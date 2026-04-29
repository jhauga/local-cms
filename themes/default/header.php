<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?= esc_attr(get_bloginfo('charset')) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc_html($pageTitle) ?></title>
    <meta name="description" content="<?= esc_attr($pageDescription) ?>">
    <link rel="stylesheet" href="<?= esc_url(get_bloginfo('stylesheet_url')) ?>">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>
    <a class="skip-link" href="#content">Skip to content</a>
    <div class="page-shell">
        <header class="site-header">
            <div class="site-branding">
                <p class="site-kicker">WordPress-shaped theme</p>
                <a class="site-title" href="<?= esc_url(home_url('/')) ?>"><?php bloginfo('name'); ?></a>
                <p class="site-tagline"><?php bloginfo('description'); ?></p>
            </div>
            <nav class="site-nav" aria-label="Primary navigation">
                <?php foreach ($navigation as $item): ?>
                    <a
                        class="nav-link<?= !empty($item['active']) ? ' is-active' : '' ?>"
                        href="<?= esc_url(home_url((string) $item['url'])) ?>"
                    >
                        <?= esc_html((string) $item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </header>
