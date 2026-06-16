<?php
/*
 * [local-cms] minimal portable template
 *
 * Replaces a builder-dependent header so the theme renders real chrome through
 * the supported WordPress compatibility tags instead of a page-builder runtime
 * the Local CMS runtime cannot satisfy. Modelled on the Twenty Twenty-One
 * header (which ports cleanly): document head via wp_head(), a branding block,
 * and a primary nav via wp_nav_menu(). The original is preserved under
 * _unported/. WP-standard class names are used so the theme's own style.css
 * still applies.
 */
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="site">
    <a class="skip-link screen-reader-text" href="#content">Skip to content</a>

    <header id="masthead" class="site-header">
        <div class="site-branding">
            <?php if ( has_custom_logo() ) : ?>
                <div class="site-logo"><?php the_custom_logo(); ?></div>
            <?php endif; ?>
            <p class="site-title">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
            </p>
            <?php $localcms_description = get_bloginfo( 'description' ); ?>
            <?php if ( $localcms_description ) : ?>
                <p class="site-description"><?php echo esc_html( $localcms_description ); ?></p>
            <?php endif; ?>
        </div>

        <?php if ( has_nav_menu( 'primary' ) ) : ?>
            <nav id="site-navigation" class="main-navigation" aria-label="Primary">
                <?php
                wp_nav_menu(
                    array(
                        'theme_location' => 'primary',
                        'menu_class'     => 'menu nav-menu',
                        'container'      => false,
                        'fallback_cb'    => false,
                    )
                );
                ?>
            </nav>
        <?php endif; ?>
    </header>

    <div id="content" class="site-content">
