<?php
/*
 * [local-cms] minimal portable template
 *
 * A single post. Replaces a builder- or hook-driven single template. Runs the
 * loop once and renders the full content for the one item the runtime supplies,
 * so a single route shows that post rather than a dump of every post.
 */
get_header();
?>
<main id="main" class="site-main narrow-layout">
    <?php while ( have_posts() ) : the_post(); ?>
        <article <?php post_class(); ?>>
            <header class="entry-header">
                <h1 class="entry-title"><?php the_title(); ?></h1>
                <div class="entry-meta">
                    <span class="posted-on"><?php echo esc_html( get_the_date() ); ?></span>
                    <span class="byline"><?php echo esc_html( get_the_author() ); ?></span>
                </div>
            </header>
            <div class="entry-content"><?php the_content(); ?></div>
        </article>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
