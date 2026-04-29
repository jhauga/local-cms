<?php
declare(strict_types=1);
?>
<section class="admin-panel">
    <div class="section-heading">
        <p class="eyebrow">Overview</p>
        <h1>Admin Dashboard</h1>
        <p>Review the publishing surface, then jump into the editors.</p>
    </div>

    <div class="stat-grid">
        <article class="stat-card"><span>Pages</span><strong><?= htmlspecialchars((string) $summary['pages'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></article>
        <article class="stat-card"><span>Posts</span><strong><?= htmlspecialchars((string) $summary['posts'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></article>
        <article class="stat-card"><span>Categories</span><strong><?= htmlspecialchars((string) $summary['categories'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></article>
        <article class="stat-card"><span>Drafts</span><strong><?= htmlspecialchars((string) $summary['drafts'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></article>
    </div>
</section>

<section class="admin-panel">
    <div class="split-heading">
        <div>
            <p class="eyebrow">Recent Pages</p>
            <h2>Page updates</h2>
        </div>
        <a class="ghost-link" href="/admin/pages">Manage pages</a>
    </div>
    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr><th>Title</th><th>Status</th><th>Updated</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recentPages as $item): ?>
                    <tr>
                        <td><a href="/admin/pages/<?= htmlspecialchars((string) $item['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></td>
                        <td><?= htmlspecialchars((string) $item['status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $item['updated_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-panel">
    <div class="split-heading">
        <div>
            <p class="eyebrow">Recent Posts</p>
            <h2>Post updates</h2>
        </div>
        <a class="ghost-link" href="/admin/posts">Manage posts</a>
    </div>
    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr><th>Title</th><th>Status</th><th>Updated</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recentPosts as $item): ?>
                    <tr>
                        <td><a href="/admin/posts/<?= htmlspecialchars((string) $item['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></td>
                        <td><?= htmlspecialchars((string) $item['status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $item['updated_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
