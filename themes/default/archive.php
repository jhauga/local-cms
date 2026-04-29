<?php
declare(strict_types=1);
?>
<?php get_header(); ?>
<main id="content" class="site-main archive-shell">
    <section class="content-section">
        <div class="section-heading section-heading-split">
            <div>
                <p class="eyebrow"><?= is_category() ? 'Category archive' : (is_tag() ? 'Tag archive' : 'Archive') ?></p>
                <h1><?php the_archive_title(); ?></h1>
                <p class="lead"><?php the_archive_description(); ?></p>
            </div>
            <div class="archive-summary">
                <span class="summary-count"><?= esc_html((string) count($posts)) ?></span>
                <span class="summary-label"><?= count($posts) === 1 ? 'published entry' : 'published entries' ?></span>
            </div>
        </div>

        <?php if (!empty($categories)): ?>
            <div class="term-list archive-filters">
                <?php foreach ($categories as $category): ?>
                    <a
                        class="term-pill is-category<?= !empty($archiveTerm) && ($archiveTerm['taxonomy'] ?? '') === 'category' && ($archiveTerm['slug'] ?? '') === ($category['slug'] ?? '') ? ' is-active' : '' ?>"
                        href="<?= esc_url(theme_term_url($category)) ?>"
                    >
                        <?= esc_html((string) $category['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($posts === []): ?>
            <article class="content-panel empty-state">
                <h2>No posts found</h2>
                <p>This archive exists, but it does not have any published posts yet.</p>
            </article>
        <?php endif; ?>

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
