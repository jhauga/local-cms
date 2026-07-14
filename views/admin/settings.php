<?php
declare(strict_types=1);
?>
<section class="admin-panel">
    <div class="section-heading">
        <p class="eyebrow">Configuration</p>
        <h1>Site Settings</h1>
        <p>Update the shared site identity used by the frontend and admin area.</p>
    </div>

    <?php if ($errors !== []): ?>
        <div class="form-errors">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="admin-form" method="post" action="/admin/settings">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <label class="field-group">
            <span>Site Name</span>
            <input type="text" name="site_name" value="<?= htmlspecialchars((string) $settings['site_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        </label>

        <label class="field-group">
            <span>Site Tagline</span>
            <textarea name="site_tagline" rows="3"><?= htmlspecialchars((string) $settings['site_tagline'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
        </label>

        <label class="field-group">
            <span>Default Content Type</span>
            <select name="default_content_type">
                <option value="post"<?= ($settings['default_content_type'] ?? 'post') === 'post' ? ' selected' : '' ?>>Post</option>
                <option value="page"<?= ($settings['default_content_type'] ?? 'post') === 'page' ? ' selected' : '' ?>>Page</option>
            </select>
        </label>
        <p class="help-copy">Used when a running markdown import cannot resolve an item's type from its metadata or the document top-matter.</p>

        <button class="primary-button" type="submit">Save settings</button>
    </form>
</section>
