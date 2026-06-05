<?php
declare(strict_types=1);

// Loaded by get_search_form() under WordPress and via get_template_part('searchform')
// in the Local CMS runtime. get_search_query() is WordPress-only, so guard it.
$searchQuery = function_exists('get_search_query') ? get_search_query() : '';
?>
<form class="search-form" role="search" method="get" action="<?= esc_url(home_url('/')) ?>">
    <label class="visually-hidden" for="search-field">Search</label>
    <input
        type="search"
        id="search-field"
        class="search-field"
        name="s"
        value="<?= esc_attr($searchQuery) ?>"
        placeholder="Search the site"
    >
    <button type="submit" class="search-submit">Search</button>
</form>
