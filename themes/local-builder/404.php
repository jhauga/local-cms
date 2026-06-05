<?php
declare(strict_types=1);

// 404 template. Rendered by WordPress for unresolved URLs and preserved for the
// port adapters; the Local CMS runtime serves its own not-found page template.
?>
<?php get_header(); ?>
<main id="content" class="site-main narrow-layout">
    <article class="content-panel entry-shell">
        <div class="entry-main">
            <p class="eyebrow">Error 404</p>
            <h1>Page not found</h1>
            <p class="lead">The page you were looking for is not here. It may have been moved, renamed, or never existed.</p>

            <section class="widget widget-search">
                <h2 class="widget-title">Search instead</h2>
                <?php get_template_part('searchform'); ?>
            </section>

            <div class="hero-actions">
                <a class="button-link" href="<?= esc_url(home_url('/')) ?>">Back to home</a>
                <a class="text-link" href="<?= esc_url(localcms_posts_url()) ?>">Browse posts</a>
            </div>
        </div>
    </article>
</main>
<?php get_footer(); ?>
