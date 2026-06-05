<?php
declare(strict_types=1);

/*
 * Right sidebar. Under WordPress the 'sidebar-primary' widget area renders when
 * it has widgets; otherwise (and in the Local CMS runtime, which has no widget
 * system) a static set of widgets is shown using the navigation and category
 * data the runtime injects.
 */
$categories = $categories ?? (function_exists('localcms_theme_terms') ? localcms_theme_terms() : []);
$navigation = $navigation ?? (function_exists('localcms_theme_navigation') ? localcms_theme_navigation() : []);
$hasWidgets = function_exists('is_active_sidebar') && is_active_sidebar('sidebar-primary');
?>
<aside class="site-sidebar" aria-label="Sidebar">
    <?php if ($hasWidgets): ?>
        <?php dynamic_sidebar('sidebar-primary'); ?>
    <?php else: ?>
        <section class="widget widget-search">
            <h2 class="widget-title">Search</h2>
            <?php get_template_part('searchform'); ?>
        </section>

        <?php if (!empty($categories)): ?>
            <section class="widget widget-categories">
                <h2 class="widget-title">Categories</h2>
                <ul class="widget-list">
                    <?php foreach ($categories as $category): ?>
                        <li>
                            <a href="<?= esc_url(theme_term_url($category)) ?>">
                                <?= esc_html((string) $category['name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if (!empty($navigation)): ?>
            <section class="widget widget-nav">
                <h2 class="widget-title">Explore</h2>
                <ul class="widget-list">
                    <?php foreach ($navigation as $navItem): ?>
                        <li>
                            <a href="<?= esc_url(home_url((string) $navItem['url'])) ?>">
                                <?= localcms_render_compact_markdown((string) $navItem['label']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</aside>
