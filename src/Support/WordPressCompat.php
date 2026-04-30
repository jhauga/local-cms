<?php
declare(strict_types=1);

use Cms\Core\ThemeRuntime;
use Cms\Support\Markdown;

if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
    function get_the_title(?array $post = null): string
    {
        return ThemeRuntime::getTitle($post);
    }
}

if (!function_exists('the_title')) {
    function the_title(string $before = '', string $after = '', ?array $post = null): void
    {
        echo $before . localcms_render_compact_markdown(get_the_title($post)) . $after;
    }
}

if (!function_exists('get_the_excerpt')) {
    function get_the_excerpt(?array $post = null): string
    {
        return ThemeRuntime::getExcerpt($post);
    }
}

if (!function_exists('the_excerpt')) {
    function the_excerpt(?array $post = null): void
    {
        echo esc_html(get_the_excerpt($post));
    }
}

if (!function_exists('get_the_content')) {
    function get_the_content(?array $post = null): string
    {
        return ThemeRuntime::getContent($post);
    }
}

if (!function_exists('the_content')) {
    function the_content(?array $post = null): void
    {
        echo get_the_content($post);
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink(?array $post = null): string
    {
        return ThemeRuntime::getPermalink($post);
    }
}

if (!function_exists('the_permalink')) {
    function the_permalink(?array $post = null): void
    {
        echo esc_url(get_permalink($post));
    }
}

if (!function_exists('get_the_date')) {
    function get_the_date(string $format = 'M j, Y', ?array $post = null): string
    {
        return ThemeRuntime::getDate($format, $post);
    }
}

if (!function_exists('the_date')) {
    function the_date(string $format = 'M j, Y', ?array $post = null): void
    {
        echo esc_html(get_the_date($format, $post));
    }
}

if (!function_exists('get_the_author')) {
    function get_the_author(?array $post = null): string
    {
        return ThemeRuntime::getAuthorName($post);
    }
}

if (!function_exists('the_author')) {
    function the_author(?array $post = null): void
    {
        echo esc_html(get_the_author($post));
    }
}

if (!function_exists('get_the_terms')) {
    function get_the_terms(?array $post, string $taxonomy): array
    {
        return ThemeRuntime::getTerms($taxonomy, $post);
    }
}

if (!function_exists('has_post_thumbnail')) {
    function has_post_thumbnail(?array $post = null): bool
    {
        return ThemeRuntime::hasPostThumbnail($post);
    }
}

if (!function_exists('get_the_post_thumbnail_url')) {
    function get_the_post_thumbnail_url(?array $post = null): string
    {
        return ThemeRuntime::getPostThumbnailUrl($post);
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