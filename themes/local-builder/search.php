<?php
declare(strict_types=1);

// Search results template. Rendered by WordPress for /?s=term and preserved for
// the port adapters; the Local CMS runtime does not route search itself.
$searchQuery = function_exists('get_search_query') ? get_search_query() : '';
?>
<?php get_header(); ?>
<div class="with-sidebar">
    <main id="content" class="site-main">
        <section class="content-section">
            <div class="section-heading">
                <p class="eyebrow">Search</p>
                <h1>Results for &ldquo;<?= esc_html($searchQuery) ?>&rdquo;</h1>
            </div>

            <section class="widget widget-search">
                <?php get_template_part('searchform'); ?>
            </section>

            <div class="story-grid">
                <?php if (have_posts()): ?>
                    <?php while (have_posts()): ?>
                        <?php the_post(); ?>
                        <?php get_template_part('template-parts/post-card'); ?>
                    <?php endwhile; ?>
                    <?php rewind_posts(); ?>
                <?php else: ?>
                    <article class="content-panel empty-state">
                        <h2>Nothing matched</h2>
                        <p>No content matched that search. Try a different term or browse the archive.</p>
                    </article>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <?php if (function_exists('dynamic_sidebar')) { get_sidebar(); } else { get_template_part('sidebar'); } ?>
</div>
<?php get_footer(); ?>
