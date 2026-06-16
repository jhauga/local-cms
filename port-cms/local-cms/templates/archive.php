<?php
/*
 * [local-cms] minimal portable template
 *
 * An archive / term listing. Replaces a builder- or hook-driven archive. Shows
 * the archive title plus an excerpt list of the items in the current query.
 */
get_header();
?>
<main id="main" class="site-main">
    <header class="page-header">
        <h1 class="page-title"><?php the_archive_title(); ?></h1>
        <?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
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
        <p class="no-posts">Nothing was found in this archive.</p>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
