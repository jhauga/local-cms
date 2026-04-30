<?php
declare(strict_types=1);

$formAction = $isNew
    ? '/admin/' . $currentSection . '/create'
    : '/admin/' . $currentSection . '/' . $item['id'];
$publishedAtValue = '';
$markdownMathEnabled = !empty($item['markdown_math']);
$markedRendererEnabled = !empty($item['use_marked']);

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

    <form class="admin-form" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($formAction, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <div class="field-grid two-up">
            <label class="field-group">
                <span>Title</span>
                <input type="text" name="title" value="<?= htmlspecialchars((string) $item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <span class="field-hint">Compact title markdown supports <code>`code`</code>, <code>*italic*</code>, <code>**bold**</code>, <code>~~strike~~</code>, <code>&lt;sub&gt;</code>, <code>&lt;sup&gt;</code>, and <code>&lt;ins&gt;</code>.</span>
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

        <section class="block-panel editor-panel" data-markdown-editor data-preview-endpoint="/admin/preview/markdown">
            <div class="editor-heading">
                <div>
                    <h2>Markdown Body</h2>
                    <p class="help-copy">Write in Markdown, then switch to Preview to render the body with the CMS Markdown pipeline.</p>
                </div>
                <div class="editor-toggle" role="tablist" aria-label="Editor mode">
                    <button class="editor-tab is-active" type="button" role="tab" aria-selected="true" data-editor-tab="write">Write</button>
                    <button class="editor-tab" type="button" role="tab" aria-selected="false" data-editor-tab="preview">Preview</button>
                </div>
            </div>

            <div class="editor-surface">
                <div class="editor-write-surface" data-editor-panel="write">
                    <textarea class="code-textarea" name="body_markdown" rows="14" data-editor-input><?= htmlspecialchars((string) $item['body_markdown'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
                </div>

                <section class="markdown-preview" data-editor-panel="preview" hidden>
                    <div class="markdown-preview-body" data-editor-preview>
                        <p class="preview-empty">Nothing to preview yet.</p>
                    </div>
                </section>
            </div>
        </section>

        <section class="block-panel">
            <h2>Markdown Options</h2>
            <label class="checkbox-row checkbox-row--start">
                <input type="checkbox" name="markdown_math" value="1"<?= $markdownMathEnabled ? ' checked' : '' ?>>
                <span>Markdown Math</span>
            </label>
            <p class="help-copy">Adds MathJax to the published page or post head so `\(...\)` and `\[...\]` expressions render on the frontend.</p>

            <label class="checkbox-row checkbox-row--start">
                <input type="checkbox" name="use_marked" value="1"<?= $markedRendererEnabled ? ' checked' : '' ?>>
                <span>Use marked.js Renderer</span>
            </label>
            <p class="help-copy">Loads the richer client-side Markdown renderer for this page or post instead of the default built-in converter.</p>
        </section>

        <div class="field-grid two-up">
            <label class="field-group">
                <span>Meta Title</span>
                <input type="text" name="meta_title" value="<?= htmlspecialchars((string) $item['meta_title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            </label>
            <label class="field-group">
                <span>Featured Image URL</span>
                <input type="text" name="featured_image" value="<?= htmlspecialchars((string) $item['featured_image'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <span class="field-hint">Use an external URL or a local path under `/uploads/`, such as `/uploads/2026/04/example.png`.</span>
            </label>
        </div>

        <section class="block-panel">
            <div class="split-heading split-heading--compact">
                <div>
                    <p class="eyebrow">Media</p>
                    <h2>Image Upload</h2>
                </div>
            </div>

            <div class="field-grid two-up media-upload-grid">
                <label class="field-group">
                    <span>Upload Image</span>
                    <input type="file" name="featured_image_upload" accept="image/jpeg,image/png,image/gif,image/webp,image/avif">
                    <span class="field-hint">Uploads are stored in `/uploads/` with configurable date folders. The default path is `/uploads/YYYY/MM/`, and static exports copy the same structure automatically.</span>
                </label>

                <?php if ((string) ($item['featured_image'] ?? '') !== ''): ?>
                    <div class="media-preview-card">
                        <span class="media-preview-label">Current image</span>
                        <img src="<?= htmlspecialchars((string) $item['featured_image'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) $item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        <p class="help-copy"><?= htmlspecialchars((string) $item['featured_image'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    </div>
                <?php else: ?>
                    <div class="media-preview-card is-empty">
                        <span class="media-preview-label">Current image</span>
                        <p class="help-copy">No featured image selected yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

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

    <script src="/assets/convert.js"></script>

    <?php if (!$isNew): ?>
        <form class="inline-form" method="post" action="/admin/<?= htmlspecialchars($currentSection, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/<?= htmlspecialchars((string) $item['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/delete">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <button class="danger-button" type="submit">Delete</button>
        </form>
    <?php endif; ?>
</section>

<script>
(() => {
    const editor = document.querySelector('[data-markdown-editor]');

    if (!editor) {
        return;
    }

    const form = editor.closest('form');
    const writeTab = editor.querySelector('[data-editor-tab="write"]');
    const previewTab = editor.querySelector('[data-editor-tab="preview"]');
    const writePanel = editor.querySelector('[data-editor-panel="write"]');
    const previewPanel = editor.querySelector('[data-editor-panel="preview"]');
    const previewBody = editor.querySelector('[data-editor-preview]');
    const textarea = editor.querySelector('[data-editor-input]');
    const tokenInput = form?.querySelector('input[name="_token"]');
    const mathCheckbox = form?.querySelector('input[name="markdown_math"]');
    const markedCheckbox = form?.querySelector('input[name="use_marked"]');
    const clientPreviewEnabled = <?= !empty($clientConverterEnabled) ? 'true' : 'false' ?>;
    const previewEndpoint = editor.getAttribute('data-preview-endpoint') || '/admin/preview/markdown';
    let debounceHandle = 0;
    let renderSequence = 0;
    let mathJaxPromise = null;

    const setMode = (mode) => {
        const isPreview = mode === 'preview';

        writePanel.hidden = isPreview;
        previewPanel.hidden = !isPreview;
        writeTab.classList.toggle('is-active', !isPreview);
        previewTab.classList.toggle('is-active', isPreview);
        writeTab.setAttribute('aria-selected', isPreview ? 'false' : 'true');
        previewTab.setAttribute('aria-selected', isPreview ? 'true' : 'false');

        if (isPreview) {
            renderPreview();
        }
    };

    const ensureMathJax = async () => {
        if (!mathCheckbox?.checked) {
            return;
        }

        if (window.MathJax && typeof window.MathJax.typesetPromise === 'function') {
            return;
        }

        if (mathJaxPromise) {
            await mathJaxPromise;
            return;
        }

        window.MathJax = {
            tex: {
                inlineMath: [['\\(', '\\)']],
                displayMath: [['\\[', '\\]']]
            },
            options: {
                skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code']
            }
        };

        mathJaxPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.id = 'localcms-mathjax-preview';
            script.async = true;
            script.src = 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js';
            script.addEventListener('load', resolve, { once: true });
            script.addEventListener('error', () => reject(new Error('MathJax failed to load.')), { once: true });
            document.head.appendChild(script);
        });

        await mathJaxPromise;
    };

    const renderPreview = async () => {
        const markdown = textarea?.value.trim() || '';
        const sequence = ++renderSequence;
        const hasTemplateSyntax = /<!--\s*html:(?:template=|begin)\b/i.test(textarea?.value || '');

        if (markdown === '') {
            previewBody.innerHTML = '<p class="preview-empty">Nothing to preview yet.</p>';
            return;
        }

        previewBody.innerHTML = '<p class="preview-status">Rendering preview...</p>';

        if (window.MarkdownConverter?.toHtml && (clientPreviewEnabled || markedCheckbox?.checked || hasTemplateSyntax)) {
            try {
                const html = await window.MarkdownConverter.toHtml(markdown, {
                    renderer: markedCheckbox?.checked ? 'marked' : 'default'
                });

                if (sequence !== renderSequence) {
                    return;
                }

                previewBody.innerHTML = html;

                if (mathCheckbox?.checked) {
                    await ensureMathJax();

                    if (window.MathJax && typeof window.MathJax.typesetPromise === 'function') {
                        await window.MathJax.typesetPromise([previewBody]);
                    }
                }

                return;
            } catch (error) {
                console.warn('Markdown client preview failed, falling back to the server preview.', error);
            }
        }

        try {
            const formData = new FormData();
            formData.append('_token', tokenInput?.value || '');
            formData.append('body_markdown', textarea?.value || '');

            const response = await fetch(previewEndpoint, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const html = await response.text();

            if (sequence !== renderSequence) {
                return;
            }

            previewBody.innerHTML = html;

            if (mathCheckbox?.checked) {
                await ensureMathJax();

                if (window.MathJax && typeof window.MathJax.typesetPromise === 'function') {
                    await window.MathJax.typesetPromise([previewBody]);
                }
            }
        } catch (error) {
            previewBody.innerHTML = '<p class="preview-status is-error">Preview could not be rendered.</p>';
        }
    };

    const schedulePreview = () => {
        window.clearTimeout(debounceHandle);
        debounceHandle = window.setTimeout(renderPreview, 220);
    };

    writeTab.addEventListener('click', () => setMode('write'));
    previewTab.addEventListener('click', () => setMode('preview'));
    textarea?.addEventListener('input', () => {
        if (!previewPanel.hidden) {
            schedulePreview();
        }
    });
    mathCheckbox?.addEventListener('change', () => {
        if (!previewPanel.hidden) {
            renderPreview();
        }
    });
    markedCheckbox?.addEventListener('change', () => {
        if (!previewPanel.hidden) {
            renderPreview();
        }
    });
})();
</script>
