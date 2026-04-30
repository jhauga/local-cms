INSERT OR IGNORE INTO pages (
    slug,
    title,
    excerpt,
    body_markdown,
    markdown_math,
    status,
    published_at,
    author_id,
    template,
    meta_title,
    meta_description,
    sort_order,
    use_marked
) VALUES (
    'markdown-conversion',
    'Markdown Conversion',
    'Reference page for the Local CMS markdown extras, comment attributes, wrappers, and template syntax.',
    '---
examples: https://github.com/example/local-cms-demo
---

# Markdown Conversion

The built-in Local CMS converter handles standard Markdown plus a handful of comment-driven additions that make documentation and editorial pages easier to shape.

## Comment Attributes <!-- id="comment-attributes" style="border-bottom: 2px solid #244f64; padding-bottom: 0.2rem;" -->

End-of-line HTML comments can attach attributes to headings, links, images, list items, and horizontal rules.

--- <!-- class="rule-divider" -->

## Links and Images

[Example source link](docs/markdown-guide.md "Example source")<!-- target="_blank" rel="noopener" class="doc-link" -->

![Theme preview](/theme/img/logo.svg "Theme preview")<!-- class="doc-figure" style="max-width: 10rem; display: block;" -->

## Task Lists

- [x] Parse GitHub-style task items<!-- class="task-item done" -->
- [x] Keep list item attributes<!-- class="task-item done" -->
- [ ] Switch to `marked.js` only when the page needs a more generic renderer<!-- class="task-item pending" -->

## Tables

| Syntax | Local CMS addition | Best use |
|:--|:--|--:|
| Attribute comments | Attach HTML attributes without leaving Markdown | Fine tuning |
| Wrapper blocks | Build a container with comment markers | Landing-page callouts |
| Template comments | Reuse a wrapper shape with one line | Repeating docs sections |

## Alerts

> [!NOTE]
> Alerts use GitHub-style markers and render as structured callouts.

> [!TIP]
> The Local CMS extras live in the built-in converter, not the `marked.js` path.

## Footnotes

Footnotes work for asides and references.[^pipeline]

[^pipeline]: The converter collects footnote definitions and prints them after the main content.

## Math

Inline math such as $a^2 + b^2 = c^2$ works alongside display blocks.

$$
\int_0^1 x^2\,dx = \frac{1}{3}
$$

## Parent Attributes

<!-- class="doc-callout" -->
> A comment-only line can attach attributes to the next rendered block element.

## HTML Wrapper Blocks

<!-- html:begin -->
<!-- section.doc-callout -->
### Wrapper Blocks

These comments create a real container while the inner body still runs through Markdown.
<!-- div.rule-accent --> <!-- span.rule-label --> <!-- Wrapper block -->
<!-- html:end -->

## Markdown Templates

<!-- html:template=interactive -->
### Template Blocks
Templates expand short comment declarations into reusable wrapper markup.
They fit repeated documentation sections and compact marketing callouts.

## Code Fences

```local-cms
<!-- html:template=interactive -->
### Template Title
Wrapped markdown content
```

## Relative Example Links

When frontmatter includes an `examples:` base URL, relative links with slashes are rewritten into source links such as [the page template](themes/default/page.php) and [the converter asset](public/assets/convert.js).

```local-cms
[the page template](themes/default/page.php)

<!-- and -->

[the converter asset](public/assets/convert.js)
```',
    1,
    'published',
    CURRENT_TIMESTAMP,
    (SELECT id FROM users ORDER BY id ASC LIMIT 1),
    'page',
    'Markdown Conversion',
    'Documentation page for Local CMS markdown comment attributes, wrapper blocks, templates, alerts, tables, and math rendering.',
    30,
    0
);

INSERT OR IGNORE INTO posts (
    slug,
    title,
    excerpt,
    body_markdown,
    markdown_math,
    status,
    published_at,
    author_id,
    meta_title,
    meta_description,
    use_marked
) VALUES (
    'markdown-conversion-techniques',
    'Markdown Conversion Techniques',
    'A post about the Local CMS markdown features that go beyond plain Markdown.',
    '# Markdown Conversion Techniques

Local CMS ships with a default browser-side converter that does more than plain Markdown. It keeps authored content portable, but it also understands comment-driven extras that work well for tutorials, notes, and documentation.

## What is unique here

- end-of-line comment attributes can decorate headings, links, images, and list items
- comment-only lines can attach attributes to the next block element
- alerts, task lists, tables, footnotes, and MathJax-friendly placeholders work without a package install
- `<!-- html:begin -->` wrappers and `<!-- html:template=name -->` templates add layout structure without dropping into full raw HTML

> [!TIP]
> Use the built-in converter when you need Local CMS specific syntax. Use the `marked.js` toggle when the page needs a richer general Markdown pass.

## A compact example

<!-- html:template=rule -->
### Comment wrappers stay readable
One template comment can wrap a short section without turning the body copy into a wall of raw HTML.
You can still add attributes to a [converter link](/assets/convert.js "Converter source")<!-- target="_blank" rel="noopener" --> and send readers to the full [Markdown Conversion](/page/markdown-conversion) reference page.

## Why this matters

Writers can stay in Markdown, editors can keep a consistent pattern library, and the theme still receives clean rendered HTML.',
    0,
    'published',
    datetime(CURRENT_TIMESTAMP, '-1 hour'),
    (SELECT id FROM users ORDER BY id ASC LIMIT 1),
    'Markdown Conversion Techniques',
    'A short overview of the Local CMS markdown features that extend plain Markdown with comment-based syntax.',
    0
);

INSERT OR IGNORE INTO content_terms (content_type, content_id, term_id, sort_order)
SELECT 'page', p.id, t.id, 0
FROM pages p
JOIN terms t ON t.taxonomy = 'tag' AND t.slug = 'markdown'
WHERE p.slug = 'markdown-conversion';

INSERT OR IGNORE INTO content_terms (content_type, content_id, term_id, sort_order)
SELECT 'post', p.id, t.id, 0
FROM posts p
JOIN terms t ON t.taxonomy = 'category' AND t.slug = 'writing-workflow'
WHERE p.slug = 'markdown-conversion-techniques';

INSERT OR IGNORE INTO content_terms (content_type, content_id, term_id, sort_order)
SELECT 'post', p.id, t.id, 1
FROM posts p
JOIN terms t ON t.taxonomy = 'tag' AND t.slug = 'markdown'
WHERE p.slug = 'markdown-conversion-techniques';

INSERT OR IGNORE INTO content_terms (content_type, content_id, term_id, sort_order)
SELECT 'post', p.id, t.id, 2
FROM posts p
JOIN terms t ON t.taxonomy = 'tag' AND t.slug = 'content-model'
WHERE p.slug = 'markdown-conversion-techniques';