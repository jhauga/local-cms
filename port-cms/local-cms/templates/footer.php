<?php
/*
 * [local-cms] minimal portable template
 *
 * Closes the structure opened by the minimal header and emits a simple footer.
 * Replaces a builder-dependent footer whose markup was produced by theme action
 * hooks the runtime does not fire. The original is preserved under _unported/.
 */
?>
    </div><!-- #content -->

    <footer id="colophon" class="site-footer">
        <div class="site-info">
            <a class="site-footer-name" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
            <span class="sep"> &middot; </span>
            <span class="site-footer-year"><?php echo esc_html( gmdate( 'Y' ) ); ?></span>
        </div>
    </footer>
</div><!-- #page -->

<?php wp_footer(); ?>
</body>
</html>
