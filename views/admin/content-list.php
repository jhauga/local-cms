<?php
declare(strict_types=1);
?>
<section class="admin-panel">
    <div class="split-heading">
        <div>
            <p class="eyebrow">Content</p>
            <h1><?= htmlspecialchars($contentLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
        </div>
        <a class="primary-link" href="/admin/<?= htmlspecialchars($currentSection, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/create">Create <?= htmlspecialchars(rtrim($contentLabel, 's'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
    </div>

    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Author</th>
                    <th>Terms</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><a href="/admin/<?= htmlspecialchars($currentSection, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/<?= htmlspecialchars((string) $item['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></td>
                        <td><?= htmlspecialchars((string) $item['slug'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td><span class="status-pill is-<?= htmlspecialchars((string) $item['status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) $item['status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></td>
                        <td><?= htmlspecialchars((string) $item['author_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td>
                            <div class="term-chip-row">
                                <?php foreach ($item['categories'] as $term): ?>
                                    <span class="term-chip is-category"><?= htmlspecialchars((string) $term['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                <?php endforeach; ?>
                                <?php foreach ($item['tags'] as $term): ?>
                                    <span class="term-chip">#<?= htmlspecialchars((string) $term['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars((string) $item['updated_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
