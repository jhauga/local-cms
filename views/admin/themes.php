<?php
declare(strict_types=1);

$directory = $directory ?? ['available' => false, 'requested' => false, 'browse' => 'popular', 'themes' => [], 'error' => null];
$browseOptions = [
    'popular' => 'Popular',
    'featured' => 'Featured',
    'new' => 'Newest',
    'updated' => 'Recently updated',
];
$activeBrowse = (string) ($directory['browse'] ?? 'popular');
?>
<section class="admin-panel">
    <div class="section-heading">
        <p class="eyebrow">Appearance</p>
        <h1>Themes</h1>
        <p>Switch the active theme for the site. Themes are read from the <code>themes/</code> folder; activating one writes the selection to <code>config.json</code> and takes effect on the next page load. Activation is checked first: a theme that the local runtime cannot render (for example a stock WordPress theme that relies on WordPress core) is declined with an explanation rather than breaking the site.</p>
    </div>

    <?php if (!empty($envLocked)): ?>
        <div class="form-errors">
            <p>The <code>APP_THEME</code> environment variable is set to <code><?= htmlspecialchars((string) $envOverride, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></code> and overrides <code>config.json</code>. Activating a theme here updates <code>config.json</code>, but the site will keep using the environment value until <code>APP_THEME</code> is cleared.</p>
        </div>
    <?php endif; ?>

    <div class="theme-grid">
        <?php foreach ($themes as $theme): ?>
            <?php $isActive = strcasecmp((string) $theme['slug'], (string) $activeTheme) === 0; ?>
            <article class="theme-card<?= $isActive ? ' is-active' : (empty($theme['compatible']) ? ' is-incompatible' : '') ?>">
                <div class="theme-shot">
                    <?php if (!empty($theme['screenshot'])): ?>
                        <img
                            src="/admin/themes/<?= rawurlencode((string) $theme['slug']) ?>/screenshot"
                            alt="<?= htmlspecialchars((string) $theme['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> screenshot"
                            loading="lazy"
                        >
                    <?php else: ?>
                        <span class="theme-shot-empty">No screenshot</span>
                    <?php endif; ?>
                    <?php if ($isActive): ?>
                        <span class="theme-badge">Active</span>
                    <?php endif; ?>
                </div>

                <div class="theme-body">
                    <h2 class="theme-name">
                        <?= htmlspecialchars((string) $theme['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        <?php if (!empty($theme['version'])): ?>
                            <span class="theme-version">v<?= htmlspecialchars((string) $theme['version'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </h2>
                    <?php if (!empty($theme['author'])): ?>
                        <p class="theme-meta">By <?= htmlspecialchars((string) $theme['author'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if (!empty($theme['description'])): ?>
                        <p class="theme-desc"><?= htmlspecialchars((string) $theme['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    <?php endif; ?>

                    <div class="theme-actions">
                        <?php if ($isActive): ?>
                            <span class="theme-active-label">Active theme</span>
                        <?php elseif (!empty($theme['compatible'])): ?>
                            <form method="post" action="/admin/themes/activate">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                <input type="hidden" name="theme" value="<?= htmlspecialchars((string) $theme['slug'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                <button class="primary-button" type="submit">Activate</button>
                            </form>
                        <?php else: ?>
                            <span class="theme-incompatible-label" title="This theme relies on the WordPress runtime and cannot be rendered by the local runtime.">Export &amp; port only</span>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="admin-panel">
    <div class="section-heading">
        <p class="eyebrow">Add themes</p>
        <h1>Browse WordPress.org</h1>
        <p>Browse the public WordPress.org theme directory and download a theme into <code>themes/</code>. Downloaded themes are WordPress-shaped and intended for the WordPress runtime and for porting; most rely on WordPress core, so they cannot be activated as the local runtime theme and activation will decline them.</p>
    </div>

    <?php if (empty($directory['available'])): ?>
        <div class="form-errors">
            <p>Outbound HTTP is unavailable in this PHP build (no cURL and <code>allow_url_fopen</code> is off), so the WordPress.org directory cannot be reached. Add themes by copying them into the <code>themes/</code> folder instead.</p>
        </div>
    <?php else: ?>
        <form class="browse-form" method="get" action="/admin/themes">
            <label class="field-group">
                <span>Browse</span>
                <select name="browse">
                    <?php foreach ($browseOptions as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $activeBrowse === $value ? ' selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="ghost-button" type="submit">Load themes</button>
        </form>

        <?php if (!empty($directory['error'])): ?>
            <div class="form-errors">
                <p><?= htmlspecialchars((string) $directory['error'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($directory['requested']) && empty($directory['error']) && $directory['themes'] === []): ?>
            <p class="help-copy">No themes were returned for that browse filter.</p>
        <?php endif; ?>

        <?php if (!empty($directory['themes'])): ?>
            <div
                class="theme-grid js-directory-grid"
                data-browse="<?= htmlspecialchars($activeBrowse, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                data-per-page="12"
                data-next-page="2"
                data-has-more="<?= !empty($directory['hasMore']) ? '1' : '0' ?>"
                data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            >
                <?php foreach ($directory['themes'] as $remote): ?>
                    <?php
                    $installed = false;
                    foreach ($themes as $local) {
                        if (strcasecmp((string) $local['slug'], (string) $remote['slug']) === 0) {
                            $installed = true;
                            break;
                        }
                    }
                    ?>
                    <article class="theme-card" data-slug="<?= htmlspecialchars((string) $remote['slug'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        <div class="theme-shot">
                            <?php if (!empty($remote['screenshot'])): ?>
                                <img src="<?= htmlspecialchars((string) $remote['screenshot'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) $remote['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> screenshot" loading="lazy">
                            <?php else: ?>
                                <span class="theme-shot-empty">No screenshot</span>
                            <?php endif; ?>
                        </div>
                        <div class="theme-body">
                            <h2 class="theme-name">
                                <?= htmlspecialchars((string) $remote['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                <?php if (!empty($remote['version'])): ?>
                                    <span class="theme-version">v<?= htmlspecialchars((string) $remote['version'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </h2>
                            <?php if (!empty($remote['author'])): ?>
                                <p class="theme-meta">By <?= htmlspecialchars((string) $remote['author'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <div class="theme-actions">
                                <?php if ($installed): ?>
                                    <span class="theme-active-label">Already installed</span>
                                <?php else: ?>
                                    <form method="post" action="/admin/themes/install">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                        <input type="hidden" name="slug" value="<?= htmlspecialchars((string) $remote['slug'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                        <input type="hidden" name="browse" value="<?= htmlspecialchars($activeBrowse, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                        <button class="primary-button" type="submit">Install</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (!empty($remote['preview'])): ?>
                                    <a class="ghost-button" href="<?= htmlspecialchars((string) $remote['preview'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noreferrer">Preview</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="js-directory-status" aria-live="polite">
                <?php if (!empty($directory['hasMore'])): ?>
                    <p class="help-copy js-directory-sentinel">Loading more themes&hellip;</p>
                <?php else: ?>
                    <p class="help-copy">No more themes to load.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<template id="theme-directory-card-template">
    <article class="theme-card">
        <div class="theme-shot">
            <img alt="" loading="lazy" hidden>
            <span class="theme-shot-empty" hidden>No screenshot</span>
        </div>
        <div class="theme-body">
            <h2 class="theme-name">
                <span class="js-theme-name"></span>
                <span class="theme-version" hidden>v<span class="js-theme-version"></span></span>
            </h2>
            <p class="theme-meta" hidden>By <span class="js-theme-author"></span></p>
            <div class="theme-actions">
                <span class="theme-active-label" hidden>Already installed</span>
                <form method="post" action="/admin/themes/install" hidden>
                    <input type="hidden" name="_token" value="">
                    <input type="hidden" name="slug" value="">
                    <input type="hidden" name="browse" value="">
                    <button class="primary-button" type="submit">Install</button>
                </form>
                <a class="ghost-button" href="" target="_blank" rel="noreferrer" hidden>Preview</a>
            </div>
        </div>
    </article>
</template>

<style>
.theme-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1.1rem;
    margin-top: 1rem;
}

.theme-card {
    display: flex;
    flex-direction: column;
    border: 1px solid var(--panel-border);
    border-radius: 1rem;
    overflow: hidden;
    background: #fff;
}

.theme-card.is-active {
    border-color: var(--accent);
    box-shadow: 0 0 0 2px var(--accent-soft);
}

.theme-shot {
    position: relative;
    aspect-ratio: 4 / 3;
    background: var(--cool-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.theme-shot img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.theme-shot-empty {
    color: var(--text-muted);
    font-size: 0.85rem;
}

.theme-badge {
    position: absolute;
    top: 0.6rem;
    left: 0.6rem;
    padding: 0.25rem 0.6rem;
    border-radius: 999px;
    background: var(--accent);
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.theme-body {
    display: grid;
    gap: 0.45rem;
    padding: 1rem;
}

.theme-name {
    margin: 0;
    font-size: 1.05rem;
    display: flex;
    align-items: baseline;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.theme-version {
    font-size: 0.78rem;
    font-weight: 500;
    color: var(--text-muted);
}

.theme-meta {
    margin: 0;
    font-size: 0.84rem;
    color: var(--text-muted);
}

.theme-desc {
    margin: 0;
    font-size: 0.88rem;
    line-height: 1.5;
    color: var(--text-main);
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.theme-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    margin-top: 0.4rem;
}

.theme-active-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--accent);
}

.theme-incompatible-label {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-muted);
    padding: 0.25rem 0.6rem;
    border: 1px dashed var(--panel-border);
    border-radius: 999px;
}

.theme-card.is-incompatible .theme-shot {
    filter: grayscale(0.6);
    opacity: 0.85;
}

.browse-form {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: end;
    margin-bottom: 0.75rem;
}

.browse-form .field-group {
    max-width: 16rem;
}
</style>

<script>
(function () {
    var grid = document.querySelector('.js-directory-grid');

    if (!grid || !('IntersectionObserver' in window) || !('fetch' in window)) {
        return;
    }

    var status = document.querySelector('.js-directory-status');
    var sentinel = status ? status.querySelector('.js-directory-sentinel') : null;
    var template = document.getElementById('theme-directory-card-template');

    if (!template || !sentinel) {
        return;
    }

    var loading = false;

    function setStatus(message, exhausted) {
        if (!status) {
            return;
        }

        status.innerHTML = '';

        var paragraph = document.createElement('p');
        paragraph.className = 'help-copy' + (exhausted ? '' : ' js-directory-sentinel');
        paragraph.textContent = message;
        status.appendChild(paragraph);

        if (!exhausted) {
            sentinel = paragraph;
            observer.observe(sentinel);
        } else {
            sentinel = null;
        }
    }

    function renderCard(theme) {
        var fragment = template.content.cloneNode(true);
        var article = fragment.querySelector('.theme-card');
        article.dataset.slug = theme.slug || '';

        var img = article.querySelector('.theme-shot img');
        var empty = article.querySelector('.theme-shot-empty');

        if (theme.screenshot) {
            img.src = theme.screenshot;
            img.alt = (theme.name || '') + ' screenshot';
            img.hidden = false;
        } else {
            empty.hidden = false;
        }

        article.querySelector('.js-theme-name').textContent = theme.name || '';

        if (theme.version) {
            var versionWrap = article.querySelector('.theme-version');
            versionWrap.hidden = false;
            article.querySelector('.js-theme-version').textContent = theme.version;
        }

        if (theme.author) {
            var meta = article.querySelector('.theme-meta');
            meta.hidden = false;
            article.querySelector('.js-theme-author').textContent = theme.author;
        }

        var actions = article.querySelector('.theme-actions');
        var installedLabel = actions.querySelector('.theme-active-label');
        var form = actions.querySelector('form');
        var previewLink = actions.querySelector('.ghost-button');

        if (theme.installed) {
            installedLabel.hidden = false;
        } else {
            form.hidden = false;
            form.querySelector('input[name="_token"]').value = grid.dataset.csrf || '';
            form.querySelector('input[name="slug"]').value = theme.slug || '';
            form.querySelector('input[name="browse"]').value = grid.dataset.browse || 'popular';
        }

        if (theme.preview) {
            previewLink.hidden = false;
            previewLink.href = theme.preview;
        } else {
            previewLink.parentNode.removeChild(previewLink);
        }

        return article;
    }

    function loadNextPage() {
        if (loading || grid.dataset.hasMore !== '1') {
            return;
        }

        loading = true;

        var browse = grid.dataset.browse || 'popular';
        var perPage = grid.dataset.perPage || '12';
        var page = grid.dataset.nextPage || '2';

        var url = '/admin/themes/browse.json?browse=' + encodeURIComponent(browse)
            + '&per_page=' + encodeURIComponent(perPage)
            + '&page=' + encodeURIComponent(page);

        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                return response.json();
            })
            .then(function (payload) {
                if (payload && payload.error) {
                    grid.dataset.hasMore = '0';
                    setStatus(payload.error, true);
                    return;
                }

                var themes = (payload && payload.themes) || [];

                themes.forEach(function (theme) {
                    if (!theme || !theme.slug) {
                        return;
                    }

                    if (grid.querySelector('[data-slug="' + (window.CSS && CSS.escape ? CSS.escape(theme.slug) : theme.slug) + '"]')) {
                        return;
                    }

                    grid.appendChild(renderCard(theme));
                });

                grid.dataset.nextPage = String(parseInt(page, 10) + 1);
                grid.dataset.hasMore = payload && payload.hasMore ? '1' : '0';

                if (grid.dataset.hasMore !== '1') {
                    setStatus('No more themes to load.', true);
                }
            })
            .catch(function () {
                grid.dataset.hasMore = '0';
                setStatus('Could not load more themes.', true);
            })
            .then(function () {
                loading = false;
            });
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                loadNextPage();
            }
        });
    }, { rootMargin: '200px 0px' });

    observer.observe(sentinel);
})();
</script>
