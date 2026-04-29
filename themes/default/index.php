<?php
declare(strict_types=1);
?>
<?php get_header(); ?>
<main id="content" class="site-main">
    <section class="hero-panel">
        <div class="hero-copy">
            <p class="eyebrow">Starter CMS</p>
            <h1><?= esc_html((string) ($heroPage['title'] ?? $siteName)) ?></h1>
            <p class="hero-intro"><?= esc_html($intro) ?></p>
            <div class="prose">
                <?= $heroPage['body_html'] ?? '<p>Seed content is ready for the next slice.</p>' ?>
            </div>
            <div class="hero-actions">
                <a class="button-link" href="<?= esc_url(home_url('/posts')) ?>">Read sample posts</a>
                <a class="text-link" href="<?= esc_url(home_url('/page/about')) ?>">See the theme approach</a>
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
                <p class="hero-card-label">Compatibility layer</p>
                <ul class="feature-list compact-list">
                    <li>Header and footer now load through theme helpers</li>
                    <li>Shared card blocks render through template parts</li>
                    <li>Body and post classes already mirror WordPress hooks</li>
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
