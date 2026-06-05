<?php
declare(strict_types=1);

namespace Cms\Support;

use RuntimeException;
use ZipArchive;

/**
 * Read-and-install bridge to the public WordPress.org theme directory.
 *
 * Mirrors the data behind wp-admin/theme-install.php?browse=popular: it queries
 * the WordPress.org Themes API for a browse list and can download and unpack a
 * chosen theme into themes/. Every network call degrades gracefully so the admin
 * Themes screen still works offline (the browse list simply returns an error
 * string and the installed themes remain available).
 *
 * Downloaded themes are WordPress-shaped; they render in a stock WordPress
 * install and are staged here for use with the Local CMS WordPress-compatible
 * runtime and for porting.
 */
final class WordPressThemeDirectory
{
    private const API = 'https://api.wordpress.org/themes/info/1.2/';

    private const BROWSE = ['popular', 'new', 'updated', 'featured'];

    /**
     * Cached User-Agent string derived from the local git remote, so forks of
     * this template repository identify themselves with their own GitHub URL
     * when calling the WordPress.org Themes API.
     */
    private ?string $userAgent = null;

    public function __construct(private string $rootPath)
    {
    }

    public function isAvailable(): bool
    {
        return function_exists('curl_init') || filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Fetch a browse list of themes.
     *
     * @return array{themes: array<int, array<string, string|float>>, error: ?string, page: int, perPage: int, hasMore: bool}
     */
    public function browse(string $browse = 'popular', int $perPage = 12, int $page = 1): array
    {
        $browse = in_array($browse, self::BROWSE, true) ? $browse : 'popular';
        $perPage = max(1, min(30, $perPage));
        $page = max(1, $page);

        $query = http_build_query([
            'action' => 'query_themes',
            'request' => [
                'browse' => $browse,
                'per_page' => $perPage,
                'page' => $page,
                'fields' => [
                    'screenshot_url' => true,
                    'description' => true,
                    'rating' => true,
                    'downloaded' => false,
                    'sections' => false,
                ],
            ],
        ]);

        $raw = $this->httpGet(self::API . '?' . $query);

        $base = ['themes' => [], 'page' => $page, 'perPage' => $perPage, 'hasMore' => false];

        if ($raw === null) {
            return $base + ['error' => 'Could not reach the WordPress.org theme directory.'];
        }

        $data = json_decode($raw, true);

        if (!is_array($data) || !isset($data['themes']) || !is_array($data['themes'])) {
            return $base + ['error' => 'The WordPress.org theme directory returned an unexpected response.'];
        }

        $themes = [];

        foreach ($data['themes'] as $theme) {
            if (!is_array($theme)) {
                continue;
            }

            $themes[] = [
                'slug' => (string) ($theme['slug'] ?? ''),
                'name' => (string) ($theme['name'] ?? ''),
                'version' => (string) ($theme['version'] ?? ''),
                'author' => $this->authorName($theme['author'] ?? ''),
                'screenshot' => $this->absoluteUrl((string) ($theme['screenshot_url'] ?? '')),
                'preview' => (string) ($theme['preview_url'] ?? ''),
                'rating' => (float) ($theme['rating'] ?? 0),
            ];
        }

        // The Themes API returns pagination metadata under `info`; fall back to
        // a heuristic (a full page implies more results) when it is missing.
        $info = is_array($data['info'] ?? null) ? $data['info'] : [];
        $totalPages = isset($info['pages']) ? (int) $info['pages'] : 0;
        $hasMore = $totalPages > 0 ? $page < $totalPages : count($themes) >= $perPage;

        return [
            'themes' => $themes,
            'page' => $page,
            'perPage' => $perPage,
            'hasMore' => $hasMore,
            'error' => null,
        ];
    }

    /**
     * Download a theme by slug, stage it in a repo-local temp folder, then move
     * the unpacked theme into themes/. Returns the slug.
     *
     * The package is downloaded and extracted under storage/tmp/ first, validated
     * there, and only moved into themes/ once it is confirmed to be a real theme.
     * Staging in the repo keeps the temp files on the same filesystem as the
     * destination (so the final move is an atomic rename) and leaves themes/
     * untouched if the download or extraction fails partway through.
     */
    public function install(string $slug): string
    {
        $slug = strtolower(trim($slug));

        if (preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug) !== 1) {
            throw new RuntimeException('That theme slug is not valid.');
        }

        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Installing themes requires the PHP zip extension.');
        }

        $target = rtrim($this->rootPath, '/\\') . '/themes/' . $slug;

        if (is_dir($target)) {
            throw new RuntimeException('A theme named "' . $slug . '" is already installed.');
        }

        $downloadUrl = $this->downloadUrl($slug);
        $zipBytes = $this->httpGet($downloadUrl, true);

        if ($zipBytes === null || $zipBytes === '') {
            throw new RuntimeException('The theme package could not be downloaded.');
        }

        $work = $this->makeTempDir();

        try {
            $zipPath = $work . '/' . $slug . '.zip';

            if (file_put_contents($zipPath, $zipBytes) === false) {
                throw new RuntimeException('The downloaded theme package could not be written to disk.');
            }

            $this->extractTo($zipPath, $work, $slug);

            $staged = $work . '/' . $slug;

            if (!is_dir($staged) || !is_file($staged . '/style.css')) {
                throw new RuntimeException('The downloaded package did not contain a valid theme.');
            }

            if (!@rename($staged, $target)) {
                // Fallback when the temp dir and themes/ live on different
                // volumes and rename() cannot cross the boundary.
                $this->copyDir($staged, $target);
            }
        } finally {
            $this->removeDir($work);
        }

        if (!is_file($target . '/style.css')) {
            throw new RuntimeException('The theme could not be moved into themes/.');
        }

        return $slug;
    }

    /**
     * Resolve the download package URL for a theme via the API.
     */
    private function downloadUrl(string $slug): string
    {
        $query = http_build_query([
            'action' => 'theme_information',
            'request' => [
                'slug' => $slug,
                'fields' => ['sections' => false],
            ],
        ]);

        $raw = $this->httpGet(self::API . '?' . $query);
        $data = $raw !== null ? json_decode($raw, true) : null;

        $downloadUrl = is_array($data) ? (string) ($data['download_link'] ?? '') : '';

        if ($downloadUrl === '' || !str_starts_with($downloadUrl, 'https://downloads.wordpress.org/')) {
            throw new RuntimeException('No download package is available for that theme.');
        }

        return $downloadUrl;
    }

    private function extractTo(string $zipPath, string $destination, string $expectedSlug): void
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('The theme package could not be opened.');
        }

        // WordPress.org packages always unpack to a single top-level <slug>/ dir.
        // Reject anything that would escape the destination or that ships a
        // different top-level folder than the slug being installed.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (str_contains($name, '..') || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name) === 1) {
                $zip->close();

                throw new RuntimeException('The theme package contains unsafe file paths.');
            }

            $top = strtok($name, '/');

            if ($top !== false && $top !== $expectedSlug) {
                $zip->close();

                throw new RuntimeException('The theme package did not match the requested theme.');
            }
        }

        if (!$zip->extractTo($destination)) {
            $zip->close();

            throw new RuntimeException('The theme package could not be extracted.');
        }

        $zip->close();
    }

    /**
     * Create a fresh repo-local staging directory under storage/tmp/.
     */
    private function makeTempDir(): string
    {
        $base = rtrim($this->rootPath, '/\\') . '/storage/tmp';

        if (!is_dir($base) && !@mkdir($base, 0777, true) && !is_dir($base)) {
            throw new RuntimeException('The temporary staging folder (storage/tmp) could not be created.');
        }

        $dir = $base . '/theme-' . bin2hex(random_bytes(6));

        if (!@mkdir($dir, 0777, true)) {
            throw new RuntimeException('A temporary staging folder could not be created.');
        }

        return $dir;
    }

    /**
     * Recursively remove a staging directory and its contents.
     */
    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir) ?: [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;

            if (is_dir($path) && !is_link($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    /**
     * Recursively copy a directory tree (rename() cross-volume fallback).
     */
    private function copyDir(string $source, string $destination): void
    {
        if (!is_dir($destination) && !@mkdir($destination, 0777, true) && !is_dir($destination)) {
            throw new RuntimeException('The theme folder could not be created in themes/.');
        }

        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $from = $source . '/' . $entry;
            $to = $destination . '/' . $entry;

            if (is_dir($from)) {
                $this->copyDir($from, $to);
            } elseif (!@copy($from, $to)) {
                throw new RuntimeException('A theme file could not be copied into themes/.');
            }
        }
    }

    private function authorName(mixed $author): string
    {
        if (is_array($author)) {
            return (string) ($author['display_name'] ?? $author['author'] ?? '');
        }

        return (string) $author;
    }

    private function absoluteUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return $url;
    }

    /**
     * Build the outbound User-Agent string. The project name is fixed (this
     * template repo ships as "LocalCMS"), but the GitHub coordinates are
     * detected from the local clone so a fork identifies itself rather than
     * pretending to be the upstream template.
     */
    private function userAgent(): string
    {
        if ($this->userAgent !== null) {
            return $this->userAgent;
        }

        $base = 'LocalCMS/0.1';
        $repository = $this->detectGitHubRepository();

        $this->userAgent = $repository === null
            ? $base
            : $base . '; +https://github.com/' . $repository;

        return $this->userAgent;
    }

    /**
     * Inspect the workspace's git configuration for an origin remote on
     * github.com and return the `<user>/<repo>` slug. Returns null when the
     * clone has no git metadata, no origin remote, or a non-GitHub origin.
     */
    private function detectGitHubRepository(): ?string
    {
        $configPath = rtrim($this->rootPath, '/\\') . '/.git/config';

        if (!is_file($configPath) || !is_readable($configPath)) {
            return null;
        }

        $contents = @file_get_contents($configPath);

        if (!is_string($contents) || $contents === '') {
            return null;
        }

        // Walk the INI-style sections and capture the url under [remote "origin"].
        $originUrl = '';
        $inOrigin = false;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, ';')) {
                continue;
            }

            if (str_starts_with($trimmed, '[')) {
                $inOrigin = preg_match('/^\[\s*remote\s+"origin"\s*\]$/', $trimmed) === 1;
                continue;
            }

            if ($inOrigin && preg_match('/^url\s*=\s*(.+)$/', $trimmed, $match) === 1) {
                $originUrl = trim($match[1]);
                break;
            }
        }

        if ($originUrl === '') {
            return null;
        }

        // Accept the common GitHub remote shapes:
        //   https://github.com/<user>/<repo>(.git)?
        //   git@github.com:<user>/<repo>(.git)?
        //   ssh://git@github.com/<user>/<repo>(.git)?
        $pattern = '#(?:https?://|git@|ssh://(?:git@)?)github\.com[:/]([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?/?$#';

        if (preg_match($pattern, $originUrl, $match) !== 1) {
            return null;
        }

        return $match[1] . '/' . $match[2];
    }

    private function httpGet(string $url, bool $binary = false): ?string
    {
        $userAgent = $this->userAgent();

        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_TIMEOUT => $binary ? 30 : 10,
                CURLOPT_USERAGENT => $userAgent,
            ]);

            // Many PHP builds (notably on Windows) ship without a configured CA
            // bundle, so HTTPS verification fails with "unable to get local
            // issuer certificate". Prefer the operating system's native trust
            // store when libcurl supports it, which needs no bundled cacert.pem.
            if (defined('CURLSSLOPT_NATIVE_CA') && ini_get('curl.cainfo') === '' && ini_get('openssl.cafile') === '') {
                curl_setopt($handle, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
            }

            $result = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);

            if (is_string($result) && $status >= 200 && $status < 300) {
                return $result;
            }

            return null;
        }

        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $binary ? 30 : 10,
                'header' => 'User-Agent: ' . $userAgent . "\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);

        return $result === false ? null : $result;
    }
}
