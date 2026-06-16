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

    /**
     * Header/footer parts already emitted this render, keyed by slug+name.
     *
     * Some ported themes call get_header()/get_footer() more than once in a
     * single render (e.g. front-page.php calls get_header() and then includes
     * home.php, which calls it again). WordPress would re-emit the whole header
     * each time; here that both duplicates the <html>/<head> markup and can fatal
     * when a header partial requires a file that defines unguarded functions. So
     * each header/footer variant is emitted at most once per render.
     */
    private array $emittedParts = [];

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

        // Initialize the $wp_query global that many ported themes reference directly.
        // This must happen after self::$current is set so WP_Query can call ThemeRuntime::data().
        if (class_exists('WP_Query')) {
            $GLOBALS['wp_query'] = new \WP_Query();
            $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
        }
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

    public static function themeAssetUrl(string $path = ''): string
    {
        $basePath = (string) self::data('themeAssetBaseUrl', '/theme');
        $basePath = $basePath === '' ? '/theme' : '/' . trim($basePath, '/');

        if ($path === '') {
            return $basePath;
        }

        return rtrim($basePath, '/') . '/' . ltrim($path, '/');
    }

    public static function themeMediaUrl(string $path = ''): string
    {
        $mediaDirectory = trim((string) self::data('themeMediaDirectory', 'img'), '/');
        $mediaDirectory = $mediaDirectory !== '' ? $mediaDirectory : 'img';

        if ($path === '') {
            return self::themeAssetUrl($mediaDirectory);
        }

        return self::themeAssetUrl($mediaDirectory . '/' . ltrim($path, '/'));
    }

    public static function includeHeader(?string $name = null, array $args = []): void
    {
        self::instance()->includeUniquePart('header', $name, $args);
    }

    public static function includeFooter(?string $name = null, array $args = []): void
    {
        self::instance()->includeUniquePart('footer', $name, $args);
    }

    /**
     * Include a header/footer part at most once per render (see $emittedParts).
     */
    private function includeUniquePart(string $slug, ?string $name, array $args): void
    {
        $key = $slug . ':' . ($name ?? '');

        if (isset($this->emittedParts[$key])) {
            return;
        }

        $this->emittedParts[$key] = true;
        $this->includeThemeFile($slug, $name, $args);
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

        if (self::currentItemUsesClientMarkdown($current)) {
            $markdown = (string) ($current['body_markdown'] ?? '');

            if ($markdown !== '') {
                $attributes = ' data-markdown-body';

                if (self::currentItemUsesMarkedRenderer($current)) {
                    $attributes .= ' data-markdown-renderer="marked"';
                }

                return '<div' . $attributes . '>' . "\n"
                    . htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . "\n" . '</div>';
            }
        }

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

    public static function currentItemUsesMarkdownMath(?array $item = null): bool
    {
        $current = self::currentItem($item);

        return !empty($current['markdown_math']);
    }

    public static function currentItemUsesMarkedRenderer(?array $item = null): bool
    {
        $current = self::currentItem($item);

        return !empty($current['use_marked']);
    }

    public static function currentItemUsesClientMarkdown(?array $item = null): bool
    {
        return self::clientConverterEnabled()
            || self::currentItemUsesMarkedRenderer($item)
            || self::currentItemUsesTemplateMarkdown($item);
    }

    public static function queryUsesClientMarkdown(): bool
    {
        if (self::clientConverterEnabled()) {
            return true;
        }

        foreach (self::instance()->queryItems as $item) {
            if (!empty($item['use_marked']) || self::itemUsesTemplateSyntax($item)) {
                return true;
            }
        }

        return false;
    }

    public static function hasCustomLogo(): bool
    {
        return (string) self::data('customLogoUrl', '') !== '';
    }

    public static function customLogoUrl(): string
    {
        return (string) self::data('customLogoUrl', '');
    }

    public static function clientConverterEnabled(): bool
    {
        return (bool) self::data('clientConverterEnabled', false);
    }

    private static function currentItemUsesTemplateMarkdown(?array $item = null): bool
    {
        return self::itemUsesTemplateSyntax(self::currentItem($item));
    }

    private static function itemUsesTemplateSyntax(?array $item): bool
    {
        if ($item === null || !self::hasMarkdownTemplates()) {
            return false;
        }

        $markdown = (string) ($item['body_markdown'] ?? '');

        return $markdown !== '' && preg_match('/<!--\s*html:(?:template=|begin)\b/i', $markdown) === 1;
    }

    private static function hasMarkdownTemplates(): bool
    {
        return self::data('markdownTemplateMap', []) !== [];
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

    /**
     * Whether the active theme was authored for Local CMS (native) or ported from WordPress.
     *
     * Reads the global seeded by Theme::loadFunctions() before functions.php runs.
     * Templates and compat shims can use this to branch on ported-vs-native behavior.
     */
    public static function isNativeTheme(): bool
    {
        return (bool) ($GLOBALS['localcms_theme_native'] ?? false);
    }
}