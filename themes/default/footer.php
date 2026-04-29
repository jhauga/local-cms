<?php
declare(strict_types=1);
?>
        <footer class="site-footer">
            <div class="footer-copy">
                <p class="footer-heading">Built to migrate cleanly.</p>
                <p>Template parts, body classes, and compatibility tags now mirror the next WordPress extraction step.</p>
            </div>
            <nav class="footer-nav" aria-label="Footer navigation">
                <?php foreach ($navigation as $item): ?>
                    <a class="footer-link" href="<?= esc_url(home_url((string) $item['url'])) ?>"><?= esc_html((string) $item['label']) ?></a>
                <?php endforeach; ?>
            </nav>
            <p class="footer-meta">&copy; <?= date('Y') ?> <?= esc_html($siteName) ?></p>
        </footer>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
