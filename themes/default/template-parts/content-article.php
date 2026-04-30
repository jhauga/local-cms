<?php
declare(strict_types=1);

$item = is_array($item ?? null) ? $item : get_post();

if ($item === null) {
    return;
}

$kind = (string) ($kind ?? get_post_type($item));
$primaryCategory = localcms_primary_term($item, 'category');
?>
<article <?php post_class(['content-panel', 'entry-shell'], $item); ?>>
    <div class="entry-grid">
        <div class="entry-main">
            <aside class="entry-aside">
                <div class="entry-figure<?= has_post_thumbnail($item) ? ' has-image' : '' ?>">
                    <?php if (has_post_thumbnail($item)): ?>
                        <img src="<?= esc_url(get_the_post_thumbnail_url($item)) ?>" alt="<?= esc_attr(localcms_compact_markdown_text(get_the_title($item))) ?>">
                    <?php else: ?>
                        <span class="entry-figure-kicker"><?= esc_html((string) ($primaryCategory['name'] ?? ucfirst($kind))) ?></span>
                        <strong><?= localcms_render_compact_markdown(get_the_title($item)) ?></strong>
                        <span><?= esc_html($kind === 'post' ? 'Portable single template' : 'Portable page template') ?></span>
                    <?php endif; ?>
                </div>

                <div class="entry-side-card">
                    <p class="hero-card-label">Compatibility notes</p>
                    <ul class="feature-list compact-list">
                        <li>Uses get_header() and get_footer()</li>
                        <li>Uses shared template parts for terms and entries</li>
                        <li>Uses body_class() and post_class() hooks</li>
                    </ul>
                </div>
            </aside>

            <p class="eyebrow"><?= esc_html($kind === 'page' ? 'Page' : 'Post') ?></p>
            <h1><?= localcms_render_compact_markdown(get_the_title($item)) ?></h1>
            <div class="meta-row">
                <?php if ($kind === 'post' && get_the_date('M j, Y', $item) !== ''): ?>
                    <p class="story-meta"><?= esc_html(get_the_date('M j, Y', $item)) ?></p>
                <?php endif; ?>
                <p class="story-meta">By <?= esc_html(get_the_author($item)) ?></p>
                <p class="story-meta"><?= esc_html((string) localcms_reading_minutes($item)) ?> min read</p>
            </div>

            <?php if (get_the_excerpt($item) !== ''): ?>
                <p class="lead"><?= esc_html(get_the_excerpt($item)) ?></p>
            <?php endif; ?>

            <?php get_template_part('template-parts/term-pills', null, ['item' => $item]); ?>

            <div class="prose">
                <?php the_content($item); ?>
            </div>
        </div>
    </div>
</article>