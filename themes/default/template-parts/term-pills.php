<?php
declare(strict_types=1);

$item = is_array($item ?? null) ? $item : get_post();

if ($item === null) {
    return;
}

// WordPress names the tag taxonomy 'post_tag'; local-cms uses 'tag'.
$tagTaxonomy = defined('ABSPATH') ? 'post_tag' : 'tag';

// get_the_terms() returns array|false|WP_Error under WordPress; normalise both.
$categories = get_the_terms($item, 'category');
$tags = get_the_terms($item, $tagTaxonomy);
$categories = is_array($categories) ? $categories : [];
$tags = is_array($tags) ? $tags : [];

if ($categories === [] && $tags === []) {
    return;
}
?>
<div class="term-list<?= !empty($compact) ? ' compact-terms' : '' ?>">
    <?php foreach ($categories as $term): ?>
        <a class="term-pill is-category" href="<?= esc_url(theme_term_url($term)) ?>">
            <?= esc_html((string) $term['name']) ?>
        </a>
    <?php endforeach; ?>
    <?php foreach ($tags as $term): ?>
        <a class="term-pill" href="<?= esc_url(theme_term_url($term)) ?>">
            #<?= esc_html((string) $term['name']) ?>
        </a>
    <?php endforeach; ?>
</div>