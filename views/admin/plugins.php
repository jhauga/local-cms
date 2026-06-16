<?php
declare(strict_types=1);
?>
<section class="admin-panel">
    <div class="section-heading">
        <p class="eyebrow">Extensions</p>
        <h1>Plugins</h1>
        <p>Plugins are read from the <code>plugins/</code> folder. Each plugin directory must contain a PHP file with a <code>Plugin Name</code> header comment.</p>
    </div>

    <?php if (empty($plugins)): ?>
        <p>No plugins are installed. Add a plugin directory under <code>plugins/</code> to see it here.</p>
    <?php else: ?>
        <div class="theme-grid">
            <?php foreach ($plugins as $plugin): ?>
                <article class="theme-card">
                    <div class="theme-body">
                        <h2 class="theme-name">
                            <?= htmlspecialchars((string) $plugin['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            <?php if (!empty($plugin['version'])): ?>
                                <span class="theme-version">v<?= htmlspecialchars((string) $plugin['version'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </h2>
                        <?php if (!empty($plugin['author'])): ?>
                            <p class="theme-meta">
                                By
                                <?php if (!empty($plugin['authorUri'])): ?>
                                    <a href="<?= htmlspecialchars((string) $plugin['authorUri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noreferrer noopener">
                                        <?= htmlspecialchars((string) $plugin['author'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                    </a>
                                <?php else: ?>
                                    <?= htmlspecialchars((string) $plugin['author'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($plugin['description'])): ?>
                            <p class="theme-desc"><?= htmlspecialchars((string) $plugin['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <div class="theme-actions">
                            <?php if (!empty($plugin['pluginUri'])): ?>
                                <a class="text-link" href="<?= htmlspecialchars((string) $plugin['pluginUri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noreferrer noopener">Plugin page</a>
                            <?php endif; ?>
                            <?php if (!empty($plugin['license'])): ?>
                                <span class="theme-meta">
                                    License:
                                    <?php if (!empty($plugin['licenseUri'])): ?>
                                        <a href="<?= htmlspecialchars((string) $plugin['licenseUri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noreferrer noopener">
                                            <?= htmlspecialchars((string) $plugin['license'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars((string) $plugin['license'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="theme-meta" style="margin-top:.5rem;font-family:monospace;font-size:.8em;word-break:break-all;">
                            <?= htmlspecialchars('plugins/' . $plugin['slug'] . '/' . basename((string) $plugin['mainFile']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
