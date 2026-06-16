<?php
declare(strict_types=1);

use Cms\Core\ThemeRuntime;
use Cms\Support\Markdown;

if (!function_exists('esc_html')) {
    // Loosely typed on purpose: ported themes pass null / numbers / Stringable
    // values into the escapers, and WordPress core casts rather than fataling.
    function esc_html($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url): string
    {
        return htmlspecialchars((string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('sanitize_html_class')) {
    function sanitize_html_class(string $className): string
    {
        return ThemeRuntime::sanitizeHtmlClass($className);
    }
}

if (!function_exists('get_header')) {
    function get_header(?string $name = null, array $args = []): void
    {
        ThemeRuntime::includeHeader($name, $args);
    }
}

if (!function_exists('get_footer')) {
    function get_footer(?string $name = null, array $args = []): void
    {
        ThemeRuntime::includeFooter($name, $args);
    }
}

if (!function_exists('get_template_part')) {
    function get_template_part(string $slug, ?string $name = null, array $args = []): void
    {
        ThemeRuntime::includeTemplatePart($slug, $name, $args);
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = ''): string
    {
        return ThemeRuntime::bloginfo($show);
    }
}

if (!function_exists('bloginfo')) {
    function bloginfo(string $show = ''): void
    {
        echo esc_html(get_bloginfo($show));
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return ThemeRuntime::homeUrl($path);
    }
}

if (!function_exists('localcms_render_compact_markdown')) {
    function localcms_render_compact_markdown(string $text): string
    {
        return Markdown::toCompactInlineHtml($text);
    }
}

if (!function_exists('localcms_compact_markdown_text')) {
    function localcms_compact_markdown_text(string $text): string
    {
        return Markdown::toCompactInlineText($text);
    }
}

if (!function_exists('theme_asset_url')) {
    function theme_asset_url(string $path = ''): string
    {
        return ThemeRuntime::themeAssetUrl($path);
    }
}

if (!function_exists('theme_media_url')) {
    function theme_media_url(string $path = ''): string
    {
        return ThemeRuntime::themeMediaUrl($path);
    }
}

if (!function_exists('language_attributes')) {
    function language_attributes(): void
    {
        echo 'lang="' . esc_attr(get_bloginfo('language')) . '"';
    }
}

if (!function_exists('body_class')) {
    function body_class(array|string $classes = []): void
    {
        echo 'class="' . esc_attr(implode(' ', ThemeRuntime::bodyClasses($classes))) . '"';
    }
}

if (!function_exists('post_class')) {
    function post_class(array|string $classes = [], ?array $post = null): void
    {
        echo 'class="' . esc_attr(implode(' ', ThemeRuntime::postClasses($classes, $post))) . '"';
    }
}

if (!function_exists('wp_head')) {
    function wp_head(): void
    {
        // Emit the theme stylesheet so ported WP themes (which rely on wp_head() for
        // their enqueued styles) load their own CSS rather than rendering unstyled.
        $stylesheetUrl = '';
        try {
            $stylesheetUrl = (string) ThemeRuntime::data('stylesheetUrl', '/theme/style.css');
        } catch (\Throwable) {
            $stylesheetUrl = '/theme/style.css';
        }
        if ($stylesheetUrl !== '') {
            echo '<link rel="stylesheet" href="' . esc_url($stylesheetUrl) . '">' . "\n";
        }

        // Emit the page title, since WP themes that declare add_theme_support('title-tag')
        // rely on wp_head() to output <title> rather than hard-coding it in the template.
        $pageTitle = '';
        try {
            $pageTitle = (string) ThemeRuntime::data('pageTitle', '');
        } catch (\Throwable) {}
        if ($pageTitle !== '') {
            echo '<title>' . esc_html($pageTitle) . '</title>' . "\n";
        }

        if (ThemeRuntime::queryUsesClientMarkdown()) {
            echo '<link rel="stylesheet" href="/assets/markdown.css">' . "\n";
        }

        if (ThemeRuntime::currentItemUsesMarkdownMath()) {
            echo <<<'HTML'
<script>
        MathJax = {
            tex: {
                inlineMath: [['\\(', '\\)']],
                displayMath: [['\\[', '\\]']]
            },
            options: {
                skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code']
            }
        };
</script>
<script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
HTML;
        }
    }
}

if (!function_exists('wp_footer')) {
    function wp_footer(): void
    {
        if (ThemeRuntime::queryUsesClientMarkdown()) {
            echo '<script>window.LocalCmsMarkdownTemplates = ' . json_encode(
                ThemeRuntime::data('markdownTemplateMap', []),
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
            ) . ';</script>' . "\n";
            echo '<script src="/assets/convert.js"></script>' . "\n";
        }
    }
}

if (!function_exists('wp_body_open')) {
    function wp_body_open(): void
    {
    }
}

if (!function_exists('have_posts')) {
    function have_posts(): bool
    {
        return ThemeRuntime::havePosts();
    }
}

if (!function_exists('the_post')) {
    function the_post(): ?array
    {
        return ThemeRuntime::thePost();
    }
}

if (!function_exists('rewind_posts')) {
    function rewind_posts(): void
    {
        ThemeRuntime::rewindPosts();
    }
}

if (!function_exists('get_post')) {
    function get_post(?array $post = null): ?array
    {
        return ThemeRuntime::currentItem($post);
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type(?array $post = null): string
    {
        return ThemeRuntime::getPostType($post);
    }
}

if (!function_exists('get_the_title')) {
    // WordPress' get_the_title() takes a post ID/object; ported themes also call
    // it bare. Accept anything and only honour an array (a Local CMS item).
    function get_the_title($post = null): string
    {
        return ThemeRuntime::getTitle(is_array($post) ? $post : null);
    }
}

if (!function_exists('the_title')) {
    // Signature mirrors WordPress: the_title($before, $after, $display). The
    // third argument is a display flag there, not a post, so it is ignored.
    function the_title($before = '', $after = '', $display = true): void
    {
        echo (string) $before . localcms_render_compact_markdown(get_the_title()) . (string) $after;
    }
}

if (!function_exists('get_the_excerpt')) {
    function get_the_excerpt($post = null): string
    {
        return ThemeRuntime::getExcerpt(is_array($post) ? $post : null);
    }
}

if (!function_exists('the_excerpt')) {
    function the_excerpt($post = null): void
    {
        echo esc_html(get_the_excerpt(is_array($post) ? $post : null));
    }
}

if (!function_exists('get_the_content')) {
    // WordPress: get_the_content($more_link_text, $strip_teaser, $post). The
    // first two are display options here; only an array $post is meaningful.
    function get_the_content($more_link_text = null, $strip_teaser = false, $post = null): string
    {
        return ThemeRuntime::getContent(is_array($post) ? $post : null);
    }
}

if (!function_exists('the_content')) {
    // WordPress: the_content($more_link_text, $strip_teaser) — both strings/bool.
    function the_content($more_link_text = null, $strip_teaser = false): void
    {
        echo get_the_content();
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post = null): string
    {
        return ThemeRuntime::getPermalink(is_array($post) ? $post : null);
    }
}

if (!function_exists('the_permalink')) {
    function the_permalink($post = null): void
    {
        echo esc_url(get_permalink(is_array($post) ? $post : null));
    }
}

if (!function_exists('get_the_date')) {
    function get_the_date($format = 'M j, Y', $post = null): string
    {
        return ThemeRuntime::getDate(is_string($format) && $format !== '' ? $format : 'M j, Y', is_array($post) ? $post : null);
    }
}

if (!function_exists('the_date')) {
    function the_date($format = 'M j, Y', $before = '', $after = '', $echo = true): void
    {
        echo (string) $before . esc_html(get_the_date($format)) . (string) $after;
    }
}

if (!function_exists('get_the_author')) {
    function get_the_author($post = null): string
    {
        return ThemeRuntime::getAuthorName(is_array($post) ? $post : null);
    }
}

if (!function_exists('the_author')) {
    function the_author($post = null): void
    {
        echo esc_html(get_the_author(is_array($post) ? $post : null));
    }
}

if (!function_exists('get_the_terms')) {
    function get_the_terms($post, $taxonomy = '')
    {
        return ThemeRuntime::getTerms((string) $taxonomy, is_array($post) ? $post : null);
    }
}

if (!function_exists('has_post_thumbnail')) {
    function has_post_thumbnail($post = null): bool
    {
        return ThemeRuntime::hasPostThumbnail(is_array($post) ? $post : null);
    }
}

if (!function_exists('get_the_post_thumbnail_url')) {
    function get_the_post_thumbnail_url($post = null, $size = 'post-thumbnail')
    {
        return ThemeRuntime::getPostThumbnailUrl(is_array($post) ? $post : null);
    }
}

if (!function_exists('is_front_page')) {
    function is_front_page(): bool
    {
        return ThemeRuntime::isFrontPage();
    }
}

if (!function_exists('is_home')) {
    function is_home(): bool
    {
        return ThemeRuntime::isHome();
    }
}

if (!function_exists('is_archive')) {
    function is_archive(): bool
    {
        return ThemeRuntime::isArchive();
    }
}

if (!function_exists('is_single')) {
    function is_single(): bool
    {
        return ThemeRuntime::isSingle();
    }
}

if (!function_exists('is_page')) {
    function is_page(): bool
    {
        return ThemeRuntime::isPage();
    }
}

if (!function_exists('is_singular')) {
    function is_singular(): bool
    {
        return ThemeRuntime::isSingular();
    }
}

if (!function_exists('is_category')) {
    function is_category(): bool
    {
        return ThemeRuntime::isCategory();
    }
}

if (!function_exists('is_tag')) {
    function is_tag(): bool
    {
        return ThemeRuntime::isTag();
    }
}

if (!function_exists('is_paged')) {
    function is_paged(): bool
    {
        return false;
    }
}

if (!function_exists('is_search')) {
    function is_search(): bool
    {
        return false;
    }
}

if (!function_exists('is_404')) {
    function is_404(): bool
    {
        return false;
    }
}

if (!function_exists('is_sticky')) {
    function is_sticky($post = 0): bool
    {
        return false;
    }
}

if (!function_exists('is_tax')) {
    function is_tax($taxonomy = '', $term = ''): bool
    {
        return false;
    }
}

if (!function_exists('is_author')) {
    function is_author($author = ''): bool
    {
        return false;
    }
}

if (!function_exists('is_date')) {
    function is_date(): bool
    {
        return false;
    }
}

if (!function_exists('is_year')) {
    function is_year(): bool
    {
        return false;
    }
}

if (!function_exists('is_month')) {
    function is_month(): bool
    {
        return false;
    }
}

if (!function_exists('is_day')) {
    function is_day(): bool
    {
        return false;
    }
}

if (!function_exists('is_attachment')) {
    function is_attachment($attachment = ''): bool
    {
        return false;
    }
}

if (!function_exists('is_preview')) {
    function is_preview(): bool
    {
        return false;
    }
}

if (!function_exists('is_feed')) {
    function is_feed($feeds = ''): bool
    {
        return false;
    }
}

if (!function_exists('is_post_type_archive')) {
    function is_post_type_archive($post_types = ''): bool
    {
        return false;
    }
}

if (!function_exists('is_page_template')) {
    function is_page_template($template = ''): bool
    {
        return false;
    }
}

if (!function_exists('in_the_loop')) {
    function in_the_loop(): bool
    {
        return false;
    }
}

if (!function_exists('get_the_archive_title')) {
    function get_the_archive_title(): string
    {
        return ThemeRuntime::archiveTitle();
    }
}

if (!function_exists('the_archive_title')) {
    function the_archive_title(string $before = '', string $after = ''): void
    {
        echo $before . esc_html(get_the_archive_title()) . $after;
    }
}

if (!function_exists('has_custom_logo')) {
    function has_custom_logo(): bool
    {
        return ThemeRuntime::hasCustomLogo();
    }
}

if (!function_exists('get_custom_logo')) {
    function get_custom_logo(): string
    {
        $url = ThemeRuntime::customLogoUrl();

        if ($url === '') {
            return '';
        }

        return '<img class="custom-logo" src="' . esc_url($url) . '" alt="' . esc_attr(get_bloginfo('name')) . '">';
    }
}

if (!function_exists('the_custom_logo')) {
    function the_custom_logo(): void
    {
        echo get_custom_logo();
    }
}

if (!function_exists('get_the_archive_description')) {
    function get_the_archive_description(): string
    {
        return ThemeRuntime::archiveDescription();
    }
}

if (!function_exists('the_archive_description')) {
    function the_archive_description(string $before = '', string $after = ''): void
    {
        echo $before . esc_html(get_the_archive_description()) . $after;
    }
}

/*
 * ---------------------------------------------------------------------------
 * Stock WordPress theme compatibility shims.
 *
 * The tags above cover templates authored for the local runtime. Porting a
 * stock WordPress theme into the runtime (see port-cms/local-cms) needs a wider
 * — but deliberately inert — surface: a classic functions.php registers menus,
 * theme supports, block styles, and enqueues assets through core APIs that have
 * no meaning here. Shimming them as safe no-ops lets such a theme load and its
 * templates render instead of fataling on the first undefined call.
 *
 * Everything below is additive and guarded by function_exists()/isset(), so
 * themes that already run in the runtime are untouched. Setup-time shims (hooks,
 * i18n, directories, enqueues) must not depend on ThemeRuntime, which is not
 * booted while functions.php is being included.
 * ---------------------------------------------------------------------------
 */

// A stock theme commonly version-guards on the core release; advertise a modern
// one so those branches resolve, and provide the global content width default.
$GLOBALS['wp_version'] ??= '6.7.0';
$GLOBALS['content_width'] ??= 1160;

// --- Internationalisation: return/echo the text unchanged. ---------------- //
if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return $text;
    }
}
if (!function_exists('_x')) {
    function _x($text, $context, $domain = 'default')
    {
        return $text;
    }
}
if (!function_exists('_n')) {
    function _n($single, $plural, $number, $domain = 'default')
    {
        return (int) $number === 1 ? $single : $plural;
    }
}
if (!function_exists('_nx')) {
    function _nx($single, $plural, $number, $context, $domain = 'default')
    {
        return (int) $number === 1 ? $single : $plural;
    }
}
if (!function_exists('translate')) {
    function translate($text, $domain = 'default')
    {
        return $text;
    }
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default')
    {
        return esc_html((string) $text);
    }
}
if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = 'default')
    {
        return esc_attr((string) $text);
    }
}
if (!function_exists('esc_html_x')) {
    function esc_html_x($text, $context, $domain = 'default')
    {
        return esc_html((string) $text);
    }
}
if (!function_exists('esc_attr_x')) {
    function esc_attr_x($text, $context, $domain = 'default')
    {
        return esc_attr((string) $text);
    }
}
if (!function_exists('_e')) {
    function _e($text, $domain = 'default')
    {
        echo $text;
    }
}
if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default')
    {
        echo esc_html((string) $text);
    }
}
if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default')
    {
        echo esc_attr((string) $text);
    }
}
if (!function_exists('__return_true')) {
    function __return_true()
    {
        return true;
    }
}
if (!function_exists('__return_false')) {
    function __return_false()
    {
        return false;
    }
}
if (!function_exists('__return_null')) {
    function __return_null()
    {
        return null;
    }
}
if (!function_exists('__return_zero')) {
    function __return_zero()
    {
        return 0;
    }
}
if (!function_exists('__return_empty_string')) {
    function __return_empty_string()
    {
        return '';
    }
}
if (!function_exists('__return_empty_array')) {
    function __return_empty_array()
    {
        return [];
    }
}

// --- Hooks: register nothing, but stay call-compatible. ------------------- //
if (!function_exists('add_action')) {
    function add_action($hook, $callback = null, $priority = 10, $accepted_args = 1)
    {
        return true;
    }
}
if (!function_exists('add_filter')) {
    function add_filter($hook, $callback = null, $priority = 10, $accepted_args = 1)
    {
        return true;
    }
}
if (!function_exists('remove_action')) {
    function remove_action($hook, $callback = null, $priority = 10)
    {
        return true;
    }
}
if (!function_exists('remove_filter')) {
    function remove_filter($hook, $callback = null, $priority = 10)
    {
        return true;
    }
}
if (!function_exists('do_action')) {
    function do_action($hook, ...$args)
    {
    }
}
if (!function_exists('do_action_ref_array')) {
    function do_action_ref_array($hook, $args = [])
    {
    }
}
if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value = null, ...$args)
    {
        return $value;
    }
}
if (!function_exists('apply_filters_ref_array')) {
    function apply_filters_ref_array($hook, $args = [])
    {
        return is_array($args) ? ($args[0] ?? null) : $args;
    }
}
if (!function_exists('has_action')) {
    function has_action($hook, $callback = false)
    {
        return false;
    }
}
if (!function_exists('has_filter')) {
    function has_filter($hook, $callback = false)
    {
        return false;
    }
}
if (!function_exists('did_action')) {
    function did_action($hook)
    {
        return 0;
    }
}
if (!function_exists('doing_action')) {
    function doing_action($hook = null)
    {
        return false;
    }
}
if (!function_exists('doing_filter')) {
    function doing_filter($hook = null)
    {
        return false;
    }
}
if (!function_exists('current_action')) {
    function current_action()
    {
        return '';
    }
}
if (!function_exists('current_filter')) {
    function current_filter()
    {
        return '';
    }
}
if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback)
    {
        return true;
    }
}
if (!function_exists('do_shortcode')) {
    function do_shortcode($content)
    {
        return $content;
    }
}

// --- Theme setup: accept and ignore. ------------------------------------- //
if (!function_exists('add_theme_support')) {
    function add_theme_support($feature, ...$args)
    {
        return true;
    }
}
if (!function_exists('remove_theme_support')) {
    function remove_theme_support($feature)
    {
        return false;
    }
}
if (!function_exists('current_theme_supports')) {
    function current_theme_supports($feature, ...$args)
    {
        return false;
    }
}
if (!function_exists('get_theme_support')) {
    function get_theme_support($feature, ...$args)
    {
        return false;
    }
}
if (!function_exists('register_nav_menus')) {
    function register_nav_menus($locations = [])
    {
    }
}
if (!function_exists('register_nav_menu')) {
    function register_nav_menu($location, $description = '')
    {
    }
}
if (!function_exists('has_nav_menu')) {
    function has_nav_menu($location)
    {
        // Return true when ThemeRuntime is booted and navigation data exists, so
        // ported WP theme templates render the navigation block rather than skip it.
        try {
            $nav = ThemeRuntime::data('navigation', []);
            return is_array($nav) && $nav !== [];
        } catch (\Throwable) {
            return false;
        }
    }
}
if (!function_exists('set_post_thumbnail_size')) {
    function set_post_thumbnail_size($width = 0, $height = 0, $crop = false)
    {
    }
}
if (!function_exists('add_image_size')) {
    function add_image_size($name, $width = 0, $height = 0, $crop = false)
    {
    }
}
if (!function_exists('add_editor_style')) {
    function add_editor_style($stylesheet = 'editor-style.css')
    {
    }
}
if (!function_exists('register_sidebar')) {
    function register_sidebar($args = [])
    {
        return is_array($args) ? (string) ($args['id'] ?? 'sidebar-1') : 'sidebar-1';
    }
}
if (!function_exists('register_sidebars')) {
    function register_sidebars($number = 1, $args = [])
    {
    }
}
if (!function_exists('unregister_sidebar')) {
    function unregister_sidebar($id)
    {
    }
}
if (!function_exists('register_block_style')) {
    function register_block_style($block_name, $style_properties = [])
    {
        return true;
    }
}
if (!function_exists('unregister_block_style')) {
    function unregister_block_style($block_name, $block_style_name)
    {
        return true;
    }
}
if (!function_exists('register_block_pattern')) {
    function register_block_pattern($pattern_name, $pattern_properties = [])
    {
        return true;
    }
}
if (!function_exists('unregister_block_pattern')) {
    function unregister_block_pattern($pattern_name)
    {
        return true;
    }
}
if (!function_exists('register_block_pattern_category')) {
    function register_block_pattern_category($category_name, $category_properties = [])
    {
        return true;
    }
}
if (!function_exists('unregister_block_pattern_category')) {
    function unregister_block_pattern_category($category_name)
    {
        return true;
    }
}
if (!function_exists('register_block_type')) {
    function register_block_type($block_type, $args = [])
    {
        return null;
    }
}
if (!function_exists('add_post_type_support')) {
    function add_post_type_support($post_type, $feature, ...$args)
    {
    }
}
if (!function_exists('load_theme_textdomain')) {
    function load_theme_textdomain($domain, $path = false)
    {
        return true;
    }
}
if (!function_exists('load_child_theme_textdomain')) {
    function load_child_theme_textdomain($domain, $path = false)
    {
        return true;
    }
}

// --- Theme directories and asset URLs. ----------------------------------- //
// Theme.php seeds $GLOBALS['localcms_theme_directory'] / ['localcms_theme_uri']
// before including functions.php so these resolve at setup time (when
// ThemeRuntime is not yet booted).
if (!function_exists('get_template_directory')) {
    function get_template_directory()
    {
        return (string) ($GLOBALS['localcms_theme_directory'] ?? getcwd());
    }
}
if (!function_exists('get_stylesheet_directory')) {
    function get_stylesheet_directory()
    {
        return get_template_directory();
    }
}
if (!function_exists('get_template_directory_uri')) {
    function get_template_directory_uri()
    {
        return (string) ($GLOBALS['localcms_theme_uri'] ?? '/theme');
    }
}
if (!function_exists('get_stylesheet_directory_uri')) {
    function get_stylesheet_directory_uri()
    {
        return get_template_directory_uri();
    }
}
if (!function_exists('get_theme_file_uri')) {
    function get_theme_file_uri($file = '')
    {
        $base = get_template_directory_uri();
        return $file === '' ? $base : rtrim($base, '/') . '/' . ltrim((string) $file, '/');
    }
}
if (!function_exists('get_parent_theme_file_uri')) {
    function get_parent_theme_file_uri($file = '')
    {
        return get_theme_file_uri($file);
    }
}
if (!function_exists('get_theme_file_path')) {
    function get_theme_file_path($file = '')
    {
        $base = get_template_directory();
        return $file === '' ? $base : rtrim($base, '/\\') . '/' . ltrim((string) $file, '/');
    }
}
if (!function_exists('get_parent_theme_file_path')) {
    function get_parent_theme_file_path($file = '')
    {
        return get_theme_file_path($file);
    }
}
if (!function_exists('get_stylesheet')) {
    function get_stylesheet()
    {
        return basename(get_template_directory());
    }
}
if (!function_exists('get_template')) {
    function get_template()
    {
        return basename(get_template_directory());
    }
}

// --- Asset enqueueing: no asset pipeline here, so accept and ignore. ------ //
if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(...$args)
    {
    }
}
if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(...$args)
    {
    }
}
if (!function_exists('wp_register_style')) {
    function wp_register_style(...$args)
    {
        return true;
    }
}
if (!function_exists('wp_register_script')) {
    function wp_register_script(...$args)
    {
        return true;
    }
}
if (!function_exists('wp_deregister_style')) {
    function wp_deregister_style(...$args)
    {
    }
}
if (!function_exists('wp_deregister_script')) {
    function wp_deregister_script(...$args)
    {
    }
}
if (!function_exists('wp_dequeue_style')) {
    function wp_dequeue_style(...$args)
    {
    }
}
if (!function_exists('wp_dequeue_script')) {
    function wp_dequeue_script(...$args)
    {
    }
}
if (!function_exists('wp_style_add_data')) {
    function wp_style_add_data(...$args)
    {
        return true;
    }
}
if (!function_exists('wp_script_add_data')) {
    function wp_script_add_data(...$args)
    {
        return true;
    }
}
if (!function_exists('wp_add_inline_style')) {
    function wp_add_inline_style(...$args)
    {
        return true;
    }
}
if (!function_exists('wp_add_inline_script')) {
    function wp_add_inline_script(...$args)
    {
        return true;
    }
}
if (!function_exists('wp_localize_script')) {
    function wp_localize_script(...$args)
    {
        return true;
    }
}
if (!function_exists('wp_set_script_translations')) {
    function wp_set_script_translations(...$args)
    {
        return true;
    }
}

// --- Options, theme mods, environment. ----------------------------------- //
if (!function_exists('get_option')) {
    function get_option($option, $default_value = false)
    {
        return $default_value;
    }
}
if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null)
    {
        return true;
    }
}
if (!function_exists('get_theme_mod')) {
    function get_theme_mod($name, $default_value = false)
    {
        return $default_value;
    }
}
if (!function_exists('set_theme_mod')) {
    function set_theme_mod($name, $value)
    {
        return true;
    }
}
if (!function_exists('get_theme_mods')) {
    function get_theme_mods()
    {
        return [];
    }
}
if (!function_exists('is_customize_preview')) {
    function is_customize_preview()
    {
        return false;
    }
}
if (!function_exists('is_admin')) {
    function is_admin()
    {
        return false;
    }
}
if (!function_exists('current_user_can')) {
    function current_user_can(...$args)
    {
        return false;
    }
}
if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in()
    {
        return false;
    }
}
if (!function_exists('get_current_user_id')) {
    function get_current_user_id()
    {
        return 0;
    }
}
if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user()
    {
        return null;
    }
}
if (!function_exists('admin_url')) {
    function admin_url($path = '', $scheme = 'admin')
    {
        return '/' . ltrim((string) $path, '/');
    }
}
if (!function_exists('wp_login_url')) {
    function wp_login_url($redirect = '', $force_reauth = false)
    {
        return '/login';
    }
}
if (!function_exists('wp_logout_url')) {
    function wp_logout_url($redirect = '')
    {
        return '/logout';
    }
}
if (!function_exists('wp_registration_url')) {
    function wp_registration_url()
    {
        return '/register';
    }
}
if (!function_exists('get_edit_post_link')) {
    function get_edit_post_link($id = 0, $context = 'display')
    {
        return '';
    }
}
if (!function_exists('wp_is_block_theme')) {
    function wp_is_block_theme()
    {
        return false;
    }
}
if (!function_exists('wp_get_environment_type')) {
    function wp_get_environment_type()
    {
        return 'production';
    }
}
if (!function_exists('is_rtl')) {
    function is_rtl()
    {
        return false;
    }
}
if (!function_exists('comments_open')) {
    function comments_open($post = null)
    {
        return false;
    }
}
if (!function_exists('pings_open')) {
    function pings_open($post = null)
    {
        return false;
    }
}
if (!function_exists('post_password_required')) {
    function post_password_required($post = null)
    {
        return false;
    }
}
if (!function_exists('is_active_sidebar')) {
    function is_active_sidebar($index)
    {
        return false;
    }
}
if (!function_exists('dynamic_sidebar')) {
    function dynamic_sidebar($index = 1)
    {
        return false;
    }
}

// --- Small value helpers stock templates lean on. ------------------------ //
if (!function_exists('absint')) {
    function absint($maybeint)
    {
        return abs((int) $maybeint);
    }
}
if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = [])
    {
        if (is_object($args)) {
            $args = get_object_vars($args);
        }
        if (is_string($args)) {
            parse_str($args, $args);
        }
        return is_array($args) ? array_merge($defaults, $args) : $defaults;
    }
}
if (!function_exists('selected')) {
    function selected($selected, $current = true, $echo = true)
    {
        $result = ((string) $selected === (string) $current) ? " selected='selected'" : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}
if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true)
    {
        $result = ((string) $checked === (string) $current) ? " checked='checked'" : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}
if (!function_exists('disabled')) {
    function disabled($disabled, $current = true, $echo = true)
    {
        $result = ((string) $disabled === (string) $current) ? " disabled='disabled'" : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}
if (!function_exists('wp_kses_post')) {
    function wp_kses_post($content)
    {
        return $content;
    }
}
if (!function_exists('wp_kses')) {
    function wp_kses($content, $allowed_html = [], $allowed_protocols = [])
    {
        return $content;
    }
}
if (!function_exists('esc_textarea')) {
    function esc_textarea($text)
    {
        return esc_html((string) $text);
    }
}
if (!function_exists('wp_trim_words')) {
    function wp_trim_words($text, $num_words = 55, $more = null)
    {
        $words = preg_split('/\s+/', trim(strip_tags((string) $text))) ?: [];
        if (count($words) <= (int) $num_words) {
            return implode(' ', $words);
        }
        return implode(' ', array_slice($words, 0, (int) $num_words)) . ($more ?? '&hellip;');
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str)
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $str)));
    }
}
if (!function_exists('sanitize_title')) {
    function sanitize_title($title, $fallback_title = '', $context = 'save')
    {
        $slug = strtolower(trim((string) $title));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : (string) $fallback_title;
    }
}
if (!function_exists('wp_unique_id')) {
    function wp_unique_id($prefix = '')
    {
        static $id = 0;
        return (string) $prefix . (string) (++$id);
    }
}
if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0)
    {
        return number_format((float) $number, (int) $decimals);
    }
}
if (!function_exists('date_i18n')) {
    function date_i18n($format, $timestamp_with_offset = null)
    {
        return date((string) $format, $timestamp_with_offset ?? time());
    }
}

// --- wp_get_theme(): expose the style.css header (Version, Name, ...). ---- //
if (!function_exists('wp_get_theme')) {
    function wp_get_theme($stylesheet = null)
    {
        return new class {
            public function get($header)
            {
                static $headers = null;

                if ($headers === null) {
                    $headers = [];
                    $dir = (string) ($GLOBALS['localcms_theme_directory'] ?? '');
                    $css = ($dir !== '' && is_file($dir . '/style.css'))
                        ? (string) file_get_contents($dir . '/style.css')
                        : '';
                    $labels = [
                        'Name' => 'Theme Name',
                        'Version' => 'Version',
                        'Author' => 'Author',
                        'Description' => 'Description',
                        'ThemeURI' => 'Theme URI',
                    ];
                    foreach ($labels as $key => $label) {
                        if (preg_match('/^[\s*]*' . preg_quote($label, '/') . '\s*:\s*(.+)$/mi', $css, $m)) {
                            $headers[$key] = trim($m[1]);
                        }
                    }
                }

                return $headers[$header] ?? '';
            }

            public function get_stylesheet_directory()
            {
                return (string) ($GLOBALS['localcms_theme_directory'] ?? '');
            }

            public function __toString(): string
            {
                return (string) $this->get('Name');
            }
        };
    }
}

// --- Render-time template tags: safe, minimal output. -------------------- //
// A ported template calls these without the WordPress query machinery behind
// them, so they emit nothing harmful rather than fataling.
if (!function_exists('wp_nav_menu')) {
    function wp_nav_menu($args = [])
    {
        $args = is_array($args) ? $args : [];
        $echo = $args['echo'] ?? true;

        $items = [];
        try {
            $nav = ThemeRuntime::data('navigation', []);
            $items = is_array($nav) ? $nav : [];
        } catch (\Throwable) {
            $items = [];
        }

        if ($items === []) {
            return $echo ? false : false;
        }

        // Build individual <li> items.
        $menuClass  = (string) ($args['menu_class'] ?? 'menu');
        $menuId     = (string) ($args['menu_id'] ?? '');
        $itemsWrap  = (string) ($args['items_wrap'] ?? '<ul id="%1$s" class="%2$s">%3$s</ul>');

        $liHtml = '';
        foreach ($items as $item) {
            $url      = is_array($item) ? (string) ($item['url'] ?? '#') : '#';
            $label    = is_array($item) ? (string) ($item['label'] ?? '') : (string) $item;
            $isActive = is_array($item) && !empty($item['active']);
            $liClass  = 'menu-item' . ($isActive ? ' current-menu-item current_page_item' : '');
            $liHtml  .= '<li class="' . esc_attr($liClass) . '">';
            $liHtml  .= '<a href="' . esc_url(home_url($url)) . '">' . esc_html($label) . '</a>';
            $liHtml  .= '</li>';
        }

        $ulHtml = sprintf($itemsWrap, esc_attr($menuId), esc_attr($menuClass), $liHtml);

        // Wrap in a container element unless disabled.
        $container      = isset($args['container']) ? (string) $args['container'] : 'div';
        $containerClass = (string) ($args['container_class'] ?? 'menu-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string) ($args['theme_location'] ?? 'nav')) . '-container');
        $containerId    = (string) ($args['container_id'] ?? '');

        if ($container !== '' && $container !== 'false') {
            $containerAttrs = ' class="' . esc_attr($containerClass) . '"';
            if ($containerId !== '') {
                $containerAttrs .= ' id="' . esc_attr($containerId) . '"';
            }
            $html = '<' . $container . $containerAttrs . '>' . $ulHtml . '</' . $container . '>';
        } else {
            $html = $ulHtml;
        }

        if ($echo) {
            echo $html;
        }
        return $html;
    }
}
if (!function_exists('single_post_title')) {
    function single_post_title($prefix = '', $display = true)
    {
        if ($display) {
            return;
        }
        return '';
    }
}
if (!function_exists('the_title_attribute')) {
    function the_title_attribute($args = '')
    {
        $title = function_exists('get_the_title') ? get_the_title() : '';
        $echo = is_array($args) ? ($args['echo'] ?? true) : true;
        $value = esc_attr($title);
        if ($echo) {
            echo $value;
            return;
        }
        return $value;
    }
}
if (!function_exists('get_search_form')) {
    function get_search_form($args = [])
    {
        $echo = is_array($args) ? ($args['echo'] ?? true) : true;
        $form = '';
        if ($echo) {
            echo $form;
            return;
        }
        return $form;
    }
}
if (!function_exists('get_search_query')) {
    function get_search_query($escaped = true)
    {
        return '';
    }
}
if (!function_exists('wp_link_pages')) {
    function wp_link_pages($args = '')
    {
        return '';
    }
}
if (!function_exists('the_posts_pagination')) {
    function the_posts_pagination($args = [])
    {
    }
}
if (!function_exists('the_posts_navigation')) {
    function the_posts_navigation($args = [])
    {
    }
}
if (!function_exists('the_post_navigation')) {
    function the_post_navigation($args = [])
    {
    }
}
if (!function_exists('paginate_links')) {
    function paginate_links($args = '')
    {
        return '';
    }
}
if (!function_exists('get_the_ID')) {
    function get_the_ID()
    {
        $post = function_exists('get_post') ? get_post() : null;
        return is_array($post) ? ($post['id'] ?? 0) : 0;
    }
}
if (!function_exists('the_ID')) {
    function the_ID()
    {
        echo (string) get_the_ID();
    }
}
if (!function_exists('the_post_thumbnail')) {
    function the_post_thumbnail($size = 'post-thumbnail', $attr = '')
    {
        echo get_the_post_thumbnail(null, $size, $attr);
    }
}
if (!function_exists('get_the_post_thumbnail')) {
    function get_the_post_thumbnail($post = null, $size = 'post-thumbnail', $attr = '')
    {
        $url = function_exists('get_the_post_thumbnail_url') ? get_the_post_thumbnail_url($post) : '';
        return $url !== '' ? '<img class="wp-post-image" src="' . esc_url($url) . '" alt="" />' : '';
    }
}
if (!function_exists('post_thumbnail_url')) {
    function post_thumbnail_url($post = null)
    {
        return function_exists('get_the_post_thumbnail_url') ? get_the_post_thumbnail_url($post) : '';
    }
}
if (!function_exists('comments_template')) {
    function comments_template($file = '/comments.php', $separate_comments = false)
    {
    }
}
if (!function_exists('wp_list_comments')) {
    function wp_list_comments($args = [], $comments = null)
    {
    }
}
if (!function_exists('comment_form')) {
    function comment_form($args = [], $post = null)
    {
    }
}
if (!function_exists('get_comments_number')) {
    function get_comments_number($post = null)
    {
        return 0;
    }
}
if (!function_exists('comments_number')) {
    function comments_number($zero = false, $one = false, $more = false)
    {
        echo '';
    }
}
if (!function_exists('the_tags')) {
    function the_tags($before = null, $sep = ', ', $after = '')
    {
    }
}
if (!function_exists('get_the_tag_list')) {
    function get_the_tag_list($before = '', $sep = '', $after = '', $id = 0)
    {
        return '';
    }
}
if (!function_exists('the_category')) {
    function the_category($separator = '', $parents = '', $post_id = false)
    {
    }
}
if (!function_exists('get_the_category_list')) {
    function get_the_category_list($separator = '', $parents = '', $post_id = false)
    {
        return '';
    }
}
if (!function_exists('edit_post_link')) {
    function edit_post_link($text = null, $before = '', $after = '', $id = 0, $css_class = 'post-edit-link')
    {
    }
}
if (!function_exists('next_post_link')) {
    function next_post_link($format = '%link &raquo;', $link = '%title', ...$rest)
    {
    }
}
if (!function_exists('previous_post_link')) {
    function previous_post_link($format = '&laquo; %link', $link = '%title', ...$rest)
    {
    }
}
if (!function_exists('get_avatar')) {
    function get_avatar($id_or_email, $size = 96, ...$rest)
    {
        return '';
    }
}
if (!function_exists('post_type_archive_title')) {
    function post_type_archive_title($prefix = '', $display = true)
    {
        if ($display) {
            return;
        }
        return '';
    }
}
if (!function_exists('get_post_type_archive_link')) {
    function get_post_type_archive_link($post_type)
    {
        return home_url('/');
    }
}
if (!function_exists('get_sidebar')) {
    function get_sidebar($name = null, $args = [])
    {
        ThemeRuntime::includeTemplatePart('sidebar', $name, is_array($args) ? $args : []);
    }
}

// ---------------------------------------------------------------------------
// Author / user / post-type helpers (WP core functions missing from earlier sets)
// ---------------------------------------------------------------------------

if (!function_exists('get_the_author_meta')) {
    function get_the_author_meta($field, $user_id = 0)
    {
        try {
            $authorName = ThemeRuntime::getAuthorName();
        } catch (\Throwable) {
            $authorName = '';
        }
        return match ((string) $field) {
            'display_name'   => $authorName,
            'user_nicename'  => sanitize_title($authorName),
            'user_login'     => sanitize_title($authorName),
            'ID'             => 0,
            'description'    => '',
            'user_url'       => '',
            'user_email'     => '',
            default          => '',
        };
    }
}
if (!function_exists('get_author_posts_url')) {
    function get_author_posts_url($author_id, $author_nicename = '')
    {
        return home_url('/');
    }
}
if (!function_exists('post_type_supports')) {
    function post_type_supports($post_type, $feature)
    {
        return in_array((string) $feature, ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments'], true);
    }
}
if (!function_exists('get_post_format')) {
    function get_post_format($post = null)
    {
        return false;
    }
}
if (!function_exists('has_category')) {
    function has_category($category = '', $post = null)
    {
        try {
            return ThemeRuntime::getTerms('category', is_array($post) ? $post : null) !== [];
        } catch (\Throwable) {
            return false;
        }
    }
}
if (!function_exists('has_tag')) {
    function has_tag($tag = '', $post = null)
    {
        try {
            return ThemeRuntime::getTerms('tag', is_array($post) ? $post : null) !== [];
        } catch (\Throwable) {
            return false;
        }
    }
}
if (!function_exists('wpautop')) {
    // Passthrough: Local CMS content is already HTML; double-wrapping in <p> corrupts it.
    function wpautop($pee, $br = true)
    {
        return $pee;
    }
}
if (!function_exists('wp_print_inline_script_tag')) {
    function wp_print_inline_script_tag($js, $args = [])
    {
        $attrs = '';
        if (is_array($args)) {
            foreach ($args as $attr => $value) {
                if ($value !== false) {
                    $attrs .= ' ' . esc_attr((string) $attr);
                    if ($value !== true) {
                        $attrs .= '="' . esc_attr((string) $value) . '"';
                    }
                }
            }
        }
        echo '<script' . $attrs . '>' . $js . '</script>' . "\n";
    }
}
if (!function_exists('get_query_var')) {
    function get_query_var($var, $default = false)
    {
        return $default;
    }
}
if (!function_exists('twenty_twenty_one_the_html_classes')) {
    // Stub for themes that define this via hooked callbacks that never run.
    function twenty_twenty_one_the_html_classes()
    {
    }
}
if (!function_exists('get_the_tags')) {
    function get_the_tags($id = 0)
    {
        try {
            $terms = ThemeRuntime::getTerms('tag');
            return $terms !== [] ? $terms : false;
        } catch (\Throwable) {
            return false;
        }
    }
}
if (!function_exists('get_the_category')) {
    function get_the_category($id = 0)
    {
        try {
            return ThemeRuntime::getTerms('category');
        } catch (\Throwable) {
            return [];
        }
    }
}
if (!function_exists('get_term_link')) {
    function get_term_link($term, $taxonomy = '')
    {
        $slug = is_array($term) ? (string) ($term['slug'] ?? '') : (string) $term;
        $tax  = $taxonomy !== '' ? (string) $taxonomy : (is_array($term) ? (string) ($term['taxonomy'] ?? 'category') : 'category');
        return home_url(($tax === 'tag' ? '/tag/' : '/category/') . rawurlencode($slug));
    }
}
if (!function_exists('get_tag_link')) {
    function get_tag_link($tag)
    {
        $slug = is_array($tag) ? (string) ($tag['slug'] ?? '') : (string) $tag;
        return home_url('/tag/' . rawurlencode($slug));
    }
}
if (!function_exists('get_category_link')) {
    function get_category_link($category)
    {
        $slug = is_array($category) ? (string) ($category['slug'] ?? '') : (string) $category;
        return home_url('/category/' . rawurlencode($slug));
    }
}
if (!function_exists('get_bloginfo_rss')) {
    function get_bloginfo_rss($show = '')
    {
        return get_bloginfo($show);
    }
}

// ---------------------------------------------------------------------------
// URL / Path utilities  (heavily used by namespaced themes and theme classes)
// ---------------------------------------------------------------------------

if (!function_exists('trailingslashit')) {
    function trailingslashit($string)
    {
        return rtrim((string) $string, '/\\') . '/';
    }
}
if (!function_exists('untrailingslashit')) {
    function untrailingslashit($string)
    {
        return rtrim((string) $string, '/\\');
    }
}
if (!function_exists('leadingslashit')) {
    function leadingslashit($string)
    {
        return '/' . ltrim((string) $string, '/\\');
    }
}
if (!function_exists('site_url')) {
    function site_url($path = '', $scheme = null)
    {
        return home_url($path);
    }
}
if (!function_exists('content_url')) {
    function content_url($path = '')
    {
        // Map to /uploads which is the public content directory in Local CMS.
        $base = '/uploads';
        return $path !== '' ? $base . '/' . ltrim((string) $path, '/') : $base;
    }
}
if (!function_exists('plugins_url')) {
    function plugins_url($path = '', $plugin = '')
    {
        return '/plugins' . ($path !== '' ? '/' . ltrim((string) $path, '/') : '');
    }
}
if (!function_exists('includes_url')) {
    function includes_url($path = '', $scheme = null)
    {
        return home_url('/wp-includes/' . ltrim((string) $path, '/'));
    }
}
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir($time = null, $create_dir = true, $refresh_cache = false)
    {
        try {
            $base = rtrim((string) ($GLOBALS['localcms_theme_directory'] ?? ''), '/\\');
            // Walk up to root: themes/<name> → root/uploads
            $root = dirname($base, 2);
        } catch (\Throwable) {
            $root = '';
        }
        $uploadDir  = $root . '/uploads';
        $uploadUrl  = '/uploads';
        return [
            'path'    => $uploadDir,
            'url'     => $uploadUrl,
            'subdir'  => '',
            'basedir' => $uploadDir,
            'baseurl' => $uploadUrl,
            'error'   => false,
        ];
    }
}
if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1)
    {
        return parse_url((string) $url, $component);
    }
}
if (!function_exists('set_url_scheme')) {
    function set_url_scheme($url, $scheme = null)
    {
        return (string) $url;
    }
}
if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url, $protocols = null)
    {
        return (string) $url;
    }
}
if (!function_exists('path_join')) {
    function path_join($base, $path)
    {
        return rtrim((string) $base, '/') . '/' . ltrim((string) $path, '/');
    }
}
if (!function_exists('path_is_absolute')) {
    function path_is_absolute($path)
    {
        return (bool) (strlen((string) $path) > 0 && ((string) $path)[0] === '/' ||
            (strlen((string) $path) >= 3 && ctype_alpha($path[0]) && $path[1] === ':'));
    }
}

// ---------------------------------------------------------------------------
// Debugging / Error helpers
// ---------------------------------------------------------------------------

if (!function_exists('_doing_it_wrong')) {
    function _doing_it_wrong($function, $message, $version = '')
    {
        // Intentional no-op: Local CMS has no equivalent admin notice system.
    }
}
if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = [])
    {
        // Only throw; callers inside theme code that use wp_die() for fatal
        // conditions will trigger the App-level error handler rather than
        // terminating the process.
        throw new \RuntimeException(
            'wp_die: ' . strip_tags(is_string($message) ? $message : (string) ($message ?? ''))
        );
    }
}
if (!function_exists('wp_trigger_error')) {
    function wp_trigger_error($function_name, $message, $error_type = \E_USER_NOTICE)
    {
        // Swallow — themes call this to signal deprecations; not needed here.
    }
}
if (!function_exists('wp_debug_backtrace_summary')) {
    function wp_debug_backtrace_summary($ignore_class = null, $skip_frames = 0, $pretty = true)
    {
        return '';
    }
}

// ---------------------------------------------------------------------------
// Hook extras (remove_all_*, doing_filter, etc.)
// ---------------------------------------------------------------------------

if (!function_exists('remove_all_actions')) {
    function remove_all_actions($hook_name, $priority = false)
    {
        return true;
    }
}
if (!function_exists('remove_all_filters')) {
    function remove_all_filters($hook_name, $priority = false)
    {
        return true;
    }
}

// ---------------------------------------------------------------------------
// String / Array utilities
// ---------------------------------------------------------------------------

if (!function_exists('wp_sprintf')) {
    function wp_sprintf($pattern, ...$args)
    {
        return vsprintf($pattern, $args);
    }
}
if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string, $remove_breaks = false)
    {
        $string = strip_tags((string) $string);
        if ($remove_breaks) {
            $string = preg_replace('/[\r\n\t ]+/', ' ', $string) ?? $string;
        }
        return trim($string);
    }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $flags = 0, $depth = 512)
    {
        return json_encode($data, $flags, $depth);
    }
}
if (!function_exists('wp_array_slice_assoc')) {
    function wp_array_slice_assoc($array, $keys)
    {
        return array_intersect_key($array, array_flip($keys));
    }
}
if (!function_exists('wp_list_pluck')) {
    function wp_list_pluck($list, $field, $index_key = null)
    {
        $out = [];
        foreach ((array) $list as $key => $value) {
            $val = is_array($value) ? ($value[$field] ?? null) : (is_object($value) ? ($value->$field ?? null) : null);
            if ($index_key !== null) {
                $idx = is_array($value) ? ($value[$index_key] ?? $key) : (is_object($value) ? ($value->$index_key ?? $key) : $key);
                $out[$idx] = $val;
            } else {
                $out[$key] = $val;
            }
        }
        return $out;
    }
}
if (!function_exists('wp_parse_list')) {
    function wp_parse_list($input_list)
    {
        if (!is_array($input_list)) {
            return preg_split('/[\s,]+/', (string) $input_list, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        return $input_list;
    }
}
if (!function_exists('wp_slash')) {
    function wp_slash($value)
    {
        return is_array($value) ? array_map('wp_slash', $value) : addslashes((string) $value);
    }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return is_array($value) ? array_map('wp_unslash', $value) : stripslashes((string) $value);
    }
}
if (!function_exists('zeroise')) {
    function zeroise($number, $threshold)
    {
        return sprintf('%0' . (int) $threshold . 's', $number);
    }
}

// ---------------------------------------------------------------------------
// Post / Object helpers (stubs for class-heavy themes)
// ---------------------------------------------------------------------------

if (!function_exists('wp_get_themes')) {
    function wp_get_themes($args = [])
    {
        return [];
    }
}
if (!function_exists('get_post')) {
    function get_post($post = null, $output = 'OBJECT', $filter = 'raw')
    {
        try {
            $item = ThemeRuntime::currentItem();
        } catch (\Throwable) {
            return null;
        }
        if ($item === null) return null;
        $obj = new \stdClass();
        foreach ($item as $k => $v) {
            $obj->$k = $v;
        }
        $obj->ID           = (int) ($item['id'] ?? 0);
        $obj->post_title   = (string) ($item['title'] ?? '');
        $obj->post_content = (string) ($item['body_html'] ?? '');
        $obj->post_excerpt = (string) ($item['excerpt'] ?? '');
        $obj->post_name    = (string) ($item['slug'] ?? '');
        $obj->post_type    = (string) ($item['content_type'] ?? 'post');
        $obj->post_status  = 'publish';
        $obj->post_author  = 1;
        $obj->post_date    = (string) ($item['published_at'] ?? '');
        return $output === 'ARRAY_A' ? (array) $obj : ($output === 'ARRAY_N' ? array_values((array) $obj) : $obj);
    }
}
if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false)
    {
        return $single ? '' : [];
    }
}
if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '')
    {
        return false;
    }
}
if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key = '', $single = false)
    {
        return $single ? '' : [];
    }
}
if (!function_exists('get_userdata')) {
    function get_userdata($user_id)
    {
        return false;
    }
}
if (!function_exists('get_user_by')) {
    function get_user_by($field, $value)
    {
        return false;
    }
}
if (!function_exists('register_taxonomy')) {
    function register_taxonomy($taxonomy, $object_type, $args = [])
    {
        return true;
    }
}
if (!function_exists('register_post_type')) {
    function register_post_type($post_type, $args = [])
    {
        return true;
    }
}
if (!function_exists('get_post_type_object')) {
    function get_post_type_object($post_type)
    {
        return null;
    }
}
if (!function_exists('post_exists')) {
    function post_exists($title, $content = '', $date = '', $type = '', $status = '')
    {
        return 0;
    }
}

// ---------------------------------------------------------------------------
// Theme customizer / option utilities
// ---------------------------------------------------------------------------

if (!function_exists('has_custom_logo')) {
    function has_custom_logo($blog_id = 0)
    {
        return false;
    }
}
if (!function_exists('the_custom_logo')) {
    function the_custom_logo($blog_id = 0)
    {
    }
}
if (!function_exists('get_custom_logo')) {
    function get_custom_logo($blog_id = 0)
    {
        return '';
    }
}
if (!function_exists('the_custom_header_markup')) {
    function the_custom_header_markup()
    {
    }
}
if (!function_exists('get_header_image')) {
    function get_header_image()
    {
        return '';
    }
}
if (!function_exists('get_header_image_tag')) {
    function get_header_image_tag($attr = [])
    {
        return '';
    }
}
if (!function_exists('get_background_image')) {
    function get_background_image()
    {
        return '';
    }
}
if (!function_exists('wp_get_document_title')) {
    function wp_get_document_title()
    {
        try {
            return (string) ThemeRuntime::data('pageTitle', '');
        } catch (\Throwable) {
            return '';
        }
    }
}

// ---------------------------------------------------------------------------
// Elementor / builder compatibility stubs
// ---------------------------------------------------------------------------

if (!function_exists('elementor_theme_do_location')) {
    // Returns false so ported themes fall back to their default header/footer.
    function elementor_theme_do_location($location)
    {
        return false;
    }
}
if (!function_exists('elementor_location_exits')) {
    function elementor_location_exits($location, $check_match = false)
    {
        return false;
    }
}
if (!function_exists('kubio_print_location')) {
    function kubio_print_location($location, $args = [])
    {
        return false;
    }
}
if (!function_exists('colibri_wp_do_hook_location')) {
    function colibri_wp_do_hook_location($location, $args = [])
    {
        return false;
    }
}

// ---------------------------------------------------------------------------
// WordPress Class stubs
// These allow themes that type-hint or instantiate WordPress classes to work
// without those classes being loaded. They are minimal surfaces; just enough
// for the theme to not crash.
// ---------------------------------------------------------------------------

if (!class_exists('WP_Query')) {
    class WP_Query
    {
        public bool $is_main_query = true;
        public bool $is_singular = false;
        public bool $is_archive = false;
        public bool $is_home = true;
        public bool $is_search = false;
        public bool $is_404 = false;
        public bool $is_paged = false;
        public int $found_posts = 0;
        public int $post_count = 0;
        public int $max_num_pages = 0;
        public array $posts = [];
        public ?\stdClass $post = null;

        public function __construct(array $args = [])
        {
            try {
                $this->posts = ThemeRuntime::data('items', []);
            } catch (\Throwable) {
                $this->posts = [];
            }
            $this->post_count   = count($this->posts);
            $this->found_posts  = $this->post_count;
            $this->max_num_pages = $this->post_count > 0 ? 1 : 0;
        }

        public function have_posts(): bool
        {
            return !empty($this->posts);
        }

        public function the_post(): void
        {
        }

        public function rewind_posts(): void
        {
        }

        public function get(string $query_var): mixed
        {
            return null;
        }

        public function set(string $query_var, mixed $value): void
        {
        }

        public function get_queried_object(): ?\stdClass
        {
            return null;
        }

        public function get_queried_object_id(): int
        {
            return 0;
        }

        public function is_main_query(): bool
        {
            return $this->is_main_query;
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private array $errors = [];
        private array $error_data = [];

        public function __construct($code = '', $message = '', $data = '')
        {
            if ($code !== '') {
                $this->errors[$code][] = $message;
                if ($data !== '') {
                    $this->error_data[$code] = $data;
                }
            }
        }

        public function get_error_code(): string
        {
            return (string) array_key_first($this->errors) ?? '';
        }

        public function get_error_message($code = ''): string
        {
            $code = $code !== '' ? $code : $this->get_error_code();
            return (string) ($this->errors[$code][0] ?? '');
        }

        public function get_error_messages($code = ''): array
        {
            if ($code !== '') {
                return $this->errors[$code] ?? [];
            }
            return array_merge(...array_values($this->errors)) ?: [];
        }

        public function has_errors(): bool
        {
            return !empty($this->errors);
        }

        public function add($code, $message, $data = ''): void
        {
            $this->errors[$code][] = $message;
            if ($data !== '') {
                $this->error_data[$code] = $data;
            }
        }

        public function get_all_error_data($code = ''): array
        {
            if ($code !== '') {
                return isset($this->error_data[$code]) ? [$this->error_data[$code]] : [];
            }
            return array_values($this->error_data);
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (!class_exists('WP_Post')) {
    class WP_Post extends \stdClass
    {
        public int $ID = 0;
        public string $post_title = '';
        public string $post_content = '';
        public string $post_excerpt = '';
        public string $post_name = '';
        public string $post_type = 'post';
        public string $post_status = 'publish';
        public string $post_author = '';
        public string $post_date = '';
        public string $post_date_gmt = '';
        public string $post_modified = '';
        public string $post_modified_gmt = '';
        public string $post_parent = '';
        public string $comment_status = 'closed';
        public string $ping_status = 'closed';
        public string $guid = '';
        public int $menu_order = 0;
        public string $comment_count = '0';
    }
}

if (!class_exists('WP_User')) {
    class WP_User
    {
        public int $ID = 1;
        public string $user_login = 'admin';
        public string $user_email = '';
        public string $display_name = '';
        public string $user_nicename = '';
        public \stdClass $data;

        public function __construct($id = 0)
        {
            $this->ID   = (int) $id;
            $this->data = new \stdClass();
        }

        public function has_cap(string $cap): bool
        {
            return false;
        }

        public function get(string $key): string
        {
            return (string) ($this->$key ?? '');
        }
    }
}

if (!class_exists('WP_Term')) {
    class WP_Term extends \stdClass
    {
        public int $term_id = 0;
        public string $name = '';
        public string $slug = '';
        public string $taxonomy = '';
        public string $description = '';
        public int $count = 0;
        public int $parent = 0;
        public string $term_taxonomy_id = '';
    }
}

if (!class_exists('WP_Theme')) {
    class WP_Theme
    {
        private array $headers = [];

        public function __construct(string $theme_dir = '', string $theme_root = '')
        {
        }

        public function get(string $header): string|false
        {
            return $this->headers[$header] ?? false;
        }

        public function get_stylesheet(): string
        {
            return '';
        }

        public function get_template(): string
        {
            return '';
        }
    }
}

if (!function_exists('wp_get_theme')) {
    function wp_get_theme($stylesheet = '', $theme_root = '')
    {
        return new WP_Theme($stylesheet, $theme_root);
    }
}

if (!class_exists('WP_Nav_Menu_Item')) {
    class WP_Nav_Menu_Item extends WP_Post
    {
        public string $url = '';
        public string $title = '';
        public string $target = '';
        public string $attr_title = '';
        public string $description = '';
        public int $menu_item_parent = 0;
        public string $classes = '';
        public string $xfn = '';
        public string $object = '';
        public string $object_id = '';
        public string $type = '';
        public string $type_label = '';
        public array $db_id = [];
    }
}

// ---------------------------------------------------------------------------
// Query reset / postdata utilities
// ---------------------------------------------------------------------------

if (!function_exists('wp_reset_postdata')) {
    function wp_reset_postdata()
    {
    }
}
if (!function_exists('wp_reset_query')) {
    function wp_reset_query()
    {
    }
}
if (!function_exists('setup_postdata')) {
    function setup_postdata($post)
    {
    }
}
if (!function_exists('rewind_posts')) {
    function rewind_posts()
    {
    }
}
if (!function_exists('get_query_flag')) {
    function get_query_flag($flag)
    {
        return false;
    }
}

// ---------------------------------------------------------------------------
// Sidebar / Widgets stubs (themes call these but we have no sidebar system)
// ---------------------------------------------------------------------------

if (!function_exists('register_sidebar')) {
    function register_sidebar($args = [])
    {
        return 'sidebar-' . ($args['id'] ?? 1);
    }
}
if (!function_exists('dynamic_sidebar')) {
    function dynamic_sidebar($index = 1)
    {
        return false;
    }
}
if (!function_exists('is_active_sidebar')) {
    function is_active_sidebar($index)
    {
        return false;
    }
}
if (!function_exists('is_active_widget')) {
    function is_active_widget($callback = false, $widget_id = false, $id_base = false, $skip_inactive = true)
    {
        return false;
    }
}

// ---------------------------------------------------------------------------
// Customizer / theme-mod stubs
// ---------------------------------------------------------------------------

if (!function_exists('get_theme_mod')) {
    function get_theme_mod($name, $default_value = false)
    {
        return $default_value;
    }
}
if (!function_exists('set_theme_mod')) {
    function set_theme_mod($name, $value)
    {
    }
}
if (!function_exists('remove_theme_mod')) {
    function remove_theme_mod($name)
    {
    }
}
if (!function_exists('get_theme_mods')) {
    function get_theme_mods()
    {
        return [];
    }
}

// ---------------------------------------------------------------------------
// Comments stubs (ported themes often call these, we have no comment system)
// ---------------------------------------------------------------------------

if (!function_exists('comments_open')) {
    function comments_open($post_id = 0)
    {
        return false;
    }
}
if (!function_exists('comments_number')) {
    function comments_number($zero = false, $one = false, $more = false, $post_id = 0)
    {
    }
}
if (!function_exists('get_comments_number')) {
    function get_comments_number($post_id = 0)
    {
        return 0;
    }
}
if (!function_exists('comments_template')) {
    function comments_template($file = '/comments.php', $separate_comments = false)
    {
    }
}
if (!function_exists('comment_form')) {
    function comment_form($args = [], $post = null)
    {
    }
}
if (!function_exists('wp_list_comments')) {
    function wp_list_comments($args = [], $comments = null)
    {
    }
}
if (!function_exists('pings_open')) {
    function pings_open($post_id = 0)
    {
        return false;
    }
}

// ---------------------------------------------------------------------------
// Navigation / menu extras
// ---------------------------------------------------------------------------

if (!function_exists('wp_page_menu')) {
    function wp_page_menu($args = [])
    {
    }
}
if (!function_exists('get_nav_menu_locations')) {
    function get_nav_menu_locations()
    {
        return [];
    }
}
if (!function_exists('wp_get_nav_menus')) {
    function wp_get_nav_menus($args = [])
    {
        return [];
    }
}
if (!function_exists('wp_nav_menu_item_classes')) {
    function wp_nav_menu_item_classes($menu_items, $args)
    {
        return $menu_items;
    }
}

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------

if (!function_exists('the_posts_pagination')) {
    function the_posts_pagination($args = [])
    {
    }
}
if (!function_exists('the_posts_navigation')) {
    function the_posts_navigation($args = [])
    {
    }
}
if (!function_exists('get_the_posts_navigation')) {
    function get_the_posts_navigation($args = [])
    {
        return '';
    }
}
if (!function_exists('get_the_posts_pagination')) {
    function get_the_posts_pagination($args = [])
    {
        return '';
    }
}
if (!function_exists('paginate_links')) {
    function paginate_links($args = [])
    {
        return '';
    }
}
if (!function_exists('previous_posts_link')) {
    function previous_posts_link($label = null, $max_page = 0)
    {
    }
}
if (!function_exists('next_posts_link')) {
    function next_posts_link($label = null, $max_page = 0)
    {
    }
}
if (!function_exists('previous_post_link')) {
    function previous_post_link($format = '&laquo; %link', $link = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category')
    {
    }
}
if (!function_exists('next_post_link')) {
    function next_post_link($format = '%link &raquo;', $link = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category')
    {
    }
}

// ---------------------------------------------------------------------------
// Search & forms
// ---------------------------------------------------------------------------

if (!function_exists('get_search_form')) {
    function get_search_form($args = [])
    {
        $echo = isset($args['echo']) ? (bool) $args['echo'] : true;
        $html = '<form role="search" method="get" action="/"><input type="search" name="s" placeholder="Search..." /></form>';
        if ($echo) {
            echo $html;
        }
        return $html;
    }
}
if (!function_exists('the_search_query')) {
    function the_search_query()
    {
        echo isset($_GET['s']) ? esc_html((string) $_GET['s']) : '';
    }
}
if (!function_exists('get_search_query')) {
    function get_search_query($escaped = true)
    {
        $q = isset($_GET['s']) ? (string) $_GET['s'] : '';
        return $escaped ? esc_html($q) : $q;
    }
}

// ---------------------------------------------------------------------------
// Tags / clouds
// ---------------------------------------------------------------------------

if (!function_exists('wp_tag_cloud')) {
    function wp_tag_cloud($args = '')
    {
        $r = wp_parse_args($args, ['echo' => true]);
        if ($r['echo']) {
            echo '';
        }
        return '';
    }
}
if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = [])
    {
        if (is_object($args)) {
            $r = (array) $args;
        } elseif (is_array($args)) {
            $r = $args;
        } else {
            parse_str((string) $args, $r);
        }
        return array_merge((array) $defaults, $r);
    }
}

// ---------------------------------------------------------------------------
// Authentication / registration links
// ---------------------------------------------------------------------------

if (!function_exists('wp_register')) {
    function wp_register($before = '<li>', $after = '</li>', $echo = true)
    {
    }
}
if (!function_exists('wp_loginout')) {
    function wp_loginout($redirect = '', $echo = true)
    {
    }
}
if (!function_exists('auth_redirect')) {
    function auth_redirect()
    {
    }
}

// ---------------------------------------------------------------------------
// Archives / queries
// ---------------------------------------------------------------------------

if (!function_exists('wp_get_archives')) {
    function wp_get_archives($args = '')
    {
    }
}
if (!function_exists('get_posts')) {
    function get_posts($args = null)
    {
        try {
            return (array) ThemeRuntime::data('items', []);
        } catch (\Throwable) {
            return [];
        }
    }
}
if (!function_exists('get_adjacent_post')) {
    function get_adjacent_post($in_same_term = false, $excluded_terms = '', $previous = true, $taxonomy = 'category', $post = null)
    {
        return null;
    }
}
if (!function_exists('the_date')) {
    function the_date($format = '', $before = '', $after = '', $echo = true)
    {
        $result = $before . get_the_date($format) . $after;
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}
if (!function_exists('get_the_date')) {
    function get_the_date($format = '', $post = null)
    {
        try {
            $date = (string) ThemeRuntime::data('pageDate', '');
        } catch (\Throwable) {
            $date = '';
        }
        if ($date === '') return '';
        try {
            $dt = new \DateTimeImmutable($date);
            return $dt->format($format !== '' ? $format : get_option('date_format', 'F j, Y'));
        } catch (\Throwable) {
            return $date;
        }
    }
}
if (!function_exists('get_the_modified_date')) {
    function get_the_modified_date($format = '', $post = null)
    {
        return get_the_date($format, $post);
    }
}
if (!function_exists('the_modified_date')) {
    function the_modified_date($format = '', $before = '', $after = '', $echo = true)
    {
        return the_date($format, $before, $after, $echo);
    }
}
if (!function_exists('get_the_time')) {
    function get_the_time($format = '', $post = null)
    {
        return get_the_date($format, $post);
    }
}
if (!function_exists('the_time')) {
    function the_time($format = '')
    {
        echo get_the_time($format);
    }
}

// ---------------------------------------------------------------------------
// Misc template functions used by many themes
// ---------------------------------------------------------------------------

if (!function_exists('wp_title')) {
    function wp_title($sep = '&raquo;', $echo = true, $seplocation = '')
    {
        try {
            $title = (string) ThemeRuntime::data('pageTitle', '');
        } catch (\Throwable) {
            $title = '';
        }
        if ($echo) {
            echo esc_html($title);
        }
        return $title;
    }
}
if (!function_exists('single_post_title')) {
    function single_post_title($prefix = '', $echo = true)
    {
        try {
            $title = (string) ThemeRuntime::data('pageTitle', '');
        } catch (\Throwable) {
            $title = '';
        }
        $result = $prefix . $title;
        if ($echo) {
            echo esc_html($result);
        }
        return $result;
    }
}
if (!function_exists('the_archive_title')) {
    function the_archive_title($before = '', $after = '')
    {
        echo $before . esc_html(get_the_archive_title()) . $after;
    }
}
if (!function_exists('get_the_archive_title')) {
    function get_the_archive_title()
    {
        try {
            return (string) ThemeRuntime::data('pageTitle', 'Archive');
        } catch (\Throwable) {
            return 'Archive';
        }
    }
}
if (!function_exists('the_archive_description')) {
    function the_archive_description($before = '', $after = '')
    {
    }
}
if (!function_exists('the_post_thumbnail')) {
    function the_post_thumbnail($size = 'post-thumbnail', $attr = [])
    {
    }
}
if (!function_exists('get_the_post_thumbnail')) {
    function get_the_post_thumbnail($post = null, $size = 'post-thumbnail', $attr = [])
    {
        return '';
    }
}
if (!function_exists('get_the_post_thumbnail_url')) {
    function get_the_post_thumbnail_url($post = null, $size = 'post-thumbnail')
    {
        return '';
    }
}
if (!function_exists('has_post_thumbnail')) {
    function has_post_thumbnail($post = null)
    {
        return false;
    }
}
if (!function_exists('the_ID')) {
    function the_ID()
    {
        try {
            echo (int) ThemeRuntime::data('item.id', 0);
        } catch (\Throwable) {
        }
    }
}
if (!function_exists('get_the_ID')) {
    function get_the_ID()
    {
        try {
            return (int) ThemeRuntime::data('item.id', 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
if (!function_exists('get_option')) {
    function get_option($option, $default_value = false)
    {
        $map = [
            'blogname'        => 'siteName',
            'blogdescription' => 'siteTagline',
            'siteurl'         => '/',
            'home'            => '/',
            'date_format'     => 'F j, Y',
            'time_format'     => 'g:i a',
            'posts_per_page'  => 10,
        ];
        if (isset($map[$option])) {
            $key = $map[$option];
            try {
                return ThemeRuntime::data($key, $default_value);
            } catch (\Throwable) {
                return $default_value;
            }
        }
        return $default_value;
    }
}
if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null)
    {
        return false;
    }
}
if (!function_exists('add_option')) {
    function add_option($option, $value = '', $deprecated = '', $autoload = 'yes')
    {
        return false;
    }
}
if (!function_exists('delete_option')) {
    function delete_option($option)
    {
        return false;
    }
}
if (!function_exists('the_author')) {
    function the_author($deprecated = '', $deprecated_echo = true)
    {
        try {
            echo esc_html((string) ThemeRuntime::getAuthorName());
        } catch (\Throwable) {
        }
    }
}
if (!function_exists('get_the_author')) {
    function get_the_author($deprecated = '')
    {
        try {
            return (string) ThemeRuntime::getAuthorName();
        } catch (\Throwable) {
            return '';
        }
    }
}
if (!function_exists('the_password_required')) {
    function the_password_required()
    {
        return false;
    }
}
if (!function_exists('post_password_required')) {
    function post_password_required($post = null)
    {
        return false;
    }
}
if (!function_exists('is_multi_author')) {
    function is_multi_author()
    {
        return false;
    }
}
if (!function_exists('wp_count_posts')) {
    function wp_count_posts($type = 'post', $perm = '')
    {
        $obj = new \stdClass();
        $obj->publish = 0;
        return $obj;
    }
}
if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0)
    {
        return number_format((float) $number, $decimals);
    }
}
if (!function_exists('size_format')) {
    function size_format($bytes, $decimals = 0)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $exp   = (int) floor(log(max(1, (float) $bytes), 1024));
        $exp   = min($exp, count($units) - 1);
        return round((float) $bytes / pow(1024, $exp), $decimals) . ' ' . $units[$exp];
    }
}
if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data)
    {
        return $data;
    }
}
if (!function_exists('wp_kses')) {
    function wp_kses($string, $allowed_html, $allowed_protocols = [])
    {
        return $string;
    }
}
if (!function_exists('wp_kses_allowed_html')) {
    function wp_kses_allowed_html($context = '')
    {
        return [];
    }
}
if (!function_exists('capital_P_dangit')) {
    function capital_P_dangit($text)
    {
        return str_replace('Wordpress', 'WordPress', (string) $text);
    }
}
if (!function_exists('wptexturize')) {
    function wptexturize($text)
    {
        return $text;
    }
}
if (!function_exists('convert_smilies')) {
    function convert_smilies($text)
    {
        return $text;
    }
}
if (!function_exists('shortcode_unautop')) {
    function shortcode_unautop($pee)
    {
        return $pee;
    }
}
if (!function_exists('do_shortcode')) {
    function do_shortcode($content, $ignore_html = false)
    {
        return $content;
    }
}
if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback)
    {
    }
}
if (!function_exists('remove_shortcode')) {
    function remove_shortcode($tag)
    {
    }
}
if (!function_exists('has_shortcode')) {
    function has_shortcode($content, $tag)
    {
        return false;
    }
}
if (!function_exists('shortcode_atts')) {
    function shortcode_atts(array $pairs, $atts, $shortcode = '')
    {
        return array_merge($pairs, array_intersect_key((array) $atts, $pairs));
    }
}
if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true)
    {
        return '';
    }
}
if (!function_exists('wp_nonce_url')) {
    function wp_nonce_url($actionurl, $action = -1, $name = '_wpnonce')
    {
        return (string) $actionurl;
    }
}
if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $die = true)
    {
        return true;
    }
}
if (!function_exists('wp_send_json')) {
    function wp_send_json($response, $status_code = null, $flags = 0)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, $flags);
        exit();
    }
}
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null, $flags = 0)
    {
        wp_send_json(['success' => true, 'data' => $data], $status_code, $flags);
    }
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null, $flags = 0)
    {
        wp_send_json(['success' => false, 'data' => $data], $status_code, $flags);
    }
}
// ---------------------------------------------------------------------------
// Additional WordPress-core shims (ported-theme coverage).
//
// These cover common core functions that ported themes call directly. They are
// type-correct (an array-returning core function returns a real array, etc.) so
// a template can pass the result to join()/array_map()/foreach without a
// TypeError. Anything theme-specific and still unknown falls through to the
// ThemeFunctionBridge fallback instead.
// ---------------------------------------------------------------------------

if (!function_exists('get_post_class')) {
    function get_post_class($class = '', $post = null)
    {
        return ThemeRuntime::postClasses($class, is_array($post) ? $post : null);
    }
}
if (!function_exists('get_body_class')) {
    function get_body_class($class = '')
    {
        return ThemeRuntime::bodyClasses($class);
    }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg(...$args)
    {
        if (isset($args[0]) && is_array($args[0])) {
            $params = $args[0];
            $url = (string) ($args[1] ?? ($_SERVER['REQUEST_URI'] ?? '/'));
        } else {
            $params = [(string) ($args[0] ?? '') => $args[1] ?? ''];
            $url = (string) ($args[2] ?? ($_SERVER['REQUEST_URI'] ?? '/'));
        }

        $fragment = '';
        if (($hash = strpos($url, '#')) !== false) {
            $fragment = substr($url, $hash);
            $url = substr($url, 0, $hash);
        }

        $base = $url;
        $query = '';
        if (($mark = strpos($url, '?')) !== false) {
            $base = substr($url, 0, $mark);
            $query = substr($url, $mark + 1);
        }

        $current = [];
        parse_str($query, $current);
        foreach ($params as $key => $value) {
            if ($value === false || $value === null) {
                unset($current[$key]);
            } else {
                $current[$key] = $value;
            }
        }

        $built = http_build_query($current);

        return $base . ($built !== '' ? '?' . $built : '') . $fragment;
    }
}
if (!function_exists('remove_query_arg')) {
    function remove_query_arg($key, $url = false)
    {
        $keys = is_array($key) ? $key : [$key];
        $url = (string) ($url !== false ? $url : ($_SERVER['REQUEST_URI'] ?? '/'));
        foreach ($keys as $singleKey) {
            $url = add_query_arg((string) $singleKey, false, $url);
        }
        return $url;
    }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($key)
    {
        return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', (string) $key));
    }
}
if (!function_exists('sanitize_email')) {
    function sanitize_email($email)
    {
        return (string) filter_var((string) $email, FILTER_SANITIZE_EMAIL);
    }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str)
    {
        return trim((string) strip_tags((string) $str));
    }
}
if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name($filename)
    {
        $filename = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $filename);
        return trim($filename, '.-');
    }
}
if (!function_exists('is_multisite')) {
    function is_multisite()
    {
        return false;
    }
}
if (!function_exists('has_block')) {
    function has_block($block_name, $post = null)
    {
        return false;
    }
}
if (!function_exists('has_blocks')) {
    function has_blocks($content = null)
    {
        return false;
    }
}
if (!function_exists('parse_blocks')) {
    function parse_blocks($content)
    {
        return [];
    }
}
if (!function_exists('render_block')) {
    function render_block($block)
    {
        return is_array($block) ? (string) ($block['innerHTML'] ?? '') : '';
    }
}
if (!function_exists('do_blocks')) {
    function do_blocks($content)
    {
        return (string) $content;
    }
}
if (!function_exists('excerpt_remove_blocks')) {
    function excerpt_remove_blocks($content)
    {
        return (string) $content;
    }
}
if (!function_exists('get_term_by')) {
    function get_term_by($field, $value, $taxonomy = '', $output = 'OBJECT', $filter = 'raw')
    {
        return false;
    }
}
if (!function_exists('get_terms')) {
    function get_terms(...$args)
    {
        return [];
    }
}
if (!function_exists('get_categories')) {
    function get_categories($args = '')
    {
        return [];
    }
}
if (!function_exists('get_tags')) {
    function get_tags($args = '')
    {
        return [];
    }
}
if (!function_exists('wp_get_post_terms')) {
    function wp_get_post_terms($post_id = 0, $taxonomy = 'post_tag', $args = [])
    {
        return [];
    }
}
if (!function_exists('wp_get_post_categories')) {
    function wp_get_post_categories($post_id = 0, $args = [])
    {
        return [];
    }
}
if (!function_exists('wp_count_posts')) {
    function wp_count_posts($type = 'post', $perm = '')
    {
        return (object) ['publish' => 0, 'draft' => 0, 'pending' => 0];
    }
}
if (!function_exists('get_transient')) {
    function get_transient($transient)
    {
        return false;
    }
}
if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0)
    {
        return true;
    }
}
if (!function_exists('delete_transient')) {
    function delete_transient($transient)
    {
        return true;
    }
}
if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '', $force = false, &$found = null)
    {
        $found = false;
        return false;
    }
}
if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0)
    {
        return true;
    }
}
if (!function_exists('get_post_thumbnail_id')) {
    function get_post_thumbnail_id($post = null)
    {
        return 0;
    }
}
if (!function_exists('wp_get_attachment_image')) {
    function wp_get_attachment_image($attachment_id, $size = 'thumbnail', $icon = false, $attr = '')
    {
        return '';
    }
}
if (!function_exists('wp_get_attachment_image_src')) {
    function wp_get_attachment_image_src($attachment_id, $size = 'thumbnail', $icon = false)
    {
        return false;
    }
}
if (!function_exists('wp_get_attachment_image_url')) {
    function wp_get_attachment_image_url($attachment_id, $size = 'thumbnail', $icon = false)
    {
        return '';
    }
}
if (!function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url($attachment_id = 0)
    {
        return '';
    }
}
if (!function_exists('wp_get_attachment_caption')) {
    function wp_get_attachment_caption($post_id = 0)
    {
        return '';
    }
}
if (!function_exists('wp_get_attachment_metadata')) {
    function wp_get_attachment_metadata($attachment_id = 0, $unfiltered = false)
    {
        return [];
    }
}
if (!function_exists('wp_get_attachment_image_attributes')) {
    function wp_get_attachment_image_attributes($attr = [], $attachment = null, $size = 'thumbnail')
    {
        return is_array($attr) ? $attr : [];
    }
}
if (!function_exists('image_downsize')) {
    function image_downsize($id, $size = 'medium')
    {
        return false;
    }
}
if (!function_exists('get_year_link')) {
    function get_year_link($year)
    {
        return home_url('/');
    }
}
if (!function_exists('get_month_link')) {
    function get_month_link($year, $month)
    {
        return home_url('/');
    }
}
if (!function_exists('get_day_link')) {
    function get_day_link($year, $month, $day)
    {
        return home_url('/');
    }
}
if (!function_exists('register_widget')) {
    function register_widget($widget)
    {
        return null;
    }
}
if (!function_exists('unregister_widget')) {
    function unregister_widget($widget)
    {
        return null;
    }
}
if (!function_exists('the_widget')) {
    function the_widget($widget, $instance = [], $args = [])
    {
        echo '';
    }
}
if (!function_exists('wp_get_nav_menu_items')) {
    function wp_get_nav_menu_items($menu = 0, $args = [])
    {
        return [];
    }
}
if (!function_exists('wp_get_nav_menu_object')) {
    function wp_get_nav_menu_object($menu)
    {
        return false;
    }
}
if (!function_exists('wp_create_nav_menu')) {
    function wp_create_nav_menu($menu_name)
    {
        return 0;
    }
}
if (!function_exists('current_time')) {
    function current_time($type = 'timestamp', $gmt = 0)
    {
        return match ((string) $type) {
            'timestamp', 'U' => time(),
            'mysql'          => gmdate('Y-m-d H:i:s'),
            default          => gmdate((string) $type),
        };
    }
}
if (!function_exists('mysql2date')) {
    function mysql2date($format, $date, $translate = true)
    {
        if (empty($date)) {
            return false;
        }
        try {
            return (new DateTimeImmutable((string) $date))->format((string) $format);
        } catch (\Throwable) {
            return false;
        }
    }
}
if (!function_exists('get_the_time')) {
    function get_the_time($format = '', $post = null)
    {
        return ThemeRuntime::getDate($format !== '' ? (string) $format : 'g:i a', is_array($post) ? $post : null);
    }
}
if (!function_exists('the_time')) {
    function the_time($format = '', $post = null)
    {
        echo esc_html(get_the_time($format, $post));
    }
}
if (!function_exists('get_the_modified_date')) {
    function get_the_modified_date($format = '', $post = null)
    {
        return get_the_date($format !== '' ? (string) $format : 'M j, Y', is_array($post) ? $post : null);
    }
}
if (!function_exists('get_the_modified_time')) {
    function get_the_modified_time($format = '', $post = null)
    {
        return get_the_time($format, $post);
    }
}
if (!function_exists('the_modified_date')) {
    function the_modified_date($format = '', $before = '', $after = '', $post = null)
    {
        echo $before . esc_html(get_the_modified_date($format, $post)) . $after;
    }
}
if (!function_exists('wp_get_post_parent_id')) {
    function wp_get_post_parent_id($post = null)
    {
        return 0;
    }
}
if (!function_exists('get_post_parent')) {
    function get_post_parent($post = null)
    {
        return null;
    }
}
if (!function_exists('get_stylesheet_uri')) {
    function get_stylesheet_uri()
    {
        return ThemeRuntime::bloginfo('stylesheet_url');
    }
}
if (!function_exists('get_custom_header')) {
    function get_custom_header()
    {
        return (object) ['url' => '', 'width' => 0, 'height' => 0];
    }
}
if (!function_exists('has_custom_header')) {
    function has_custom_header()
    {
        return false;
    }
}
if (!function_exists('get_header_image')) {
    function get_header_image()
    {
        return '';
    }
}
if (!function_exists('antispambot')) {
    function antispambot($email_address, $hex_encoding = 0)
    {
        return (string) $email_address;
    }
}
if (!function_exists('wp_specialchars_decode')) {
    function wp_specialchars_decode($string, $quote_style = ENT_NOQUOTES)
    {
        return htmlspecialchars_decode((string) $string, (int) $quote_style);
    }
}
