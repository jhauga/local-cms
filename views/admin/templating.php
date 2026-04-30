<?php
declare(strict_types=1);
?>
<section class="admin-panel">
    <div class="section-heading">
        <p class="eyebrow">Markdown Templates</p>
        <h1>Templating</h1>
        <p>Create reusable HTML wrapper snippets for markdown conversion. Use <code>{__markdown__}</code> where the authored markdown should be inserted, and use a <code>local-cms</code> fenced block when you need to show raw template syntax in docs.</p>
    </div>

    <?php if ($errors !== []): ?>
        <div class="form-errors">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="admin-form" method="post" action="/admin/templating" data-template-form>
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <section class="block-panel">
            <h2>Usage</h2>
            <p class="help-copy">Save a template here, then call it in markdown with <code>&lt;!-- html:template=template-name --&gt;</code>. Template snippets can also use wrapper comments like <code>&lt;!-- html:begin --&gt;</code> and sibling element comments.</p>
        </section>

        <div class="template-list" data-template-list>
            <?php foreach ($templates as $template): ?>
                <section class="block-panel template-card" data-template-row>
                    <div class="template-card-header">
                        <div>
                            <h2>Template Snippet</h2>
                            <p class="help-copy">Names should stay short and stable because authors will reference them directly in markdown comments.</p>
                        </div>
                        <button class="ghost-button" type="button" data-remove-template>Remove</button>
                    </div>

                    <div class="field-grid two-up">
                        <label class="field-group">
                            <span>Template Name</span>
                            <input type="text" name="template_names[]" value="<?= htmlspecialchars((string) ($template['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" placeholder="interactive">
                        </label>

                        <label class="field-group">
                            <span>Author Call</span>
                            <input type="text" value="&lt;!-- html:template=<?= htmlspecialchars((string) ($template['name'] ?? 'template-name'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> --&gt;" readonly>
                        </label>
                    </div>

                    <label class="field-group">
                        <span>HTML Snippet</span>
                        <textarea class="code-textarea" name="template_markups[]" rows="8" placeholder="&lt;!-- html:begin --&gt;&#10;&lt;!-- section.example-template --&gt;&#10;{__markdown__}&#10;&lt;!-- html:end --&gt;"><?= htmlspecialchars((string) ($template['markup'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
                    </label>
                </section>
            <?php endforeach; ?>
        </div>

        <template id="markdown-template-row-template">
            <section class="block-panel template-card" data-template-row>
                <div class="template-card-header">
                    <div>
                        <h2>Template Snippet</h2>
                        <p class="help-copy">Use <code>{__markdown__}</code> at least once so authored markdown has an insertion point.</p>
                    </div>
                    <button class="ghost-button" type="button" data-remove-template>Remove</button>
                </div>

                <div class="field-grid two-up">
                    <label class="field-group">
                        <span>Template Name</span>
                        <input type="text" name="template_names[]" value="" placeholder="callout">
                    </label>

                    <label class="field-group">
                        <span>Author Call</span>
                        <input type="text" value="&lt;!-- html:template=template-name --&gt;" readonly>
                    </label>
                </div>

                <label class="field-group">
                    <span>HTML Snippet</span>
                    <textarea class="code-textarea" name="template_markups[]" rows="8" placeholder="&lt;!-- html:begin --&gt;&#10;&lt;!-- section.example-template --&gt;&#10;{__markdown__}&#10;&lt;!-- html:end --&gt;"></textarea>
                </label>
            </section>
        </template>

        <div class="button-row">
            <button class="ghost-button" type="button" data-add-template>Add template</button>
            <button class="primary-button" type="submit">Save templates</button>
        </div>
    </form>
</section>

<script>
(() => {
    const form = document.querySelector('[data-template-form]');

    if (!form) {
        return;
    }

    const list = form.querySelector('[data-template-list]');
    const rowTemplate = document.getElementById('markdown-template-row-template');

    form.addEventListener('click', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.matches('[data-add-template]')) {
            event.preventDefault();

            if (!list || !rowTemplate) {
                return;
            }

            list.appendChild(rowTemplate.content.cloneNode(true));
            return;
        }

        if (!target.matches('[data-remove-template]')) {
            return;
        }

        event.preventDefault();

        const row = target.closest('[data-template-row]');

        if (!(row instanceof HTMLElement)) {
            return;
        }

        if (list && list.querySelectorAll('[data-template-row]').length <= 1) {
            const nameInput = row.querySelector('input[name="template_names[]"]');
            const markupInput = row.querySelector('textarea[name="template_markups[]"]');

            if (nameInput instanceof HTMLInputElement) {
                nameInput.value = '';
            }

            if (markupInput instanceof HTMLTextAreaElement) {
                markupInput.value = '';
            }

            return;
        }

        row.remove();
    });
})();
</script>