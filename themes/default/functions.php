<?php
declare(strict_types=1);

if (!function_exists('theme_format_date')) {
    function theme_format_date(?string $dateTime): string
    {
        if ($dateTime === null || $dateTime === '') {
            return '';
        }

        $date = new DateTimeImmutable($dateTime);

        return $date->format('M j, Y');
    }
}

if (!function_exists('theme_term_url')) {
    function theme_term_url(array $term): string
    {
        $base = ($term['taxonomy'] ?? '') === 'category' ? '/category/' : '/tag/';

        return home_url($base . rawurlencode((string) ($term['slug'] ?? '')));
    }
}

if (!function_exists('localcms_primary_term')) {
    function localcms_primary_term(?array $item = null, string $taxonomy = 'category'): ?array
    {
        $terms = $item !== null
            ? ($taxonomy === 'category' ? ($item['categories'] ?? []) : ($item['tags'] ?? []))
            : get_the_terms(get_post(), $taxonomy);

        return is_array($terms) ? ($terms[0] ?? null) : null;
    }
}

if (!function_exists('localcms_reading_minutes')) {
    function localcms_reading_minutes(?array $item = null): int
    {
        $current = $item ?? get_post();

        return (int) ($current['reading_minutes'] ?? 1);
    }
}
