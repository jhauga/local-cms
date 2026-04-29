<?php
declare(strict_types=1);
?>
<section class="admin-panel auth-panel">
    <div class="section-heading">
        <p class="eyebrow">Admin Access</p>
        <h1>Sign in</h1>
        <p>Use the seeded admin account to manage content, taxonomies, and site settings.</p>
    </div>

    <?php if ($errors !== []): ?>
        <div class="form-errors">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="admin-form" method="post" action="/admin/login">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <label class="field-group">
            <span>Email</span>
            <input type="email" name="email" value="<?= htmlspecialchars((string) ($values['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" autocomplete="username">
        </label>

        <label class="field-group">
            <span>Password</span>
            <input type="password" name="password" autocomplete="current-password">
        </label>

        <button class="primary-button" type="submit">Sign in</button>
        <p class="help-copy">Default seeded login: <strong>admin@example.com</strong> with password <strong>LocalCMS123!</strong></p>
    </form>
</section>
