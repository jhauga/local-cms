<?php
declare(strict_types=1);

$item = is_array($item ?? null) ? $item : get_post();

if ($item === null) {
    return;
}

// WordPress names the tag taxonomy 'post_tag'; local-cms uses 'tag'.
$tagTaxonomy = defined('ABSPATH') ? 'post_tag' : 'tag';

// get_the_terms() returns array|false|WP_Error under WordPress and yields raw
// WP_Term objects; normalise the result (and each member) to the array shape the
// markup below reads, so $term['name'] never touches a WP_Term directly.
$categories = get_the_terms($item, 'category');
$tags = get_the_terms($item, $tagTaxonomy);
$categories = is_array($categories) ? array_values(array_filter(array_map('localcms_normalize_term', $categories))) : [];
$tags = is_array($tags) ? array_values(array_filter(array_map('localcms_normalize_term', $tags))) : [];

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