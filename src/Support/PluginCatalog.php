<?php
declare(strict_types=1);

namespace Cms\Support;

/**
 * Discovers plugins installed under plugins/.
 *
 * A plugin is any direct child of plugins/ that contains a PHP file sharing
 * the directory name (e.g. plugins/my-plugin/my-plugin.php). The WordPress
 * plugin header comment block is parsed so the dashboard can show the name,
 * description, version, author, and URI without executing any plugin code.
 */
final class PluginCatalog
{
    public function __construct(private string $rootPath)
    {
    }

    /**
     * Every installed plugin, sorted by display name.
     *
     * @return array<int, array<string, string|null>>
     */
    public function installed(): array
    {
        $base = $this->pluginsPath();

        if (!is_dir($base)) {
            return [];
        }

        $plugins = [];

        foreach (scandir($base) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $plugin = $this->find($entry);

            if ($plugin !== null) {
                $plugins[] = $plugin;
            }
        }

        usort($plugins, static fn (array $a, array $b): int => strcasecmp((string) $a['name'], (string) $b['name']));

        return $plugins;
    }

    /**
     * Describe a single installed plugin, or null when the slug is not a plugin.
     *
     * @return array<string, string|null>|null
     */
    public function find(string $slug): ?array
    {
        $slug = $this->normalizeSlug($slug);

        if ($slug === null) {
            return null;
        }

        $dir = $this->pluginsPath() . '/' . $slug;

        if (!is_dir($dir)) {
            return null;
        }

        // The main plugin file must share the directory name.
        $mainFile = $dir . '/' . $slug . '.php';

        if (!is_file($mainFile)) {
            // Fall back: look for any PHP file with a Plugin Name header.
            $mainFile = $this->findMainFile($dir);

            if ($mainFile === null) {
                return null;
            }
        }

        $header = $this->parseHeader((string) @file_get_contents($mainFile));

        if (($header['Plugin Name'] ?? '') === '') {
            return null;
        }

        return [
            'slug'        => $slug,
            'name'        => $this->firstNonEmpty($header['Plugin Name'] ?? '', $slug),
            'description' => trim($header['Description'] ?? ''),
            'version'     => trim($header['Version'] ?? ''),
            'author'      => trim($header['Author'] ?? ''),
            'pluginUri'   => trim($header['Plugin URI'] ?? ''),
            'authorUri'   => trim($header['Author URI'] ?? ''),
            'license'     => trim($header['License'] ?? ''),
            'licenseUri'  => trim($header['License URI'] ?? ''),
            'requiresWP'  => trim($header['Requires at least'] ?? ''),
            'requiresPHP' => trim($header['Requires PHP'] ?? ''),
            'mainFile'    => $mainFile,
        ];
    }

    public function exists(string $slug): bool
    {
        return $this->find($slug) !== null;
    }

    /**
     * Scan a plugin directory for any PHP file carrying a Plugin Name header.
     */
    private function findMainFile(string $dir): ?string
    {
        foreach (scandir($dir) ?: [] as $file) {
            if (substr($file, -4) !== '.php') {
                continue;
            }

            $path   = $dir . '/' . $file;
            $header = $this->parseHeader((string) @file_get_contents($path));

            if (($header['Plugin Name'] ?? '') !== '') {
                return $path;
            }
        }

        return null;
    }

    /**
     * Pull "Key: Value" fields from the leading WordPress plugin header comment.
     *
     * @return array<string, string>
     */
    private function parseHeader(string $php): array
    {
        $keys = [
            'Plugin Name', 'Plugin URI', 'Description', 'Version',
            'Author', 'Author URI', 'License', 'License URI',
            'Requires at least', 'Requires PHP', 'Text Domain',
        ];
        $found = [];

        // Only scan the first 8 KB where the header block lives.
        $head = substr($php, 0, 8192);

        foreach ($keys as $key) {
            if (preg_match('/^[\s*]*' . preg_quote($key, '/') . '\s*:\s*(.+?)\s*$/mi', $head, $matches) === 1) {
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

    private function pluginsPath(): string
    {
        return rtrim($this->rootPath, '/\\') . '/plugins';
    }
}
