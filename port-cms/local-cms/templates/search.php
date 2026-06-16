<?php
/*
 * [local-cms] minimal portable template
 *
 * Search results. Replaces a builder- or hook-driven search template with the
 * same excerpt list the archive uses.
 */
get_header();
?>
<main id="main" class="site-main">
    <header class="page-header">
        <h1 class="page-title">Search results</h1>
    </header>

    <?php if ( have_posts() ) : ?>
        <?php while ( have_posts() ) : the_post(); ?>
            <article <?php post_class(); ?>>
                <h2 class="entry-title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h2>
                <div class="entry-summary"><?php the_excerpt(); ?></div>
            </article>
        <?php endwhile; ?>
    <?php else : ?>
        <p class="no-posts">Nothing matched your search.</p>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
