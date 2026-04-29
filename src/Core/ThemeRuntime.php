<?php
declare(strict_types=1);

namespace Cms\Core;

use DateTimeImmutable;
use RuntimeException;

final class ThemeRuntime
{
    private static ?self $current = null;

    private array $queryItems;

    private ?array $currentItem;

    private int $queryIndex = -1;

    private function __construct(
        private string $themePath,
        private string $template,
        private array $data,
    ) {
        $this->queryItems = $this->resolveQueryItems($data);
        $this->currentItem = $this->resolveCurrentItem($data, $this->queryItems);
    }

    public static function boot(string $themePath, string $template, array $data): void
    {
        self::$current = new self($themePath, $template, $data);
    }

    public static function clear(): void
    {
        self::$current = null;
    }

    public static function data(string $key, mixed $default = null): mixed
    {
        return self::instance()->data[$key] ?? $default;
    }

    public static function bloginfo(string $show): string
    {
        return match ($show) {
            'name' => (string) self::data('siteName', 'Local CMS'),
            'description' => (string) self::data('siteTagline', ''),
            'stylesheet_url' => self::homeUrl((string) self::data('stylesheetUrl', '/theme/style.css')),
            'charset' => 'utf-8',
            'language' => 'en-US',
            default => '',
        };
    }

    public static function homeUrl(string $path = ''): string
    {
        $basePath = (string) self::data('homeUrl', '/');
        $basePath = $basePath === '' ? '/' : $basePath;

        if ($path === '' || $path === '/') {
            return $basePath;
        }

        if ($basePath === '/') {
            return '/' . ltrim($path, '/');
        }

        return rtrim($basePath, '/') . '/' . ltrim($path, '/');
    }

    public static function includeHeader(?string $name = null, array $args = []): void
    {
        self::instance()->includeThemeFile('header', $name, $args);
    }

    public static function includeFooter(?string $name = null, array $args = []): void
    {
        self::instance()->includeThemeFile('footer', $name, $args);
    }

    public static function includeTemplatePart(string $slug, ?string $name = null, array $args = []): void
    {
        self::instance()->includeThemeFile($slug, $name, $args);
    }

    public static function havePosts(): bool
    {
        $runtime = self::instance();

        return isset($runtime->queryItems[$runtime->queryIndex + 1]);
    }

    public static function thePost(): ?array
    {
        $runtime = self::instance();

        if (!isset($runtime->queryItems[$runtime->queryIndex + 1])) {
            return null;
        }

        $runtime->queryIndex++;
        $runtime->currentItem = $runtime->queryItems[$runtime->queryIndex];

        return $runtime->currentItem;
    }

    public static function rewindPosts(): void
    {
        $runtime = self::instance();
        $runtime->queryIndex = -1;
        $runtime->currentItem = $runtime->resolveCurrentItem($runtime->data, $runtime->queryItems);
    }

    public static function currentItem(?array $item = null): ?array
    {
        if ($item !== null) {
            return $item;
        }

        return self::instance()->currentItem;
    }

    public static function getPostType(?array $item = null): string
    {
        $current = self::currentItem($item);

        return (string) ($current['content_type'] ?? 'post');
    }

    public static function getTitle(?array $item = null): string
    {
        $current = self::currentItem($item);

        return (string) ($current['title'] ?? '');
    }

    public static function getExcerpt(?array $item = null): string
    {
        $current = self::currentItem($item);

        return (string) ($current['excerpt'] ?? '');
    }

    public static function getContent(?array $item = null): string
    {
        $current = self::currentItem($item);

        return (string) ($current['body_html'] ?? '');
    }

    public static function getAuthorName(?array $item = null): string
    {
        $current = self::currentItem($item);

        return (string) ($current['author_name'] ?? 'Editorial Team');
    }

    public static function getDate(string $format = 'M j, Y', ?array $item = null): string
    {
        $current = self::currentItem($item);
        $dateTime = (string) ($current['published_at'] ?? '');

        if ($dateTime === '') {
            return '';
        }

        try {
            return (new DateTimeImmutable($dateTime))->format($format);
        } catch (\Throwable) {
            return '';
        }
    }

    public static function getPermalink(?array $item = null): string
    {
        $current = self::currentItem($item);
        $slug = rawurlencode((string) ($current['slug'] ?? ''));

        return match (self::getPostType($current)) {
            'page' => self::homeUrl('/page/' . $slug),
            default => self::homeUrl('/post/' . $slug),
        };
    }

    public static function hasPostThumbnail(?array $item = null): bool
    {
        $current = self::currentItem($item);

        return (string) ($current['featured_image'] ?? '') !== '';
    }

    public static function getPostThumbnailUrl(?array $item = null): string
    {
        $current = self::currentItem($item);

        return (string) ($current['featured_image'] ?? '');
    }

    public static function getTerms(string $taxonomy, ?array $item = null): array
    {
        $current = self::currentItem($item);

        if ($current === null) {
            return [];
        }

        return match ($taxonomy) {
            'category' => $current['categories'] ?? [],
            'tag' => $current['tags'] ?? [],
            default => $current['terms'][$taxonomy] ?? [],
        };
    }

    public static function archiveTitle(): string
    {
        return (string) self::data('archiveTitle', 'Archive');
    }

    public static function archiveDescription(): string
    {
        return (string) self::data('archiveDescription', '');
    }

    public static function bodyClasses(array|string $classes = []): array
    {
        $runtime = self::instance();
        $pathClass = trim((string) self::data('currentPath', '/'), '/');
        $pathClass = $pathClass === '' ? 'home' : $pathClass;

        $resolved = [
            'localcms-theme',
            'view-' . self::sanitizeHtmlClass($runtime->template),
            'path-' . self::sanitizeHtmlClass($pathClass),
        ];

        if (self::isFrontPage()) {
            $resolved[] = 'home';
            $resolved[] = 'blog';
        }

        if (self::isArchive()) {
            $resolved[] = 'archive';
        }

        if (self::isSingle()) {
            $resolved[] = 'single';
            $resolved[] = 'single-post';
        }

        if (self::isPage()) {
            $resolved[] = 'page';
        }

        if (self::isCategory()) {
            $resolved[] = 'category';
        }

        if (self::isTag()) {
            $resolved[] = 'tag';
        }

        $current = self::currentItem();

        if ($current !== null) {
            $resolved[] = self::getPostType($current);

            if (!empty($current['slug'])) {
                $resolved[] = self::sanitizeHtmlClass(self::getPostType($current) . '-' . (string) $current['slug']);
            }
        }

        return self::uniqueClasses(array_merge($resolved, self::normalizeClasses($classes)));
    }

    public static function postClasses(array|string $classes = [], ?array $item = null): array
    {
        $current = self::currentItem($item);

        if ($current === null) {
            return self::normalizeClasses($classes);
        }

        $resolved = [
            self::sanitizeHtmlClass(self::getPostType($current)),
        ];

        if (!empty($current['slug'])) {
            $resolved[] = self::sanitizeHtmlClass('entry-' . (string) $current['slug']);
        }

        if (!empty($current['status'])) {
            $resolved[] = self::sanitizeHtmlClass('status-' . (string) $current['status']);
        }

        if (self::hasPostThumbnail($current)) {
            $resolved[] = 'has-post-thumbnail';
        }

        return self::uniqueClasses(array_merge($resolved, self::normalizeClasses($classes)));
    }

    public static function isFrontPage(): bool
    {
        return self::data('currentPath', '/') === '/';
    }

    public static function isHome(): bool
    {
        return self::isFrontPage();
    }

    public static function isArchive(): bool
    {
        return self::instance()->template === 'archive';
    }

    public static function isSingle(): bool
    {
        return self::instance()->template === 'single';
    }

    public static function isPage(): bool
    {
        return self::instance()->template === 'page';
    }

    public static function isSingular(): bool
    {
        return self::isSingle() || self::isPage();
    }

    public static function isCategory(): bool
    {
        return self::isArchive() && (string) (self::data('archiveTerm', [])['taxonomy'] ?? '') === 'category';
    }

    public static function isTag(): bool
    {
        return self::isArchive() && (string) (self::data('archiveTerm', [])['taxonomy'] ?? '') === 'tag';
    }

    public static function sanitizeHtmlClass(string $className): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($className)) ?? '';
        $sanitized = trim($sanitized, '-');

        return $sanitized === '' ? 'theme-item' : $sanitized;
    }

    private static function instance(): self
    {
        if (self::$current === null) {
            throw new RuntimeException('Theme runtime has not been booted.');
        }

        return self::$current;
    }

    private function includeThemeFile(string $slug, ?string $name, array $args): void
    {
        $candidates = $this->resolveCandidateFiles($slug, $name);

        foreach ($candidates as $filePath) {
            if (is_file($filePath)) {
                $this->requireFile($filePath, $args);

                return;
            }
        }
    }

    private function requireFile(string $filePath, array $args): void
    {
        extract(array_merge($this->data, $args), EXTR_SKIP);

        require $filePath;
    }

    private function resolveCandidateFiles(string $slug, ?string $name): array
    {
        $normalizedSlug = $this->normalizePathPart($slug);

        if ($normalizedSlug === null) {
            return [];
        }

        $candidates = [];
        $normalizedName = $name !== null ? $this->normalizeNamePart($name) : null;

        if ($normalizedName !== null && $normalizedName !== '') {
            $candidates[] = $this->themePath . '/' . $normalizedSlug . '-' . $normalizedName . '.php';
        }

        $candidates[] = $this->themePath . '/' . $normalizedSlug . '.php';

        return $candidates;
    }

    private function normalizePathPart(string $value): ?string
    {
        $normalized = trim(str_replace('\\', '/', $value), '/');

        if ($normalized === '' || preg_match('#(^|/)\.\.(/|$)#', $normalized) === 1) {
            return null;
        }

        if (preg_match('#[^A-Za-z0-9/_-]#', $normalized) === 1) {
            return null;
        }

        return $normalized;
    }

    private function normalizeNamePart(string $value): ?string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/[^A-Za-z0-9_-]/', $normalized) === 1) {
            return null;
        }

        return $normalized;
    }

    private function resolveQueryItems(array $data): array
    {
        if (isset($data['posts']) && is_array($data['posts'])) {
            return array_values(array_filter($data['posts'], 'is_array'));
        }

        if (isset($data['post']) && is_array($data['post'])) {
            return [$data['post']];
        }

        if (isset($data['page']) && is_array($data['page'])) {
            return [$data['page']];
        }

        return [];
    }

    private function resolveCurrentItem(array $data, array $queryItems): ?array
    {
        if (isset($data['post']) && is_array($data['post'])) {
            return $data['post'];
        }

        if (isset($data['page']) && is_array($data['page'])) {
            return $data['page'];
        }

        return $queryItems[0] ?? null;
    }

    private static function normalizeClasses(array|string $classes): array
    {
        if (is_string($classes)) {
            $classes = preg_split('/\s+/', trim($classes)) ?: [];
        }

        $normalized = [];

        foreach ($classes as $className) {
            $className = trim((string) $className);

            if ($className === '') {
                continue;
            }

            $normalized[] = self::sanitizeHtmlClass($className);
        }

        return $normalized;
    }

    private static function uniqueClasses(array $classes): array
    {
        $unique = [];

        foreach ($classes as $className) {
            $className = trim((string) $className);

            if ($className === '' || in_array($className, $unique, true)) {
                continue;
            }

            $unique[] = $className;
        }

        return $unique;
    }
}