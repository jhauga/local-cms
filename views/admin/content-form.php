<?php
declare(strict_types=1);

$formAction = $isNew
    ? '/admin/' . $currentSection . '/create'
    : '/admin/' . $currentSection . '/' . $item['id'];
$publishedAtValue = '';

if (!empty($item['published_at'])) {
    $publishedAtValue = str_replace(' ', 'T', substr((string) $item['published_at'], 0, 16));
}
?>
<section class="admin-panel">
    <div class="split-heading">
        <div>
            <p class="eyebrow">Editor</p>
            <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
        </div>
        <a class="ghost-link" href="/admin/<?= htmlspecialchars($currentSection, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Back to list</a>
    </div>

    <?php if ($errors !== []): ?>
        <div class="form-errors">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="admin-form" method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <div class="field-grid two-up">
            <label class="field-group">
                <span>Title</span>
                <input type="text" name="title" value="<?= htmlspecialchars((string) $item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            </label>
            <label class="field-group">
                <span>Slug</span>
                <input type="text" name="slug" value="<?= htmlspecialchars((string) $item['slug'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            </label>
        </div>

        <div class="field-grid three-up">
            <label class="field-group">
                <span>Status</span>
                <select name="status">
                    <option value="draft"<?= ($item['status'] ?? '') === 'draft' ? ' selected' : '' ?>>Draft</option>
                    <option value="published"<?= ($item['status'] ?? '') === 'published' ? ' selected' : '' ?>>Published</option>
                </select>
            </label>
            <label class="field-group">
                <span>Publish At</span>
                <input type="datetime-local" name="published_at" value="<?= htmlspecialchars($publishedAtValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            </label>
            <?php if ($contentType === 'page'): ?>
                <label class="field-group">
                    <span>Sort Order</span>
                    <input type="number" name="sort_order" value="<?= htmlspecialchars((string) $item['sort_order'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                </label>
            <?php endif; ?>
        </div>

        <?php if ($contentType === 'page'): ?>
            <label class="field-group">
                <span>Template</span>
                <input type="text" name="template" value="<?= htmlspecialchars((string) $item['template'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            </label>
        <?php endif; ?>

        <label class="field-group">
            <span>Excerpt</span>
            <textarea name="excerpt" rows="3"><?= htmlspecialchars((string) $item['excerpt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
        </label>

        <label class="field-group">
            <span>Markdown Body</span>
            <textarea class="code-textarea" name="body_markdown" rows="14"><?= htmlspecialchars((string) $item['body_markdown'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
        </label>

        <div class="field-grid two-up">
            <label class="field-group">
                <span>Meta Title</span>
                <input type="text" name="meta_title" value="<?= htmlspecialchars((string) $item['meta_title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            </label>
            <label class="field-group">
                <span>Featured Image URL</span>
                <input type="text" name="featured_image" value="<?= htmlspecialchars((string) $item['featured_image'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            </label>
        </div>

        <label class="field-group">
            <span>Meta Description</span>
            <textarea name="meta_description" rows="3"><?= htmlspecialchars((string) $item['meta_description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
        </label>

        <div class="taxonomy-grid">
            <?php foreach ($termGroups as $taxonomy => $terms): ?>
                <section class="taxonomy-panel">
                    <h2><?= htmlspecialchars(ucfirst($taxonomy), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
                    <div class="checkbox-group">
                        <?php foreach ($terms as $term): ?>
                            <label class="checkbox-row">
                                <input
                                    type="checkbox"
                                    name="term_ids[]"
                                    value="<?= htmlspecialchars((string) $term['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                    <?= in_array((int) $term['id'], $item['term_ids'] ?? [], true) ? ' checked' : '' ?>
                                >
                                <span><?= htmlspecialchars((string) $term['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <div class="form-actions">
            <button class="primary-button" type="submit">Save <?= htmlspecialchars($contentLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
        </div>
    </form>

    <?php if (!$isNew): ?>
        <form class="inline-form" method="post" action="/admin/<?= htmlspecialchars($currentSection, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/<?= htmlspecialchars((string) $item['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/delete">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <button class="danger-button" type="submit">Delete</button>
        </form>
    <?php endif; ?>
</section>
