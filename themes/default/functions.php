<?php
declare(strict_types=1);

/**
 * Local CMS Default theme functions.
 *
 * This file is shared between two runtimes:
 *
 *  - Local CMS, which preloads src/Support/WordPressCompat.php (defining the
 *    template tags and localcms_* / theme_* helpers) before this file.
 *  - A stock WordPress install, where the theme is imported directly and only
 *    WordPress core is available.
 *
 * Every definition below is guarded with function_exists(), so the local-cms
 * runtime keeps its own implementations untouched while WordPress receives
 * lightweight, dependency-free fallbacks. Behaviour that only makes sense under
 * WordPress is gated on defined('ABSPATH'), which is always defined by core and
 * never by local-cms.
 */

if (!function_exists('theme_format_date')) {
    function theme_format_date(?string $dateTime): string
    {
        if ($dateTime === null || $dateTime === '') {
            return '';
        }

        $date = new DateTimeImmutable($dateTime);

        return $date->format('M j, Y');
    }
}

if (!function_exists('theme_term_url')) {
    /**
     * Build a term archive URL from a local-cms term array or a WP_Term object
     * (WP_Term supports array access, so the same read syntax works for both).
     */
    function theme_term_url(array|object $term): string
    {
        $base = ($term['taxonomy'] ?? '') === 'category' ? '/category/' : '/tag/';

        return home_url($base . rawurlencode((string) ($term['slug'] ?? '')));
    }
}

if (!function_exists('localcms_primary_term')) {
    /**
     * Resolve the first term for a content item.
     *
     * Accepts a local-cms array item or a WordPress post object/ID. Returns the
     * raw term (array in local-cms, WP_Term in WordPress) so callers can read it
     * with array syntax in either runtime.
     */
    function localcms_primary_term(array|object|int|null $item = null, string $taxonomy = 'category'): array|object|null
    {
        if (is_array($item)) {
            $terms = $taxonomy === 'category' ? ($item['categories'] ?? []) : ($item['tags'] ?? []);

            return is_array($terms) ? ($terms[0] ?? null) : null;
        }

        $resolvedTaxonomy = defined('ABSPATH') && $taxonomy === 'tag' ? 'post_tag' : $taxonomy;
        $terms = get_the_terms($item ?? get_post(), $resolvedTaxonomy);

        return is_array($terms) ? ($terms[0] ?? null) : null;
    }
}

if (!function_exists('localcms_reading_minutes')) {
    /**
     * Estimate reading time in minutes for a content item.
     *
     * Uses the local-cms precomputed value when present, otherwise estimates
     * from the WordPress post body at ~200 words per minute.
     */
    function localcms_reading_minutes(array|object|int|null $item = null): int
    {
        $current = $item ?? get_post();

        if (is_array($current)) {
            return max(1, (int) ($current['reading_minutes'] ?? 1));
        }

        if ($current === null || !function_exists('get_the_content')) {
            return 1;
        }

        $content = function_exists('wp_strip_all_tags')
            ? wp_strip_all_tags(get_the_content(null, false, $current))
            : strip_tags(get_the_content(null, false, $current));

        return max(1, (int) ceil(str_word_count($content) / 200));
    }
}

/*
 * --------------------------------------------------------------------------
 * WordPress fallbacks for local-cms helpers
 * --------------------------------------------------------------------------
 * These are only defined when running outside local-cms (i.e. inside real
 * WordPress, where WordPressCompat.php was never loaded). They mirror the
 * intent of Cms\Support\Markdown and Cms\Core\ThemeRuntime closely enough for
 * the theme to render without fatals.
 */

if (!function_exists('localcms_render_compact_markdown')) {
    /**
     * Render a single line of "compact" inline Markdown to safe HTML.
     *
     * Supports the same inline subset the local-cms renderer uses for titles
     * and labels: code spans, bold, italic, bold-italic and strikethrough.
     */
    function localcms_render_compact_markdown(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', str_replace(["\r\n", "\n", "\r"], ' ', $text)) ?? $text);

        if ($text === '') {
            return '';
        }

        // Protect code spans before escaping so backtick contents stay literal.
        $placeholders = [];
        $text = preg_replace_callback('/`([^`\n]+)`/', static function (array $matches) use (&$placeholders): string {
            $key = "\x00md" . count($placeholders) . "\x00";
            $placeholders[$key] = '<code>' . esc_html($matches[1]) . '</code>';

            return $key;
        }, $text) ?? $text;

        $text = esc_html($text);

        $patterns = [
            '/\*\*\*([^*][\s\S]*?)\*\*\*/' => '<strong><em>$1</em></strong>',
            '/___([^_][\s\S]*?)___/' => '<strong><em>$1</em></strong>',
            '/\*\*([^*][\s\S]*?)\*\*/' => '<strong>$1</strong>',
            '/__([^_][\s\S]*?)__/' => '<strong>$1</strong>',
            '/~~([^~][\s\S]*?)~~/' => '<del>$1</del>',
            '/(?<!\*)\*([^*\n]+)\*(?!\*)/' => '<em>$1</em>',
            '/(?<!_)_([^_\n]+)_(?!_)/' => '<em>$1</em>',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return strtr($text, $placeholders);
    }
}

if (!function_exists('localcms_compact_markdown_text')) {
    /**
     * Reduce a line of compact Markdown to escaped-safe plain text.
     */
    function localcms_compact_markdown_text(string $text): string
    {
        $plain = html_entity_decode(
            strip_tags(localcms_render_compact_markdown($text)),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        return trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);
    }
}

if (!function_exists('theme_asset_url')) {
    function theme_asset_url(string $path = ''): string
    {
        $relative = ltrim($path, '/');

        if (function_exists('get_theme_file_uri')) {
            return get_theme_file_uri($relative);
        }

        return '/' . $relative;
    }
}

if (!function_exists('theme_media_url')) {
    function theme_media_url(string $path = ''): string
    {
        $mediaDirectory = 'img';

        return theme_asset_url($path === '' ? $mediaDirectory : $mediaDirectory . '/' . ltrim($path, '/'));
    }
}

/*
 * --------------------------------------------------------------------------
 * WordPress theme integration
 * --------------------------------------------------------------------------
 * Only registered when WordPress is actually present. These give the imported
 * theme sensible defaults (logo, thumbnails, menu location) and supply the
 * navigation/term data that local-cms would otherwise inject into the views.
 */

if (defined('ABSPATH') && function_exists('add_action')) {
    add_action('after_setup_theme', static function (): void {
        add_theme_support('automatic-feed-links');
        add_theme_support('post-thumbnails');
        add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets']);
        add_theme_support('custom-logo', [
            'height' => 80,
            'width' => 240,
            'flex-height' => true,
            'flex-width' => true,
        ]);

        register_nav_menus([
            'primary' => __('Primary Navigation', 'local-cms'),
        ]);
    });
}

if (!function_exists('localcms_theme_navigation')) {
    /**
     * Build the navigation array the header and footer expect.
     *
     * Shape: [['label' => string, 'url' => string, 'active' => bool], ...]
     * where 'url' is a home-relative path suitable for home_url().
     *
     * Prefers a menu assigned to the 'primary' location, then falls back to the
     * site's top-level pages plus a Home link.
     */
    function localcms_theme_navigation(): array
    {
        if (!defined('ABSPATH') || !function_exists('wp_get_nav_menu_items')) {
            return [];
        }

        $currentId = function_exists('get_queried_object_id') ? get_queried_object_id() : 0;
        $items = [];

        $locations = get_nav_menu_locations();

        if (!empty($locations['primary'])) {
            $menuItems = wp_get_nav_menu_items($locations['primary']);

            if (is_array($menuItems)) {
                foreach ($menuItems as $menuItem) {
                    if ((int) $menuItem->menu_item_parent !== 0) {
                        continue;
                    }

                    $items[] = [
                        'label' => (string) $menuItem->title,
                        'url' => wp_make_link_relative((string) $menuItem->url),
                        'active' => $currentId !== 0 && (int) $menuItem->object_id === $currentId,
                    ];
                }
            }
        }

        if ($items === []) {
            $items[] = [
                'label' => get_bloginfo('name'),
                'url' => '/',
                'active' => is_front_page(),
            ];

            $pages = get_pages([
                'parent' => 0,
                'sort_column' => 'menu_order,post_title',
                'number' => 6,
            ]);

            foreach (is_array($pages) ? $pages : [] as $page) {
                $items[] = [
                    'label' => get_the_title($page),
                    'url' => wp_make_link_relative(get_permalink($page)),
                    'active' => $currentId !== 0 && (int) $page->ID === $currentId,
                ];
            }
        }

        return $items;
    }
}

if (!function_exists('localcms_theme_terms')) {
    /**
     * Return categories in the array shape the templates render.
     *
     * Shape: [['name','slug','taxonomy','content_count'], ...]
     */
    function localcms_theme_terms(): array
    {
        if (!defined('ABSPATH') || !function_exists('get_categories')) {
            return [];
        }

        $terms = [];

        foreach (get_categories(['hide_empty' => false, 'number' => 8]) as $category) {
            $terms[] = [
                'name' => $category->name,
                'slug' => $category->slug,
                'taxonomy' => 'category',
                'content_count' => (int) $category->count,
            ];
        }

        return $terms;
    }
}

if (!function_exists('localcms_theme_archive_term')) {
    /**
     * Describe the currently queried archive term, if any.
     *
     * Shape: ['name','slug','taxonomy'] | null. The taxonomy is normalised so
     * 'post_tag' is reported as 'tag', matching the local-cms convention used
     * throughout the templates.
     */
    function localcms_theme_archive_term(): ?array
    {
        if (!defined('ABSPATH') || !function_exists('get_queried_object')) {
            return null;
        }

        $queried = get_queried_object();

        if (!$queried instanceof WP_Term) {
            return null;
        }

        return [
            'name' => $queried->name,
            'slug' => $queried->slug,
            'taxonomy' => $queried->taxonomy === 'post_tag' ? 'tag' : $queried->taxonomy,
        ];
    }
}
