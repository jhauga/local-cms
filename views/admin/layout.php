<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <link rel="icon" href="/uploads/admin-favicon.svg">
    <?php foreach ($stylesheets as $stylesheet): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($stylesheet, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <?php endforeach; ?>
    <script>window.LocalCmsMarkdownTemplates = <?= json_encode($markdownTemplateMap ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;</script>
</head>
<body class="admin-body">
    <div class="admin-shell">
        <header class="admin-header">
            <div>
                <a class="admin-brand" href="/admin"><?= htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
                <p class="admin-tagline"><?= htmlspecialchars($siteTagline, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            </div>
            <?php if (!empty($authUser)): ?>
                <div class="admin-userbox">
                    <p>Local author: <?= htmlspecialchars((string) $authUser['display_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                </div>
            <?php endif; ?>
        </header>

        <div class="admin-grid">
            <?php if (empty($isGuestScreen)): ?>
                <aside class="admin-sidebar">
                    <nav aria-label="Admin navigation">
                        <a class="admin-nav-link<?= ($currentSection ?? '') === 'dashboard' ? ' is-active' : '' ?>" href="/admin">Dashboard</a>
                        <a class="admin-nav-link<?= ($currentSection ?? '') === 'pages' ? ' is-active' : '' ?>" href="/admin/pages">Pages</a>
                        <a class="admin-nav-link<?= ($currentSection ?? '') === 'posts' ? ' is-active' : '' ?>" href="/admin/posts">Posts</a>
                        <a class="admin-nav-link<?= ($currentSection ?? '') === 'taxonomies' ? ' is-active' : '' ?>" href="/admin/taxonomies">Taxonomies</a>
                        <a class="admin-nav-link<?= ($currentSection ?? '') === 'templating' ? ' is-active' : '' ?>" href="/admin/templating">Templating</a>
                        <a class="admin-nav-link<?= ($currentSection ?? '') === 'settings' ? ' is-active' : '' ?>" href="/admin/settings">Settings</a>
                        <a class="admin-nav-link" href="/" target="_blank" rel="noreferrer">Open site</a>
                    </nav>
                </aside>
            <?php endif; ?>

            <main class="admin-main">
                <?php if (!empty($notice)): ?>
                    <div class="notice-banner"><?= htmlspecialchars((string) $notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if (!empty($errorNotice)): ?>
                    <div class="notice-banner is-error"><?= htmlspecialchars((string) $errorNotice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php require $contentTemplate; ?>
            </main>
        </div>
    </div>
</body>
</html>
