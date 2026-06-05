<?php
declare(strict_types=1);

$siteName = $siteName ?? get_bloginfo('name');
$navigation = $navigation ?? localcms_theme_navigation();
?>
        <footer class="site-footer">
            <div class="footer-copy">
                <p class="footer-heading">Page-builder ready, Markdown friendly.</p>
                <p>Bootstrap-based templates and template parts render in Local CMS and as a standalone WordPress theme.</p>
            </div>
            <nav class="footer-nav" aria-label="Footer navigation">
                <?php foreach ($navigation as $item): ?>
                    <a class="footer-link" href="<?= esc_url(home_url((string) $item['url'])) ?>"><?= localcms_render_compact_markdown((string) $item['label']) ?></a>
                <?php endforeach; ?>
            </nav>
            <p class="footer-meta">&copy; <?= date('Y') ?> <?= esc_html($siteName) ?></p>
        </footer>
    </div>
    <?php wp_footer(); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
