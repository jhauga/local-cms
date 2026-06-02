<?php
declare(strict_types=1);

$siteName = $siteName ?? get_bloginfo('name');
$navigation = $navigation ?? localcms_theme_navigation();
?>
        <footer class="site-footer">
            <div class="footer-copy">
                <p class="footer-heading">Built to migrate cleanly.</p>
                <p>Template parts, body classes, and compatibility tags now mirror the next WordPress extraction step.</p>
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
    <script>
    (() => {
        const shell = document.querySelector('[data-site-header-shell]');
        const header = document.querySelector('[data-site-header]');

        if (!shell || !header) {
            return;
        }

        const desktopQuery = window.matchMedia('(min-width: 901px)');
        const threshold = 100;
        let ticking = false;

        const syncShellHeight = () => {
            shell.style.height = `${header.offsetHeight}px`;
        };

        const updateHeaderState = () => {
            const shouldFix = desktopQuery.matches && window.scrollY > threshold;

            header.classList.toggle('is-fixed', shouldFix);
            document.body.classList.toggle('has-fixed-header', shouldFix);
            syncShellHeight();
        };

        const queueUpdate = () => {
            if (ticking) {
                return;
            }

            ticking = true;
            window.requestAnimationFrame(() => {
                updateHeaderState();
                ticking = false;
            });
        };

        if (typeof ResizeObserver === 'function') {
            new ResizeObserver(syncShellHeight).observe(header);
        }

        if (typeof desktopQuery.addEventListener === 'function') {
            desktopQuery.addEventListener('change', queueUpdate);
        } else if (typeof desktopQuery.addListener === 'function') {
            desktopQuery.addListener(queueUpdate);
        }

        window.addEventListener('scroll', queueUpdate, { passive: true });
        window.addEventListener('resize', queueUpdate);
        updateHeaderState();
    })();
    </script>
</body>
</html>
