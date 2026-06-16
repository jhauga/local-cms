<?php
declare(strict_types=1);

namespace Cms\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Keeps a ported WordPress theme from fataling on functions it never defines.
 *
 * A stock theme leans on a forest of helpers — its own (`newsx_get_option`),
 * its bundled frameworks (Kirki), and plugins it assumes are active (`pll_*`,
 * WooCommerce). Under the Local CMS runtime most of those are absent, and the
 * first undefined call aborts the render. Worse, the abort often happens inside
 * `functions.php` while it is pulling a framework, so every helper defined
 * *after* that point — including the theme's own — silently never loads, and the
 * template then fatals on one of them.
 *
 * The bridge breaks that cascade in two passes around `functions.php`:
 *
 *  1. preload()  — before functions.php runs, statically find every function the
 *     theme's source CALLS but DEFINES nowhere, and define a safe shim for each.
 *     These are the external dependencies. Because none are defined in source,
 *     shimming them can never collide with a real definition, and it lets
 *     functions.php finish — so the theme's own real helpers actually load.
 *  2. finalize() — after functions.php has run, define a shim for any function
 *     the theme's TEMPLATES call that is still undefined (e.g. a helper whose
 *     defining file load was skipped). This is the last safety net before render.
 *
 * Every shim routes through {@see fallback()}, which asks the
 * {@see ThemeFallbackRegistry} for a plausible, inert return value. The result:
 * the theme renders with its real templates, real styling, and as many of its
 * real functions as could be recovered — and never fatals on the rest.
 */
final class ThemeFunctionBridge
{
    private const MAX_FILE_BYTES = 512 * 1024;
    private const MAX_FILES = 4000;

    private static ?ThemeFallbackRegistry $registry = null;

    /** @var array<string, string> synthesized function name → fallback kind (for reporting) */
    private static array $synthesized = [];

    /** @var string[] classes the fallback autoloader has stubbed (for reporting) */
    private static array $stubbedClasses = [];

    private static bool $classAutoloaderRegistered = false;

    /**
     * The fallback value for a synthesized function call. Called by every shim.
     */
    public static function fallback(string $name, array $args): mixed
    {
        $registry = self::$registry ?? new ThemeFallbackRegistry();

        return $registry->valueFor($name, $args);
    }

    public static function activate(ThemeFallbackRegistry $registry): void
    {
        self::$registry = $registry;
    }

    /**
     * Before functions.php: shim external dependencies so it can load fully.
     *
     * @return array{synthesized: array<string, string>, called: int, defined: int}
     */
    public static function preload(string $themePath, string $rootPath): array
    {
        self::$synthesized = [];
        self::registerClassAutoloader();
        $scan = self::scan($themePath, $rootPath);

        // External deps: called somewhere in the theme but defined nowhere in it.
        $externals = [];
        foreach ($scan['called'] as $name) {
            if (!isset($scan['definedLookup'][strtolower($name)]) && !function_exists($name)) {
                $externals[] = $name;
            }
        }

        self::defineShims($externals);

        return [
            'synthesized' => self::$synthesized,
            'called'      => count($scan['called']),
            'defined'     => count($scan['definedLookup']),
        ];
    }

    /**
     * After functions.php: shim any still-undefined function the templates call.
     *
     * @return array<string, string> newly synthesized name → kind
     */
    public static function finalize(string $themePath, string $rootPath): array
    {
        $scan = self::scan($themePath, $rootPath);

        // Shim a template-called function only when it is still undefined AND the
        // theme does not define it inside one of its own template files. A
        // function defined in a template part (e.g. author.php) will define
        // itself when that part renders; shimming it first would cause a
        // "cannot redeclare" fatal the moment the part loads.
        $missing = [];
        foreach ($scan['templateCalled'] as $name) {
            if (!function_exists($name) && !isset($scan['definedInTemplate'][strtolower($name)])) {
                $missing[] = $name;
            }
        }

        self::defineShims($missing);

        return self::$synthesized;
    }

    /** Every function name the bridge has synthesized this request, name → kind. */
    public static function synthesized(): array
    {
        ksort(self::$synthesized);

        return self::$synthesized;
    }

    /** Class names the fallback autoloader has stubbed this request. */
    public static function stubbedClasses(): array
    {
        sort(self::$stubbedClasses);

        return self::$stubbedClasses;
    }

    /**
     * Report which functions the bridge would synthesize for a theme, and the
     * fallback kind the registry assigns each — without defining anything.
     *
     * Drives the admin Theme Bridge screen: it surfaces the theme-specific
     * "elements" the runtime has no real implementation for, so an operator can
     * see them and override how each one behaves.
     *
     * @return array<string, string> function name → fallback kind, sorted by name
     */
    public static function report(string $themePath, string $rootPath, ThemeFallbackRegistry $registry): array
    {
        $scan = self::scan($themePath, $rootPath);

        $names = [];

        foreach ($scan['called'] as $name) {
            if (!isset($scan['definedLookup'][strtolower($name)]) && !function_exists($name)) {
                $names[$name] = true;
            }
        }

        foreach ($scan['templateCalled'] as $name) {
            if (!function_exists($name) && !isset($scan['definedInTemplate'][strtolower($name)])) {
                $names[$name] = true;
            }
        }

        $report = [];
        foreach (array_keys($names) as $name) {
            $report[$name] = $registry->kindFor($name);
        }

        ksort($report);

        return $report;
    }

    /**
     * Register a last-resort autoloader that defines any still-unresolved class
     * as a benign {@see LocalCmsSafeClass} subclass.
     *
     * Registered once, and only stubs while a theme render is active (the
     * registry is set). Classes in the app's own "Cms\" namespace are never
     * stubbed — they must resolve through the real autoloader or fail loudly.
     * Runs last (appended), so it only fires when every real autoloader has
     * already declined the class.
     */
    private static function registerClassAutoloader(): void
    {
        if (self::$classAutoloaderRegistered) {
            return;
        }

        self::$classAutoloaderRegistered = true;

        spl_autoload_register(static function (string $class): void {
            // Only act during an active ported-theme render.
            if (self::$registry === null) {
                return;
            }

            // Never shadow the application's own classes.
            if (strncmp($class, 'Cms\\', 4) === 0) {
                return;
            }

            if (
                class_exists($class, false)
                || interface_exists($class, false)
                || trait_exists($class, false)
                || (function_exists('enum_exists') && enum_exists($class, false))
            ) {
                return;
            }

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $class) !== 1) {
                return;
            }

            $separator = strrpos($class, '\\');
            if ($separator === false) {
                $code = "class {$class} extends \\Cms\\Support\\LocalCmsSafeClass {}";
            } else {
                $namespace = substr($class, 0, $separator);
                $short = substr($class, $separator + 1);
                $code = "namespace {$namespace}; class {$short} extends \\Cms\\Support\\LocalCmsSafeClass {}";
            }

            try {
                eval($code);
                self::$stubbedClasses[] = $class;
            } catch (Throwable) {
                // If the stub cannot be defined the class simply stays missing,
                // which is exactly the state we were already in.
            }
        });
    }

    /**
     * Define a guarded shim for each name not already defined.
     *
     * @param string[] $names
     */
    private static function defineShims(array $names): void
    {
        $registry = self::$registry ?? new ThemeFallbackRegistry();
        $code = '';

        foreach (array_unique($names) as $name) {
            if (preg_match('/^[a-z_][a-z0-9_]*$/i', $name) !== 1 || function_exists($name)) {
                continue;
            }

            // Single-quoted name is regex-validated above, so this is injection-safe.
            $code .= "if(!function_exists('{$name}')){function {$name}(...\$a){return \\Cms\\Support\\ThemeFunctionBridge::fallback('{$name}',\$a);}}\n";
            self::$synthesized[$name] = $registry->kindFor($name);
        }

        if ($code !== '') {
            try {
                eval($code);
            } catch (Throwable) {
                // A malformed batch must never take down the render; the worst case
                // is that an unshimmed call fatals exactly as it would have anyway.
            }
        }
    }

    /**
     * Tokenize the theme's PHP and collect defined / called function names.
     *
     * Cached per theme, keyed on the mtimes of functions.php and style.css, so
     * the (potentially large) source tree is only walked when the theme changes.
     *
     * @return array{
     *     definedLookup: array<string, true>,
     *     called: string[],
     *     templateCalled: string[]
     * }
     */
    private static function scan(string $themePath, string $rootPath): array
    {
        $cached = self::readCache($themePath, $rootPath);
        if ($cached !== null) {
            return $cached;
        }

        $defined = [];
        $definedInTemplate = [];
        $called = [];
        $templateCalled = [];
        $count = 0;

        foreach (self::phpFiles($themePath) as $file) {
            if (++$count > self::MAX_FILES) {
                break;
            }

            $size = @filesize($file);
            if ($size === false || $size > self::MAX_FILE_BYTES) {
                continue;
            }

            $source = @file_get_contents($file);
            if ($source === false || $source === '') {
                continue;
            }

            [$fileDefined, $fileCalled] = self::tokenize($source);
            $isTemplate = self::isTemplateFile($themePath, $file);

            foreach ($fileDefined as $name) {
                $defined[strtolower($name)] = true;
                if ($isTemplate) {
                    $definedInTemplate[strtolower($name)] = true;
                }
            }
            foreach ($fileCalled as $name) {
                $called[$name] = true;
            }

            if ($isTemplate) {
                foreach ($fileCalled as $name) {
                    $templateCalled[$name] = true;
                }
            }
        }

        $result = [
            'definedLookup'     => $defined,
            'definedInTemplate' => $definedInTemplate,
            'called'            => array_keys($called),
            'templateCalled'    => array_keys($templateCalled),
        ];

        self::writeCache($themePath, $rootPath, $result);

        return $result;
    }

    /**
     * Pull function definitions and real function calls out of one PHP file.
     *
     * A call is a T_STRING immediately followed by "(" that is not preceded by
     * `->`, `?->`, `::`, `function`, or `new` — so method calls, static calls,
     * definitions, and class instantiations are all excluded, and CSS/JS tokens
     * living inside string literals are never seen at all.
     *
     * @return array{0: string[], 1: string[]} [definedNames, calledNames]
     */
    private static function tokenize(string $source): array
    {
        try {
            $tokens = token_get_all($source);
        } catch (Throwable) {
            return [[], []];
        }

        $defined = [];
        $called = [];
        $prevSignificant = null;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                if ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }

                if ($token[0] === T_STRING) {
                    $prevId = is_array($prevSignificant) ? $prevSignificant[0] : null;
                    $prevText = is_array($prevSignificant) ? null : $prevSignificant;

                    if ($prevId === T_FUNCTION) {
                        $defined[] = $token[1];
                    } elseif (
                        $prevId !== T_OBJECT_OPERATOR
                        && $prevId !== T_DOUBLE_COLON
                        && $prevId !== T_NEW
                        && $prevText !== '\\'
                        && (defined('T_NULLSAFE_OBJECT_OPERATOR') ? $prevId !== T_NULLSAFE_OBJECT_OPERATOR : true)
                    ) {
                        // Look ahead for "(" to confirm this is a call.
                        for ($j = $i + 1; $j < $count; $j++) {
                            $next = $tokens[$j];
                            if (is_array($next) && ($next[0] === T_WHITESPACE || $next[0] === T_COMMENT || $next[0] === T_DOC_COMMENT)) {
                                continue;
                            }
                            if ($next === '(') {
                                $called[] = $token[1];
                            }
                            break;
                        }
                    }
                }

                $prevSignificant = $token;
                continue;
            }

            $prevSignificant = $token;
        }

        return [$defined, $called];
    }

    /**
     * Whether a file is a renderable template (root template or a template part),
     * as opposed to framework/include code that only runs at functions.php time.
     */
    private static function isTemplateFile(string $themePath, string $file): bool
    {
        $relative = str_replace('\\', '/', substr($file, strlen($themePath) + 1));

        if ($relative === '') {
            return false;
        }

        // Root-level PHP template (header.php, index.php, page.php, ...), but NOT
        // functions.php — that is the theme's setup file, loaded once before
        // render. Its definitions must remain eligible for shimming when it bails
        // partway, so it is treated as setup code, not a render-time template.
        if (!str_contains($relative, '/')) {
            return strtolower($relative) !== 'functions.php';
        }

        // Template-part directories WordPress themes load during render.
        return (bool) preg_match('#^(template-parts|parts|templates|partials|content)/#i', $relative);
    }

    /** @return iterable<string> */
    private static function phpFiles(string $themePath): iterable
    {
        if (!is_dir($themePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($themePath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                yield $file->getPathname();
            }
        }
    }

    // --- caching ------------------------------------------------------------

    private static function cachePath(string $themePath, string $rootPath): string
    {
        $key = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($themePath)) ?? 'theme';

        return rtrim($rootPath, '/\\') . '/storage/cache/theme-bridge/' . $key . '.json';
    }

    private static function cacheKey(string $themePath): string
    {
        // Bump the leading version when the cached shape changes so stale caches
        // from an earlier build are discarded rather than misread.
        $signals = ['v2'];
        foreach (['functions.php', 'style.css'] as $marker) {
            $path = $themePath . '/' . $marker;
            $signals[] = is_file($path) ? (string) @filemtime($path) : '0';
        }

        return implode(':', $signals);
    }

    private static function readCache(string $themePath, string $rootPath): ?array
    {
        $path = self::cachePath($themePath, $rootPath);

        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) @file_get_contents($path), true);

        if (!is_array($decoded) || ($decoded['key'] ?? null) !== self::cacheKey($themePath)) {
            return null;
        }

        return [
            'definedLookup'     => array_fill_keys(array_map('strtolower', (array) ($decoded['defined'] ?? [])), true),
            'definedInTemplate' => array_fill_keys(array_map('strtolower', (array) ($decoded['definedInTemplate'] ?? [])), true),
            'called'            => (array) ($decoded['called'] ?? []),
            'templateCalled'    => (array) ($decoded['templateCalled'] ?? []),
        ];
    }

    private static function writeCache(string $themePath, string $rootPath, array $result): void
    {
        $path = self::cachePath($themePath, $rootPath);
        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            return;
        }

        $payload = [
            'key'               => self::cacheKey($themePath),
            'defined'           => array_keys($result['definedLookup']),
            'definedInTemplate' => array_keys($result['definedInTemplate']),
            'called'            => $result['called'],
            'templateCalled'    => $result['templateCalled'],
        ];

        @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_SLASHES));
    }
}
