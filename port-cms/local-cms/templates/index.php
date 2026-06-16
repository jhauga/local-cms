<?php
/*
 * [local-cms] minimal portable template
 *
 * The blog/home loop. Replaces a builder- or hook-driven index whose content
 * the runtime cannot produce. Mirrors the Twenty Twenty-One loop using only the
 * supported template tags so real posts render with the theme's own styling.
 */
get_header();
?>
<main id="main" class="site-main">
    <?php if ( have_posts() ) : ?>
        <?php while ( have_posts() ) : the_post(); ?>
            <article <?php post_class(); ?>>
                <header class="entry-header">
                    <h2 class="entry-title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h2>
                    <div class="entry-meta">
                        <span class="posted-on"><?php echo esc_html( get_the_date() ); ?></span>
                        <span class="byline"><?php echo esc_html( get_the_author() ); ?></span>
                    </div>
                </header>
                <div class="entry-summary"><?php the_excerpt(); ?></div>
                <a class="more-link" href="<?php the_permalink(); ?>">Continue reading</a>
            </article>
        <?php endwhile; ?>
    <?php else : ?>
        <p class="no-posts">No posts were found.</p>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
