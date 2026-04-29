<?php
declare(strict_types=1);

$termAction = $isNew ? '/admin/taxonomies/create' : '/admin/taxonomies/' . $term['id'];
?>
<section class="admin-panel">
    <div class="split-heading">
        <div>
            <p class="eyebrow">Taxonomy Editor</p>
            <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
        </div>
        <a class="ghost-link" href="/admin/taxonomies">Back to terms</a>
    </div>

    <?php if ($errors !== []): ?>
        <div class="form-errors">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="admin-form" method="post" action="<?= htmlspecialchars($termAction, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <div class="field-grid two-up">
            <label class="field-group">
                <span>Taxonomy</span>
                <select name="taxonomy">
                    <option value="category"<?= ($term['taxonomy'] ?? '') === 'category' ? ' selected' : '' ?>>Category</option>
                    <option value="tag"<?= ($term['taxonomy'] ?? '') === 'tag' ? ' selected' : '' ?>>Tag</option>
                </select>
            </label>
            <label class="field-group">
                <span>Name</span>
                <input type="text" name="name" value="<?= htmlspecialchars((string) ($term['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            </label>
        </div>

        <label class="field-group">
            <span>Slug</span>
            <input type="text" name="slug" value="<?= htmlspecialchars((string) ($term['slug'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        </label>

        <label class="field-group">
            <span>Description</span>
            <textarea name="description" rows="4"><?= htmlspecialchars((string) ($term['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
        </label>

        <div class="form-actions">
            <button class="primary-button" type="submit">Save term</button>
        </div>
    </form>

    <?php if (!$isNew): ?>
        <form class="inline-form" method="post" action="/admin/taxonomies/<?= htmlspecialchars((string) $term['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/delete">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <button class="danger-button" type="submit">Delete</button>
        </form>
    <?php endif; ?>
</section>
