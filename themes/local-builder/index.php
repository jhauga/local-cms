<?php
declare(strict_types=1);

/*
 * Front-page data is supplied by local-cms; provide WordPress fallbacks so the
 * hero and category rail still render after a direct theme import.
 */
$siteName = $siteName ?? get_bloginfo('name');
$intro = $intro ?? get_bloginfo('description');
$heroPage = $heroPage ?? [];
$categories = $categories ?? localcms_theme_terms();
?>
<?php get_header(); ?>
<main id="content" class="site-main">
    <section class="hero-panel">
        <div class="hero-copy">
            <p class="eyebrow">Page builder ready</p>
            <h1><?= esc_html((string) ($heroPage['title'] ?? $siteName)) ?></h1>
            <p class="hero-intro"><?= esc_html($intro) ?></p>
            <div class="prose">
                <?= $heroPage['body_html'] ?? '<p>Seed content is ready for the next slice.</p>' ?>
            </div>
            <div class="hero-actions">
                <a class="button-link" href="<?= esc_url(localcms_posts_url()) ?>">Read sample posts</a>
                <a class="text-link" href="<?= esc_url(localcms_page_url('about')) ?>">See the theme approach</a>
            </div>
        </div>
        <aside class="hero-card">
            <p class="hero-card-label">Browse by category</p>
            <div class="term-list compact-terms">
                <?php foreach ($categories as $category): ?>
                    <a class="term-pill" href="<?= esc_url(theme_term_url($category)) ?>">
                        <?= esc_html((string) $category['name']) ?>
                        <span><?= esc_html((string) $category['content_count']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="compat-panel">
                <p class="hero-card-label">Built on Bootstrap</p>
                <ul class="feature-list compact-list">
                    <li>Bootstrap 5 grid and utilities with a sharp, dark slate gray skin</li>
                    <li>Shared template parts for cards, entries, and terms</li>
                    <li>Body and post classes mirror the WordPress hooks</li>
                </ul>
            </div>
        </aside>
    </section>

    <section class="content-section">
        <div class="section-heading">
            <p class="eyebrow">Recent entries</p>
            <h2>Latest posts</h2>
        </div>
        <div class="story-grid">
            <?php if (have_posts()): ?>
                <?php while (have_posts()): ?>
                    <?php the_post(); ?>
                    <?php get_template_part('template-parts/post-card'); ?>
                <?php endwhile; ?>
                <?php rewind_posts(); ?>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php get_footer(); ?>
