<?php
declare(strict_types=1);

$item = is_array($post ?? null) ? $post : (is_array($item ?? null) ? $item : get_post());

if ($item === null) {
    return;
}

$primaryCategory = localcms_primary_term($item, 'category');
?>
<article <?php post_class(['story-card'], $item); ?>>
    <a class="story-link-wrap" href="<?= esc_url(get_permalink($item)) ?>">
        <div class="story-art<?= has_post_thumbnail($item) ? ' has-image' : '' ?>">
            <?php if (has_post_thumbnail($item)): ?>
                <img src="<?= esc_url(get_the_post_thumbnail_url($item)) ?>" alt="<?= esc_attr(localcms_compact_markdown_text(get_the_title($item))) ?>">
            <?php else: ?>
                <span class="story-art-label"><?= esc_html((string) ($primaryCategory['name'] ?? ucfirst(get_post_type($item)))) ?></span>
                <span class="story-art-meta">Portable archive card</span>
            <?php endif; ?>
        </div>

        <div class="story-copy">
            <div class="meta-row">
                <?php if (get_the_date('M j, Y', $item) !== ''): ?>
                    <p class="story-meta"><?= esc_html(get_the_date('M j, Y', $item)) ?></p>
                <?php endif; ?>
                <p class="story-meta"><?= esc_html((string) localcms_reading_minutes($item)) ?> min read</p>
            </div>
            <h3 class="story-title"><?= localcms_render_compact_markdown(get_the_title($item)) ?></h3>
            <?php $excerpt = localcms_display_excerpt($item, 'post'); ?>
            <?php if ($excerpt !== ''): ?>
                <p class="story-excerpt"><?= esc_html($excerpt) ?></p>
            <?php endif; ?>
        </div>
    </a>

    <?php get_template_part('template-parts/term-pills', null, ['item' => $item]); ?>
</article>
