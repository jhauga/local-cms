<?php
declare(strict_types=1);
?>
<section class="admin-panel">
    <div class="section-heading">
        <p class="eyebrow">Content</p>
        <h1>Import Running Markdown</h1>
        <p>Split one markdown document into pages and posts. Separate items with a <code>---</code> line surrounded by empty lines, and describe each item with an optional <code>metadata</code> fenced code block.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="form-errors">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($warnings)): ?>
        <div class="notice-banner">
            <?php foreach ($warnings as $warning): ?>
                <p><?= htmlspecialchars((string) $warning, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($report)): ?>
        <div class="section-heading">
            <h2><?= !empty($dryRun) ? 'Dry Run Preview' : 'Import Results' ?></h2>
            <p>
                <?= (int) $report['created'] ?> <?= !empty($dryRun) ? 'to create' : 'created' ?>,
                <?= (int) $report['updated'] ?> <?= !empty($dryRun) ? 'to update' : 'updated' ?>.
                Keywords are parsed for future metadata support and are not stored yet.
            </p>
        </div>

        <?php if ($report['items'] !== []): ?>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Slug</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Terms</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report['items'] as $item): ?>
                            <tr>
                                <td>
                                    <?php if (isset($item['id'])): ?>
                                        <a href="/admin/<?= $item['type'] === 'page' ? 'pages' : 'posts' ?>/<?= htmlspecialchars((string) $item['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
                                    <?php else: ?>
                                        <?= htmlspecialchars((string) $item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars((string) $item['slug'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $item['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                                <td><span class="status-pill is-<?= htmlspecialchars((string) $item['status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) $item['status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></td>
                                <td>
                                    <div class="term-chip-row">
                                        <?php foreach ($item['categories'] as $term): ?>
                                            <span class="term-chip is-category"><?= htmlspecialchars((string) $term, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                        <?php endforeach; ?>
                                        <?php foreach ($item['tags'] as $term): ?>
                                            <span class="term-chip">#<?= htmlspecialchars((string) $term, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars((string) $item['action'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <form class="admin-form" method="post" action="/admin/import" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <label class="field-group">
            <span>Running Markdown</span>
            <textarea name="running_markdown" rows="16" placeholder="---&#10;type: mix&#10;default-type: post&#10;---&#10;&#10;# First Post&#10;&#10;Body content.&#10;&#10;---&#10;&#10;# Second Post"><?= htmlspecialchars((string) ($source ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
        </label>

        <label class="field-group">
            <span>Or upload a markdown file</span>
            <input type="file" name="running_markdown_file" accept=".md,.markdown,text/markdown,text/plain">
        </label>

        <label class="checkbox-row checkbox-row--start">
            <input type="checkbox" name="dry_run" value="1"<?= !empty($dryRun) ? ' checked' : '' ?>>
            <span>Dry run</span>
        </label>
        <p class="help-copy">Preview what would be created or updated without saving anything.</p>

        <button class="primary-button" type="submit">Import</button>
    </form>
</section>
