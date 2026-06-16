<?php
declare(strict_types=1);

/**
 * Local CMS adapter for port-cms (inbound).
 *
 * Every other adapter (drupal, joomla, ...) runs outbound: it takes a Local CMS
 * theme and reshapes it for a foreign platform. This one runs in reverse. The
 * staged source is a stock WordPress theme (e.g. Twenty Twenty-One), and the
 * goal is to make it loadable by the Local CMS runtime, rewriting <staging_dir>
 * in place.
 *
 *   php transform.php <themes|plugins> <name> <staging_dir>
 *
 * The Local CMS runtime decides theme compatibility *statically* from the
 * style.css header marker "Local CMS Runtime: compatible" (see
 * src/Support/ThemeCatalog.php). A foreign theme is never executed to probe it,
 * because its functions.php may call exit()/die() when WordPress is absent. So
 * this adapter does two things, both safe and well defined:
 *
 *   1. Stamps the "Local CMS Runtime: compatible" marker into the style.css
 *      header so the runtime will offer the theme for activation.
 *   2. Neutralizes the canonical "exit if WordPress is absent" guards so the
 *      theme cannot terminate the Local CMS process when its functions.php is
 *      included.
 *
 * Anything it cannot resolve automatically is reported as a custom error with a
 * reason, and the port is aborted so a broken theme is never written back.
 */

use Cms\Port\PortCompatibilityException;

require __DIR__ . '/PortCompatibilityException.php';

/**
 * Licenses whose terms permit modifying and redistributing the source, so a
 * port (which alters the source) is allowed. Each entry is a case-insensitive
 * regex matched against the detected license string plus a display label.
 *
 * Creative Commons variants are allowed only when they permit derivatives; the
 * NoDerivatives (ND) family is rejected by FORBIDDEN_LICENSE_RULES below, which
 * takes precedence.
 *
 * @var array<string, string> regex => label
 */
const PERMISSIVE_LICENSE_RULES = [
    '/\b(gnu\s+)?(l|a)?gpl(\s|-|v|version)*[23](\.0)?(\s|-)*(or[-\s]later|\+|only)?\b/i' => 'GPL family',
    '/\bgnu\s+(lesser\s+|affero\s+)?general\s+public\s+license\b/i'                       => 'GNU General Public License',
    '/\bmit\b/i'                                                                          => 'MIT',
    '/\bapache(\s|-)*(license)?(\s|-)*2(\.0)?\b/i'                                         => 'Apache-2.0',
    '/\b(2|3|4)[-\s]?clause\s+bsd\b|\bbsd[-\s]?(2|3|4)[-\s]?clause\b|\bbsd\b/i'             => 'BSD',
    '/\b(mpl|mozilla\s+public\s+license)(\s|-)*2(\.0)?\b/i'                                => 'MPL-2.0',
    '/\bisc\b/i'                                                                           => 'ISC',
    '/\bzlib\b/i'                                                                          => 'zlib',
    '/\bwtfpl\b/i'                                                                         => 'WTFPL',
    '/\bunlicense\b|\bpublic\s+domain\b/i'                                                 => 'Public domain / Unlicense',
    '/\bcc0\b|creative\s+commons\s+zero\b/i'                                               => 'CC0',
    '/\bcc(\s|-)*by(\s|-)*(sa|nc|nc(\s|-)*sa)?(\s|-)*([1-4](\.0)?)?\b/i'                    => 'Creative Commons (derivatives allowed)',
];

/**
 * Licenses that forbid altering the source (or are proprietary). Matched first;
 * a hit refuses the port even when a permissive rule above would also match
 * (e.g. "CC BY-ND" matches both the CC rule and this one).
 *
 * @var array<string, string> regex => reason
 */
const FORBIDDEN_LICENSE_RULES = [
    '/\bnd\b|no[-\s]?deriv|creative\s+commons.*\bnd\b|cc(\s|-)*by[-\s]*(nc[-\s]*)?nd\b/i'
        => 'it forbids derivative works, so the source may not be altered',
    '/\ball\s+rights\s+reserved\b|\bproprietary\b|\bcommercial\b.*\blicense\b/i'
        => 'it is proprietary / all-rights-reserved, so the source may not be altered or redistributed',
];

/**
 * Strong signals that a template's output is produced by a page-builder runtime
 * the Local CMS runtime cannot satisfy (component frameworks, theme singletons).
 * A match means the template renders nothing — or fatals — under the runtime, so
 * it is replaced with a minimal portable equivalent.
 *
 * @var array<string, string> regex => human-readable framework label
 */
const BUILDER_RENDER_RULES = [
    // Component frameworks: $obj->get('header')->render() (ColibriWP / Kubio).
    '/->\s*get\s*\(\s*[\'"][^\'"]+[\'"]\s*\)\s*->\s*render\s*\(/' => 'component-render framework (ColibriWP/Kubio-style)',
    '/\bkubio_theme\s*\(/'                                       => 'Kubio builder runtime',
    '/\bColibriWP\\\\/'                                          => 'ColibriWP framework',
    // Theme singletons whose templates delegate chrome/content to action hooks.
    '/\bkadence\s*\(\s*\)\s*->/'                                 => 'Kadence builder runtime',
    '/\bGeneratePress\b|\bgenerate_do_/'                         => 'GeneratePress builder runtime',
    '/\bAstra_|\bastra_get_/'                                    => 'Astra builder runtime',
    '/\boceanwp_/'                                               => 'OceanWP builder runtime',
    '/\belementor\b.*->\s*render|\\\\Elementor\\\\/'             => 'Elementor builder runtime',
];

/**
 * The templates the runtime renders directly, mapped to the role each plays.
 * "ensure" templates are created when missing; "chrome" templates (header/footer)
 * are screened and replaced as a pair so their opening/closing markup always
 * matches. "optional" templates are only replaced when they exist and are
 * builder-dependent (never created), so the WordPress template hierarchy is not
 * altered by introducing a route the theme never defined.
 *
 * @var array<string, string> filename (no extension) => role
 */
const SCREENED_TEMPLATES = [
    'header'     => 'chrome',
    'footer'     => 'chrome',
    'index'      => 'ensure',
    'single'     => 'ensure',
    'page'       => 'ensure',
    'archive'    => 'ensure',
    '404'        => 'ensure',
    'front-page' => 'optional',
    'home'       => 'optional',
    'search'     => 'optional',
];

/** Marker written into every minimal template so re-runs never re-quarantine it. */
const MINIMAL_TEMPLATE_MARKER = '[local-cms] minimal portable template';

/**
 * Marker left by an earlier version of this adapter on the thin stubs it wrote
 * for missing routes. Those stubs render, but sub-par (an archive without its
 * title, a full-content dump where an excerpt list belongs), so they are
 * upgraded in place to the current minimal set. They are machine-generated, not
 * theme originals, so the upgrade does not quarantine anything.
 */
const LEGACY_STUB_MARKER = '[local-cms] Generated fallback template';

[$self, $tool, $name, $stage] = array_pad($argv, 4, null);

try {
    run((string) $tool, (string) $name, (string) $stage);
} catch (PortCompatibilityException $e) {
    // Custom error: the source cannot be ported, with the reason why.
    fwrite(STDERR, '[local-cms] Cannot port "' . (string) $name . '": ' . $e->getMessage() . "\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, '[local-cms] Unexpected failure porting "' . (string) $name . '": ' . $e->getMessage() . "\n");
    exit(1);
}

echo "[local-cms] Ported \"{$name}\" into a Local CMS-compatible theme.\n";
exit(0);

// ---------------------------------------------------------------------------

/**
 * Validate and port the staged WordPress theme into Local CMS form, in place.
 *
 * @throws PortCompatibilityException when the source is not a portable theme.
 */
function run(string $tool, string $name, string $stage): void
{
    if ($stage === '' || !is_dir($stage)) {
        throw new PortCompatibilityException('the staging directory does not exist.');
    }

    // Inbound porting reshapes a WordPress *theme* into runtime templates; there
    // is no equivalent inbound mapping for plugins yet.
    if ($tool !== 'themes') {
        throw new PortCompatibilityException(
            "porting into Local CMS supports themes only (got \"{$tool}\")."
        );
    }

    $stage = rtrim($stage, "/\\");
    $stylePath = $stage . '/style.css';

    if (!is_file($stylePath)) {
        throw new PortCompatibilityException(
            'the source has no style.css, so it is not a WordPress theme.'
        );
    }

    $style = (string) file_get_contents($stylePath);

    if (!preg_match('/^[\s*]*Theme Name\s*:\s*\S/mi', $style)) {
        throw new PortCompatibilityException(
            "style.css is missing the required \"Theme Name\" header."
        );
    }

    // index.php is the base template the runtime falls back to. Block/FSE themes
    // ship templates/*.html instead and have no index.php — the runtime cannot
    // render those, but rather than refuse the theme outright, the template
    // screen below generates a minimal portable index.php so the theme still
    // renders (with its own styling) instead of fataling on a missing template.
    if (!is_file($stage . '/index.php')) {
        echo "[local-cms] No index.php (block/FSE theme); a minimal portable index will be generated.\n";
    }

    // License gate: only port a theme whose license permits modifying and
    // redistributing the source. Unknown or no-derivatives licenses are refused
    // so the port never alters source it has no right to alter.
    $license = detect_license($stage, $style);
    assert_license_permits_porting($license);

    $style = stamp_runtime_marker($style);

    if (file_put_contents($stylePath, $style) === false) {
        throw new PortCompatibilityException('style.css could not be written with the runtime marker.');
    }

    $neutralized = neutralize_wordpress_guards($stage);
    write_license_note($stage, $name, $style, $license);
    $screen = screen_and_port_templates($stage);
    write_port_report($stage, $name, $screen);

    echo "[local-cms] License \"{$license['id']}\" ({$license['source']}) permits porting.\n";
    echo "[local-cms] Stamped \"Local CMS Runtime: compatible\" into style.css.\n";
    echo $neutralized > 0
        ? "[local-cms] Neutralized WordPress-only exit guards in {$neutralized} file(s).\n"
        : "[local-cms] No WordPress-only exit guards needed neutralizing.\n";

    foreach ($screen['frameworks'] as $framework) {
        echo "[local-cms] Detected unsupported runtime: {$framework}.\n";
    }
    if ($screen['replaced'] !== []) {
        echo "[local-cms] Replaced builder-dependent templates with minimal portable ones: "
            . implode(', ', $screen['replaced']) . ".\n";
        echo "[local-cms]   (originals preserved under _unported/)\n";
    }
    if ($screen['created'] !== []) {
        echo "[local-cms] Generated minimal templates for missing routes: "
            . implode(', ', $screen['created']) . ".\n";
    }
    if ($screen['kept'] !== []) {
        echo "[local-cms] Kept theme-native templates (already runtime-safe): "
            . implode(', ', $screen['kept']) . ".\n";
    }
    echo "[local-cms] Wrote LICENSE_NOTE.txt and PORT_REPORT.txt.\n";
}

/**
 * Discover the theme's license from the usual places.
 *
 * Checks, in order, the style.css "License" header, readme.txt, package.json,
 * and a bundled LICENSE/COPYING file. Returns the first non-empty signal as
 * ['id' => string, 'uri' => string, 'source' => string, 'hasFile' => bool].
 */
function detect_license(string $stage, string $style): array
{
    $uri = '';
    if (preg_match('/^[\s*]*License\s*URI\s*:\s*(.+?)\s*$/mi', $style, $m)) {
        $uri = trim($m[1]);
    }

    // 1. style.css "License:" header.
    if (preg_match('/^[\s*]*License\s*:\s*(.+?)\s*$/mi', $style, $m) && trim($m[1]) !== '') {
        return ['id' => trim($m[1]), 'uri' => $uri, 'source' => 'style.css', 'hasFile' => has_license_file($stage)];
    }

    // 2. readme.txt "License:" line.
    $readme = $stage . '/readme.txt';
    if (is_file($readme)) {
        $text = (string) file_get_contents($readme);
        if (preg_match('/^\s*License\s*:\s*(.+?)\s*$/mi', $text, $m) && trim($m[1]) !== '') {
            if ($uri === '' && preg_match('/^\s*License\s*URI\s*:\s*(.+?)\s*$/mi', $text, $u)) {
                $uri = trim($u[1]);
            }
            return ['id' => trim($m[1]), 'uri' => $uri, 'source' => 'readme.txt', 'hasFile' => has_license_file($stage)];
        }
    }

    // 3. package.json "license" field.
    $packageJson = $stage . '/package.json';
    if (is_file($packageJson)) {
        $data = json_decode((string) file_get_contents($packageJson), true);
        if (is_array($data) && isset($data['license']) && is_string($data['license']) && trim($data['license']) !== '') {
            return ['id' => trim($data['license']), 'uri' => $uri, 'source' => 'package.json', 'hasFile' => has_license_file($stage)];
        }
    }

    // 4. Bundled LICENSE / COPYING file: read the first lines for a known name.
    foreach (license_file_paths($stage) as $path) {
        if (is_file($path)) {
            $head = (string) substr((string) file_get_contents($path), 0, 4096);
            if (trim($head) !== '') {
                $firstLine = trim((string) strtok($head, "\n"));
                return [
                    'id' => $firstLine !== '' ? $firstLine : basename($path),
                    'uri' => $uri,
                    'source' => basename($path),
                    'hasFile' => true,
                ];
            }
        }
    }

    return ['id' => '', 'uri' => $uri, 'source' => 'none', 'hasFile' => has_license_file($stage)];
}

/** Candidate bundled license file paths, in priority order. */
function license_file_paths(string $stage): array
{
    return [
        $stage . '/LICENSE',
        $stage . '/LICENSE.txt',
        $stage . '/LICENSE.md',
        $stage . '/COPYING',
        $stage . '/COPYING.txt',
        $stage . '/license.txt',
    ];
}

/** Whether the theme ships its own license file. */
function has_license_file(string $stage): bool
{
    foreach (license_file_paths($stage) as $path) {
        if (is_file($path)) {
            return true;
        }
    }
    return false;
}

/**
 * Refuse the port unless the detected license permits altering the source.
 *
 * @throws PortCompatibilityException when the license forbids derivatives,
 *         is proprietary, or could not be determined.
 */
function assert_license_permits_porting(array $license): void
{
    $id = (string) $license['id'];
    $haystack = trim($id . ' ' . (string) $license['uri']);

    if ($haystack === '') {
        throw new PortCompatibilityException(
            'its license could not be determined; porting alters the source, so an unknown license is refused.'
        );
    }

    foreach (FORBIDDEN_LICENSE_RULES as $pattern => $reason) {
        if (preg_match($pattern, $haystack)) {
            throw new PortCompatibilityException("its license (\"{$id}\") is not portable: {$reason}.");
        }
    }

    foreach (PERMISSIVE_LICENSE_RULES as $pattern => $_label) {
        if (preg_match($pattern, $haystack)) {
            return;
        }
    }

    throw new PortCompatibilityException(
        "its license (\"{$id}\") is not on the list of licenses that permit altering the source, so the port is refused."
    );
}

/**
 * Write LICENSE_NOTE.txt into the ported theme.
 *
 * Records the detected license and states plainly that the port does not and
 * cannot relicense the theme: the original terms still govern. When the theme
 * ships its own LICENSE/COPYING file, the note points to it as authoritative.
 */
function write_license_note(string $stage, string $name, string $style, array $license): void
{
    $themeName = preg_match('/^[\s*]*Theme Name\s*:\s*(.+?)\s*$/mi', $style, $m) ? trim($m[1]) : $name;
    $uriLine = $license['uri'] !== '' ? (string) $license['uri'] : 'n/a';
    $generated = gmdate('Y-m-d');

    $lines = [
        'LICENSE NOTE',
        '============',
        '',
        "Theme:            {$themeName}",
        "Detected license: {$license['id']}",
        "License URI:      {$uriLine}",
        "Detected from:    {$license['source']}",
        "Ported on:        {$generated} (UTC) by port-cms (target: local-cms)",
        '',
        'This theme was ported into Local CMS-compatible form. The port only adapts',
        'the theme to the Local CMS runtime (it marks the theme runtime-compatible and',
        'neutralizes WordPress-only access guards). It does NOT relicense the theme.',
        '',
        'The original license above still governs this theme and MUST NOT be changed.',
        'Porting was permitted only because that license allows modifying and',
        'redistributing the source.',
        '',
    ];

    if (!empty($license['hasFile'])) {
        $lines[] = 'This theme ships its own license file (LICENSE / COPYING). That file is';
        $lines[] = 'preserved unchanged alongside this note and remains the authoritative,';
        $lines[] = 'unalterable statement of the license.';
        $lines[] = '';
    }

    $lines[] = 'Generated by port-cms/local-cms.';

    file_put_contents($stage . '/LICENSE_NOTE.txt', implode("\n", $lines) . "\n");
}

/**
 * Screen the theme's renderable templates and substitute minimal portable ones
 * where the original cannot work under the Local CMS runtime.
 *
 * This is the portability screen at the heart of the inbound port. Twenty
 * Twenty-One ports cleanly because its templates use the standard loop and
 * template-part delegation, which the compat layer fully supports. Builder
 * themes (Kubio's ColibriWP component runtime, Kadence's hook-driven body,
 * Elementor/Astra/OceanWP/GeneratePress singletons) instead produce their page
 * through machinery the runtime has no way to drive, so those templates render
 * blank — or fatal, as Kubio's `->get('header')->render()` does on a null
 * component.
 *
 * The rule, per the port's design goal: a small set of WordPress elements that
 * actually render is worth more than the whole theme rendering sub-par. So each
 * builder-dependent template is moved aside to _unported/ and replaced with the
 * matching file from templates/ — the proven, runtime-safe set modelled on
 * Twenty Twenty-One. Templates that already use the supported surface are left
 * untouched, so a clean classic theme is never rewritten.
 *
 * @return array{
 *     replaced: string[], created: string[], kept: string[], frameworks: string[]
 * }
 */
function screen_and_port_templates(string $stage): array
{
    $minimalDir = __DIR__ . '/templates';

    $replaced = [];
    $created = [];
    $kept = [];
    $frameworks = [];

    // Decide chrome (header/footer) as a pair: replacing one without the other
    // would leave the opening and closing markup mismatched.
    $headerFramework = builder_framework_for($stage . '/header.php');
    $footerFramework = builder_framework_for($stage . '/footer.php');
    $replaceChrome = $headerFramework !== null || $footerFramework !== null;

    foreach (SCREENED_TEMPLATES as $template => $role) {
        $path = $stage . '/' . $template . '.php';
        $minimal = $minimalDir . '/' . $template . '.php';
        $exists = is_file($path);

        // Already a minimal template from a previous port: leave it in place.
        if ($exists && is_minimal_template($path)) {
            $kept[] = $template . '.php';
            continue;
        }

        // A thin stub from an earlier porter version: upgrade it in place to the
        // current minimal template (machine-generated, so nothing to quarantine).
        if ($exists && is_legacy_stub($path)) {
            if (is_file($minimal)) {
                copy($minimal, $path);
                $replaced[] = $template . '.php';
            } else {
                $kept[] = $template . '.php';
            }
            continue;
        }

        if ($role === 'chrome') {
            if (!is_file($minimal)) {
                continue;
            }
            if ($replaceChrome) {
                if ($exists) {
                    quarantine_original($stage, $template . '.php');
                    $replaced[] = $template . '.php';
                } else {
                    $created[] = $template . '.php';
                }
                copy($minimal, $path);
                $framework = $template === 'header' ? $headerFramework : $footerFramework;
                if ($framework !== null) {
                    $frameworks[] = $framework;
                }
            } elseif ($exists) {
                $kept[] = $template . '.php';
            }
            continue;
        }

        $framework = $exists ? builder_framework_for($path) : null;

        if ($framework !== null) {
            if (!is_file($minimal)) {
                continue;
            }
            quarantine_original($stage, $template . '.php');
            copy($minimal, $path);
            $replaced[] = $template . '.php';
            $frameworks[] = $framework;
            continue;
        }

        if ($exists) {
            $kept[] = $template . '.php';
            continue;
        }

        // Missing: only "ensure" templates are created, so the template hierarchy
        // is not extended with optional routes the theme never defined.
        if ($role === 'ensure' && is_file($minimal)) {
            copy($minimal, $path);
            $created[] = $template . '.php';
        }
    }

    $frameworks = array_values(array_unique($frameworks));
    sort($replaced);
    sort($created);
    sort($kept);

    return compact('replaced', 'created', 'kept', 'frameworks');
}

/**
 * The page-builder framework a template depends on, or null when it is
 * runtime-safe.
 *
 * A template is builder-dependent when it either matches a known builder-render
 * signal, or produces its body solely through action hooks the runtime does not
 * fire (do_action with no standard loop/content output). Comments are stripped
 * first so a commented-out guard or doc block never trips the detection.
 */
function builder_framework_for(string $path): ?string
{
    if (!is_file($path)) {
        return null;
    }

    $code = strip_php_comments((string) file_get_contents($path));

    foreach (BUILDER_RENDER_RULES as $pattern => $label) {
        if (preg_match($pattern, $code)) {
            return $label;
        }
    }

    // Body produced only by theme action hooks, with no standard content output.
    if (preg_match('/\bdo_action\s*\(/', $code) && !template_outputs_standard_content($code)) {
        return 'theme action hooks the runtime does not fire';
    }

    return null;
}

/**
 * Whether a template emits content through the supported surface: the standard
 * loop, a content template tag, or a get_template_part() delegation.
 */
function template_outputs_standard_content(string $code): bool
{
    return preg_match('/\b(have_posts|the_post|the_content|the_title|the_excerpt)\s*\(/', $code) === 1
        || preg_match('/\bget_template_part\s*\(/', $code) === 1;
}

/** Whether a file is one of this adapter's minimal templates (idempotency guard). */
function is_minimal_template(string $path): bool
{
    if (!is_file($path)) {
        return false;
    }

    return str_contains((string) file_get_contents($path), MINIMAL_TEMPLATE_MARKER);
}

/** Whether a file is a thin stub written by an earlier version of this adapter. */
function is_legacy_stub(string $path): bool
{
    if (!is_file($path)) {
        return false;
    }

    return str_contains((string) file_get_contents($path), LEGACY_STUB_MARKER);
}

/**
 * Move a replaced original into _unported/ so it is preserved (auditable, and
 * restorable) but never loaded by the runtime, which only resolves templates at
 * the theme root.
 */
function quarantine_original(string $stage, string $filename): void
{
    $quarantine = $stage . '/_unported';

    if (!is_dir($quarantine)) {
        mkdir($quarantine, 0777, true);
    }

    $from = $stage . '/' . $filename;
    $to = $quarantine . '/' . $filename;

    if (is_file($to)) {
        @unlink($to);
    }

    if (!@rename($from, $to)) {
        // Fall back to copy+delete when rename across the directory is refused.
        copy($from, $to);
        @unlink($from);
    }
}

/**
 * Strip PHP comments from source so detection regexes match live code only.
 *
 * Best-effort token-based pass; on any tokenizer failure the original source is
 * returned, which only makes detection more conservative (never less safe).
 */
function strip_php_comments(string $code): string
{
    try {
        $tokens = token_get_all($code);
    } catch (\Throwable) {
        return $code;
    }

    $out = '';
    foreach ($tokens as $token) {
        if (is_array($token)) {
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                continue;
            }
            $out .= $token[1];
        } else {
            $out .= $token;
        }
    }

    return $out;
}

/**
 * Write PORT_REPORT.txt: the portability screen's verdict for this theme.
 *
 * Records which templates were kept as-is, which were replaced with minimal
 * portable equivalents, which were generated for missing routes, and the
 * builder runtimes detected — so the operator can see exactly how much of the
 * theme survived the port and why the rest was substituted.
 *
 * @param array{replaced: string[], created: string[], kept: string[], frameworks: string[]} $screen
 */
function write_port_report(string $stage, string $name, array $screen): void
{
    $generated = gmdate('Y-m-d');

    $lines = [
        'PORTABILITY REPORT',
        '==================',
        '',
        "Theme:      {$name}",
        "Screened:   {$generated} (UTC) by port-cms (target: local-cms)",
        '',
        'The Local CMS runtime renders templates through a WordPress compatibility',
        'layer that covers the classic theming surface (the loop, template tags,',
        'template parts) but not page-builder runtimes. Templates that depend on a',
        'builder were replaced with minimal portable equivalents modelled on Twenty',
        'Twenty-One; the originals are preserved unchanged under _unported/.',
        '',
    ];

    if ($screen['frameworks'] !== []) {
        $lines[] = 'Unsupported runtimes detected:';
        foreach ($screen['frameworks'] as $framework) {
            $lines[] = '  - ' . $framework;
        }
        $lines[] = '';
    }

    $section = static function (string $heading, array $items) use (&$lines): void {
        $lines[] = $heading . ($items === [] ? ' none' : '');
        foreach ($items as $item) {
            $lines[] = '  - ' . $item;
        }
        $lines[] = '';
    };

    $section('Replaced (builder-dependent → minimal portable):', $screen['replaced']);
    $section('Generated (missing route → minimal portable):', $screen['created']);
    $section('Kept (already runtime-safe, untouched):', $screen['kept']);

    $lines[] = 'To restore an original template, move it back from _unported/ into the';
    $lines[] = 'theme root — but it will only render if its builder runtime is also made';
    $lines[] = 'available to the Local CMS runtime.';
    $lines[] = '';
    $lines[] = 'Generated by port-cms/local-cms.';

    file_put_contents($stage . '/PORT_REPORT.txt', implode("\n", $lines) . "\n");
}

/**
 * Ensure the style.css header block declares the theme runtime-compatible.
 *
 * Idempotent: an existing "Local CMS Runtime:" line is rewritten to
 * "compatible"; otherwise the marker is inserted just before the header block
 * closes. The header block is the first C-style comment in the file, matching
 * how ThemeCatalog parses it.
 */
function stamp_runtime_marker(string $style): string
{
    // Already declared compatible: leave the file untouched.
    if (preg_match('/^[\s*]*Local CMS Runtime\s*:\s*compatible\s*$/mi', $style)) {
        return $style;
    }

    // A differing "Local CMS Runtime:" value: rewrite it to compatible.
    if (preg_match('/^([\s*]*)Local CMS Runtime\s*:.*$/mi', $style)) {
        return (string) preg_replace(
            '/^([\s*]*)Local CMS Runtime\s*:.*$/mi',
            '${1}Local CMS Runtime: compatible',
            $style,
            1
        );
    }

    // Insert the marker just before the first comment block closes.
    $closePos = strpos($style, '*/');

    if ($closePos === false) {
        // No recognizable header block; prepend a minimal one.
        return "/*\nLocal CMS Runtime: compatible\n*/\n" . $style;
    }

    $marker = "Local CMS Runtime: compatible\n";

    return substr($style, 0, $closePos) . $marker . substr($style, $closePos);
}

/**
 * Comment out the canonical "die when WordPress is absent" guards.
 *
 * Stock WordPress themes guard direct access with lines such as
 * `defined( 'ABSPATH' ) || exit;`. Under the Local CMS runtime ABSPATH is never
 * defined, so left intact those guards would terminate the whole process the
 * moment functions.php is included. Each matched guard line is prefixed with a
 * comment that records why, so the original intent stays visible.
 *
 * @return int Number of PHP files modified.
 */
function neutralize_wordpress_guards(string $stage): int
{
    $modified = 0;

    foreach (php_files($stage) as $file) {
        $contents = (string) file_get_contents($file);

        // Pass 1 – single-line guards:
        //   defined('ABSPATH') || exit;
        //   if ( ! defined( 'ABSPATH' ) ) exit;
        $patched = (string) preg_replace_callback(
            '/^(?<indent>[ \t]*)(?<code>(?:if\s*\(\s*!\s*defined|defined)\s*\(\s*[\'"]ABSPATH[\'"]\s*\).*?(?:exit|die)\b.*)$/mi',
            static fn (array $m): string =>
                $m['indent'] . '// [local-cms] WordPress guard disabled: ' . trim($m['code']),
            $contents,
            -1,
            $count
        );

        // Pass 2 – multi-line block guards:
        //   if ( ! defined( 'ABSPATH' ) ) {
        //       exit;
        //   }
        // The ABSPATH check line is on its own line, followed by a brace block
        // that contains only whitespace + exit/die.  Replace the whole block
        // with a single comment so the braces don't leave a syntax error.
        $count2 = 0;
        $patched = (string) preg_replace_callback(
            '/(?<indent>[ \t]*)(?<ifline>if\s*\(\s*!\s*defined\s*\(\s*[\'"]ABSPATH[\'"]\s*\)\s*\)\s*\{)\s*\n\s*(?:exit|die)\s*[;(][^\n]*\n\s*\}/mi',
            static fn (array $m): string =>
                $m['indent'] . '// [local-cms] WordPress guard disabled: ' . trim($m['ifline']) . ' ... }',
            $patched,
            -1,
            $count2
        );
        $count += $count2;

        if ($count > 0 && $patched !== $contents) {
            file_put_contents($file, $patched);
            $modified++;
        }
    }

    return $modified;
}

/**
 * Yield every .php file under the staged theme.
 *
 * @return iterable<string>
 */
function php_files(string $stage): iterable
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            yield $file->getPathname();
        }
    }
}
