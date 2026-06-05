<?php
declare(strict_types=1);

/**
 * Local CMS Local Builder theme functions.
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

if (!function_exists('localcms_normalize_term')) {
    /**
     * Normalise a single term into the array shape every template reads.
     *
     * Accepts a local-cms term array or a WordPress WP_Term object. WP_Term
     * exposes its data through magic property access (e.g. $term->name) and does
     * NOT implement ArrayAccess, so reading it with array syntax ($term['name'])
     * fatals with "Cannot use object of type WP_Term as array". This converts
     * either input to a plain array so callers can use array syntax safely.
     *
     * Shape: ['name','slug','taxonomy','content_count'] | null. WordPress's
     * 'post_tag' taxonomy is reported as 'tag' to match the local-cms convention
     * used throughout the templates.
     */
    function localcms_normalize_term(array|object|null $term): ?array
    {
        if ($term === null) {
            return null;
        }

        if (is_array($term)) {
            $taxonomy = (string) ($term['taxonomy'] ?? '');
            $count = $term['content_count'] ?? ($term['count'] ?? 0);

            return [
                'name' => (string) ($term['name'] ?? ''),
                'slug' => (string) ($term['slug'] ?? ''),
                'taxonomy' => $taxonomy === 'post_tag' ? 'tag' : $taxonomy,
                'content_count' => (int) $count,
            ];
        }

        $taxonomy = (string) ($term->taxonomy ?? '');

        return [
            'name' => (string) ($term->name ?? ''),
            'slug' => (string) ($term->slug ?? ''),
            'taxonomy' => $taxonomy === 'post_tag' ? 'tag' : $taxonomy,
            'content_count' => (int) ($term->count ?? 0),
        ];
    }
}

if (!function_exists('theme_term_url')) {
    /**
     * Build a term archive URL from a local-cms term array or a WP_Term object.
     *
     * Under WordPress the canonical archive URL depends on the site's permalink
     * structure, so get_term_link() is used to resolve it correctly (the plain
     * /category/{slug} path only works with pretty permalinks). local-cms serves
     * its own clean /category/ and /tag/ routes, used as the fallback.
     */
    function theme_term_url(array|object $term): string
    {
        $term = localcms_normalize_term($term) ?? [];
        $slug = (string) ($term['slug'] ?? '');
        $taxonomy = (string) ($term['taxonomy'] ?? '');

        if (defined('ABSPATH') && function_exists('get_term_link') && $slug !== '') {
            $wpTaxonomy = $taxonomy === 'tag' ? 'post_tag' : ($taxonomy !== '' ? $taxonomy : 'category');
            $link = get_term_link($slug, $wpTaxonomy);

            if (is_string($link) && $link !== '') {
                return $link;
            }
        }

        $base = $taxonomy === 'category' ? '/category/' : '/tag/';

        return home_url($base . rawurlencode($slug));
    }
}

if (!function_exists('localcms_posts_url')) {
    /**
     * URL of the post listing for the current runtime.
     *
     * local-cms serves the post index at /posts. WordPress has no such route:
     * the listing lives at the page assigned as the "Posts page" under
     * Settings -> Reading, or at the site root when posts are shown on the
     * front page.
     */
    function localcms_posts_url(): string
    {
        if (defined('ABSPATH') && function_exists('get_option')) {
            $postsPage = (int) get_option('page_for_posts');

            if ($postsPage > 0) {
                $url = get_permalink($postsPage);

                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }

            return home_url('/');
        }

        return home_url('/posts');
    }
}

if (!function_exists('localcms_page_url')) {
    /**
     * URL of a content page by slug for the current runtime.
     *
     * local-cms serves pages at /page/{slug}. WordPress resolves the page by
     * path and returns its permalink, falling back to the home page when no page
     * with that slug exists.
     */
    function localcms_page_url(string $slug): string
    {
        $slug = trim($slug, '/');

        if (defined('ABSPATH') && function_exists('get_page_by_path')) {
            $page = get_page_by_path($slug);

            if ($page !== null) {
                $url = get_permalink($page);

                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }

            return home_url('/');
        }

        return home_url('/page/' . $slug);
    }
}

if (!function_exists('localcms_primary_term')) {
    /**
     * Resolve the first term for a content item.
     *
     * Accepts a local-cms array item or a WordPress post object/ID. Always
     * returns a normalised term array (or null) so callers can read it with
     * array syntax in either runtime.
     */
    function localcms_primary_term(array|object|int|null $item = null, string $taxonomy = 'category'): ?array
    {
        if (is_array($item)) {
            $terms = $taxonomy === 'category' ? ($item['categories'] ?? []) : ($item['tags'] ?? []);
            $term = is_array($terms) ? ($terms[0] ?? null) : null;

            return localcms_normalize_term($term);
        }

        $resolvedTaxonomy = defined('ABSPATH') && $taxonomy === 'tag' ? 'post_tag' : $taxonomy;
        $terms = get_the_terms($item ?? get_post(), $resolvedTaxonomy);
        $term = is_array($terms) ? ($terms[0] ?? null) : null;

        return localcms_normalize_term($term);
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

if (!function_exists('localcms_display_excerpt')) {
    /**
     * Resolve the excerpt a template should display, by content type.
     *
     *  - Pages never show an excerpt.
     *  - Posts show an excerpt only when one was explicitly authored.
     *
     * This deliberately avoids WordPress's default behaviour of synthesising an
     * excerpt from the opening paragraph of the body: core get_the_excerpt()
     * trims the content when no manual excerpt exists, which made posts (and
     * pages) echo their own opening paragraph. has_excerpt() distinguishes a
     * real, author-entered excerpt from that auto-generated one. In local-cms
     * the excerpt column is already manual-only, so the stored value is used
     * directly.
     */
    function localcms_display_excerpt(array|object|int|null $item = null, string $kind = ''): string
    {
        if ($kind === '') {
            $kind = get_post_type($item);
        }

        if ($kind === 'page') {
            return '';
        }

        if (defined('ABSPATH')) {
            return has_excerpt($item) ? get_the_excerpt($item) : '';
        }

        return get_the_excerpt($item);
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
            'primary' => __('Primary Navigation', 'local-builder'),
            'footer' => __('Footer Navigation', 'local-builder'),
        ]);
    });

    add_action('widgets_init', static function (): void {
        register_sidebar([
            'name' => __('Primary Sidebar', 'local-builder'),
            'id' => 'sidebar-primary',
            'description' => __('Widgets shown alongside posts in the Local Builder right sidebar.', 'local-builder'),
            'before_widget' => '<section id="%1$s" class="widget %2$s">',
            'after_widget' => '</section>',
            'before_title' => '<h2 class="widget-title">',
            'after_title' => '</h2>',
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
