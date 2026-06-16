<?php
/*
 * [local-cms] minimal portable template
 *
 * A single page. Replaces a builder- or hook-driven page template. Renders the
 * one page the runtime supplies (no post meta, page titles use an h1).
 */
get_header();
?>
<main id="main" class="site-main narrow-layout">
    <?php while ( have_posts() ) : the_post(); ?>
        <article <?php post_class(); ?>>
            <header class="entry-header">
                <h1 class="entry-title"><?php the_title(); ?></h1>
            </header>
            <div class="entry-content"><?php the_content(); ?></div>
        </article>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
