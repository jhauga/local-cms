<?php
declare(strict_types=1);
?>
<section class="admin-panel">
    <div class="split-heading">
        <div>
            <p class="eyebrow">Taxonomies</p>
            <h1>Manage categories and tags</h1>
        </div>
        <div class="button-row">
            <a class="ghost-link" href="/admin/taxonomies/create?taxonomy=category">New category</a>
            <a class="primary-link" href="/admin/taxonomies/create?taxonomy=tag">New tag</a>
        </div>
    </div>

    <?php foreach ($groupedTerms as $taxonomy => $terms): ?>
        <section class="taxonomy-panel block-panel">
            <h2><?= htmlspecialchars(ucfirst($taxonomy), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr><th>Name</th><th>Slug</th><th>Assignments</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($terms as $term): ?>
                            <tr>
                                <td><a href="/admin/taxonomies/<?= htmlspecialchars((string) $term['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) $term['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></td>
                                <td><?= htmlspecialchars((string) $term['slug'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $term['assignment_count'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; ?>
</section>
