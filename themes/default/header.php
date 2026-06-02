<?php
declare(strict_types=1);

/*
 * When local-cms renders this theme it injects $pageTitle, $pageDescription,
 * $siteName and $navigation. Under stock WordPress those are absent, so fall
 * back to core APIs. The ?? operator preserves any value local-cms provides.
 */
$siteName = $siteName ?? get_bloginfo('name');
$pageTitle = $pageTitle ?? (function_exists('wp_get_document_title') ? wp_get_document_title() : $siteName);
$pageDescription = $pageDescription ?? get_bloginfo('description');
$navigation = $navigation ?? localcms_theme_navigation();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?= esc_attr(get_bloginfo('charset')) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc_html(localcms_compact_markdown_text($pageTitle)) ?></title>
    <meta name="description" content="<?= esc_attr($pageDescription) ?>">
    <link rel="icon" href="<?= esc_url(theme_media_url('favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= esc_url(get_bloginfo('stylesheet_url')) ?>">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>
    <a class="skip-link" href="#content">Skip to content</a>
    <div class="page-shell">
        <div class="site-header-shell" data-site-header-shell>
            <header class="site-header" data-site-header>
                <div class="site-branding">
                    <p class="site-kicker">WordPress-shaped theme</p>
                    <a class="site-title" href="<?= esc_url(home_url('/')) ?>">
                        <?php if (has_custom_logo()): ?>
                            <?php the_custom_logo(); ?>
                        <?php else: ?>
                            <img class="logo" src="<?= esc_url(theme_media_url('logo.svg')) ?>" alt="<?= esc_attr(get_bloginfo('name')) ?>">
                        <?php endif; ?>
                    </a>
                    <p class="site-tagline"><?php bloginfo('description'); ?></p>
                </div>
                <nav class="site-nav" aria-label="Primary navigation">
                    <?php foreach ($navigation as $item): ?>
                        <a
                            class="nav-link<?= !empty($item['active']) ? ' is-active' : '' ?>"
                            href="<?= esc_url(home_url((string) $item['url'])) ?>"
                        >
                            <?= localcms_render_compact_markdown((string) $item['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </header>
        </div>
