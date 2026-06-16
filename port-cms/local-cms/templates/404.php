<?php
/*
 * [local-cms] minimal portable template
 *
 * The "not found" template. Replaces a builder- or hook-driven 404 with a plain
 * message and a link home, using only supported tags.
 */
get_header();
?>
<main id="main" class="site-main">
    <section class="error-404 not-found">
        <header class="page-header">
            <h1 class="page-title">Page not found</h1>
        </header>
        <div class="page-content">
            <p>The page you are looking for is not here.
               <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Return home</a>.</p>
        </div>
    </section>
</main>
<?php get_footer(); ?>
