<?php
declare(strict_types=1);

namespace Cms\Support;

use RuntimeException;

/**
 * Discovers the themes installed under themes/ and switches the active one.
 *
 * A theme is any direct child of themes/ that carries a style.css. The WordPress
 * style.css header is parsed for the display name, description, version, and
 * author so the admin Themes screen can present installed themes the way the
 * WordPress Appearance > Themes screen does. Activation persists the choice to
 * config.json (theme.name), which config/app.php reads on the next request.
 */
final class ThemeCatalog
{
    private const SCREENSHOT_NAMES = [
        'screenshot.png',
        'screenshot.jpg',
        'screenshot.jpeg',
        'screenshot.gif',
        'screenshot.webp',
        'screenshot.svg',
    ];

    public function __construct(private string $rootPath)
    {
    }

    /**
     * Every installed theme, sorted by display name.
     *
     * @return array<int, array<string, string|null>>
     */
    public function installed(): array
    {
        $base = $this->themesPath();

        if (!is_dir($base)) {
            return [];
        }

        $themes = [];

        foreach (scandir($base) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $theme = $this->find($entry);

            if ($theme !== null) {
                $themes[] = $theme;
            }
        }

        usort($themes, static fn (array $a, array $b): int => strcasecmp((string) $a['name'], (string) $b['name']));

        return $themes;
    }

    /**
     * Describe a single installed theme, or null if the slug is not a theme.
     *
     * @return array<string, string|null>|null
     */
    public function find(string $slug): ?array
    {
        $slug = $this->normalizeSlug($slug);

        if ($slug === null) {
            return null;
        }

        $dir = $this->themesPath() . '/' . $slug;

        if (!is_dir($dir) || !is_file($dir . '/style.css')) {
            return null;
        }

        $header = $this->parseHeader((string) @file_get_contents($dir . '/style.css'));

        return [
            'slug'      => $slug,
            'name'      => $this->firstNonEmpty($header['Theme Name'] ?? '', $slug),
            'description' => trim($header['Description'] ?? ''),
            'version'   => trim($header['Version'] ?? ''),
            'author'    => trim($header['Author'] ?? ''),
            'screenshot' => $this->screenshotName($slug),
            'runtime'   => trim($header['Local CMS Runtime'] ?? ''),
            'native'    => $this->isLocalCmsNative($dir, $header),
        ];
    }

    public function exists(string $slug): bool
    {
        return $this->find($slug) !== null;
    }

    /**
     * Whether a theme was authored for this runtime (native) or ported from WordPress.
     *
     * A theme is considered native when ALL of the following hold:
     *  - Its style.css header declares "Local CMS Runtime: native" OR "origin: local-cms", OR
     *  - Its functions.php uses `declare(strict_types=1)` (a Local CMS convention), AND
     *  - It does NOT contain any "Ported by port-cms" or "LICENSE_NOTE.txt" marker.
     *
     * Native themes can rely on Local CMS data shapes directly; ported themes go
     * through the WordPress compatibility shims.
     *
     * @param array<string, string> $header Already-parsed style.css header.
     */
    public function isLocalCmsNative(string $themeDir, array $header = []): bool
    {
        // Explicit "native" marker in the style.css header.
        $runtimeHeader = strtolower(trim($header['Local CMS Runtime'] ?? ''));
        if ($runtimeHeader === 'native') {
            return true;
        }
        $originHeader = strtolower(trim($header['Local CMS Origin'] ?? ''));
        if (in_array($originHeader, ['local-cms', 'native', 'local'], true)) {
            return true;
        }

        // A LICENSE_NOTE.txt is written by the port-cms adapter → ported theme.
        if (is_file($themeDir . '/LICENSE_NOTE.txt')) {
            return false;
        }

        // functions.php with strict_types → likely authored for this repo.
        $functionsPath = $themeDir . '/functions.php';
        if (is_file($functionsPath)) {
            $head = (string) substr((string) file_get_contents($functionsPath), 0, 1024);
            if (str_contains($head, 'declare(strict_types=1)')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a theme declares itself compatible with the local runtime.
     *
     * Compatibility is determined statically from the style.css header marker
     * ("Local CMS Runtime: compatible") rather than by executing the theme. A
     * foreign WordPress theme cannot be safely probed by loading it: its
     * functions.php may call exit()/die() when WordPress is absent (the common
     * "defined('ABSPATH') || exit;" guard), which would terminate the whole
     * process uncatchably. Themes authored for the local runtime add the marker;
     * everything else (every WordPress.org download) is treated as export/port
     * only, with no theme code ever run to decide.
     */
    public function isLocalRuntimeCompatible(string $slug): bool
    {
        $theme = $this->find($slug);

        if ($theme === null) {
            return false;
        }

        $marker = strtolower(trim((string) ($theme['runtime'] ?? '')));

        return in_array($marker, ['compatible', 'true', 'yes', '1', 'local', 'local-cms'], true);
    }

    /**
     * Why a theme cannot be activated in the local runtime, or null if it can.
     *
     * Surfaced before the theme is written into config.json so an incompatible
     * theme is never made active.
     */
    public function compatibilityError(string $slug): ?string
    {
        if (!$this->exists($slug)) {
            return 'That theme is not installed.';
        }

        if ($this->isLocalRuntimeCompatible($slug)) {
            return null;
        }

        return 'it is a WordPress theme that relies on the WordPress runtime and is not marked as compatible with the local runtime';
    }

    /**
     * Absolute path to a theme's screenshot, or null when it has none.
     */
    public function screenshotPath(string $slug): ?string
    {
        $slug = $this->normalizeSlug($slug);

        if ($slug === null) {
            return null;
        }

        $name = $this->screenshotName($slug);

        return $name === null ? null : $this->themesPath() . '/' . $slug . '/' . $name;
    }

    /**
     * Persist the active theme to config.json, preserving the rest of the file.
     */
    public function activate(string $slug): void
    {
        if (!$this->exists($slug)) {
            throw new RuntimeException('That theme is not installed.');
        }

        $slug = (string) $this->normalizeSlug($slug);
        $configPath = rtrim($this->rootPath, '/\\') . '/config.json';
        $config = [];

        if (is_file($configPath)) {
            $decoded = json_decode((string) file_get_contents($configPath), true);

            if (is_array($decoded)) {
                $config = $decoded;
            }
        }

        if (!isset($config['theme']) || !is_array($config['theme'])) {
            $config['theme'] = [];
        }

        $config['theme']['name'] = $slug;

        $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false || file_put_contents($configPath, $encoded . "\n") === false) {
            throw new RuntimeException('The theme selection could not be saved to config.json.');
        }
    }

    /**
     * Whether a theme is protected from deletion.
     *
     * A theme is protected when its directory appears in .gitignore as an
     * explicit exception — i.e. a line matching "!themes/<slug>/" or
     * "!themes/<slug>" — meaning it is committed to the repository. Themes
     * installed from outside (WordPress.org downloads, ports) are not excepted
     * and may be deleted.
     */
    public function isProtected(string $slug): bool
    {
        $slug = $this->normalizeSlug($slug);

        if ($slug === null) {
            return true;
        }

        $gitignorePath = rtrim($this->rootPath, '/\\') . '/.gitignore';

        if (!is_file($gitignorePath)) {
            return false;
        }

        $lines = file($gitignorePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            // Match "!themes/<slug>/" or "!themes/<slug>" exception patterns.
            if (preg_match('#^!themes/' . preg_quote($slug, '#') . '/?$#i', $line) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete an installed theme by removing its directory from themes/.
     *
     * @throws RuntimeException when the theme is not installed, is protected,
     *         is the active theme, or the directory cannot be removed.
     */
    public function delete(string $slug, string $activeTheme = ''): void
    {
        $slug = (string) $this->normalizeSlug($slug);

        if (!$this->exists($slug)) {
            throw new RuntimeException('That theme is not installed.');
        }

        if ($this->isProtected($slug)) {
            throw new RuntimeException('That theme is part of the repository and cannot be deleted.');
        }

        if ($activeTheme !== '' && strcasecmp($slug, $activeTheme) === 0) {
            throw new RuntimeException('The active theme cannot be deleted. Activate a different theme first.');
        }

        $dir = $this->themesPath() . '/' . $slug;
        $this->removeDirectory($dir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir);

        if ($entries === false) {
            throw new RuntimeException('Could not read the theme directory.');
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                if (!unlink($path)) {
                    throw new RuntimeException('Could not delete: ' . $path);
                }
            }
        }

        if (!rmdir($dir)) {
            throw new RuntimeException('Could not remove directory: ' . $dir);
        }
    }

    private function screenshotName(string $slug): ?string
    {
        $dir = $this->themesPath() . '/' . $slug;

        foreach (self::SCREENSHOT_NAMES as $name) {
            if (is_file($dir . '/' . $name)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Pull "Key: Value" fields from the leading WordPress style.css header block.
     *
     * @return array<string, string>
     */
    private function parseHeader(string $css): array
    {
        $keys = ['Theme Name', 'Description', 'Version', 'Author', 'Theme URI', 'Author URI', 'Tags', 'Local CMS Runtime'];
        $found = [];

        foreach ($keys as $key) {
            if (preg_match('/^[\s*]*' . preg_quote($key, '/') . '\s*:\s*(.+?)\s*$/mi', $css, $matches) === 1) {
                $found[$key] = trim($matches[1]);
            }
        }

        return $found;
    }

    private function firstNonEmpty(string ...$values): string
    {
        foreach ($values as $value) {
            if (trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private function normalizeSlug(string $slug): ?string
    {
        $slug = trim($slug, '/\\ ');

        if ($slug === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $slug) !== 1) {
            return null;
        }

        return $slug;
    }

    private function themesPath(): string
    {
        return rtrim($this->rootPath, '/\\') . '/themes';
    }
}
