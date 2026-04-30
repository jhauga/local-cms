UPDATE pages
SET body_markdown = REPLACE(
    body_markdown,
    'When frontmatter includes an `examples:` base URL, relative links with slashes are rewritten into source links such as [the page template](themes/default/page.php) and [the converter asset](public/assets/convert.js).',
    'When frontmatter includes an `examples:` base URL, relative links with slashes are rewritten into source links such as [the page template](themes/default/page.php) and [the converter asset](public/assets/convert.js).

```local-cms
[the page template](themes/default/page.php)

<!-- and -->

[the converter asset](public/assets/convert.js)
```'
)
WHERE slug = 'markdown-conversion';