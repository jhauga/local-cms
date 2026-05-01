UPDATE pages
SET
    title = 'Local CMS',
    excerpt = 'A compact publishing system with richer content models, taxonomy support, and a safer Markdown pipeline.',
    body_markdown = '# Publish without the framework weight

Local CMS now pairs a small PHP core with a fuller content model. Write in Markdown, store structure in SQLite, and keep the theme layer portable.

## What changed in this slice

1. Posts and pages now carry author and meta fields.
2. Categories and tags can drive archive views.
3. Markdown supports quotes, code fences, lists, links, and inline formatting.

> The content model stays separate from the theme layer so the same templates can later move into WordPress.

Explore the [post archive](/posts) or read the [workflow page](/page/editorial-workflow).',
    status = 'published',
    published_at = '2026-04-29 20:10:00',
    author_id = 1,
    template = 'page',
    meta_title = 'Local CMS',
    meta_description = 'Local CMS now supports richer content metadata, taxonomy archives, and safer Markdown rendering.',
    featured_image = '',
    sort_order = 0,
    markdown_math = 0,
    use_marked = 0
WHERE slug = 'home';

UPDATE pages
SET
    title = 'About',
    excerpt = 'Why this CMS favors portability and a small footprint.',
    body_markdown = '# About Local CMS

This scaffold is designed for fast iteration while keeping the presentation layer portable.

- Content lives in SQLite.
- Rendering happens through a safe Markdown pipeline.
- Templates stay close to WordPress naming conventions.

> A CMS should let content structure evolve without forcing the theme to change shape.',
    status = 'published',
    published_at = '2026-04-29 20:10:00',
    author_id = 1,
    template = 'page',
    meta_title = 'About the Project',
    meta_description = 'Why Local CMS keeps the backend small and the theme layer portable.',
    featured_image = '',
    sort_order = 10,
    markdown_math = 0,
    use_marked = 0
WHERE slug = 'about';

UPDATE pages
SET
    title = 'Workflow',
    excerpt = 'How content, taxonomy, and theme rendering stay decoupled.',
    body_markdown = '## Editorial workflow

1. Draft pages and posts in Markdown.
2. Attach categories or tags for archive organization.
3. Render through theme templates that can later map to WordPress.

```php
<?php
  // The frontend reads structured content, not hardcoded page markup.
  $route = ''/post/{slug}'';
?>
```

Use `archive.php` for collections and `single.php` for individual entries.',
    status = 'published',
    published_at = '2026-04-29 20:10:00',
    author_id = 1,
    template = 'page',
    meta_title = 'Editorial Workflow',
    meta_description = 'How Local CMS keeps the content model separate from the theme layer.',
    featured_image = '',
    sort_order = 20,
    markdown_math = 0,
    use_marked = 0
WHERE slug = 'editorial-workflow';

UPDATE pages
SET
    title = '`md` Conversion',
    excerpt = 'Reference page for the Local CMS markdown extras, comment attributes, wrappers, and template syntax.',
    body_markdown = '---
examples: https://github.com/example/local-cms-demo
---

# Markdown Conversion

The built-in Local CMS converter handles standard Markdown plus a handful of comment-driven additions that make documentation and editorial pages easier to shape.

## Comment Attributes <!-- id="comment-attributes" style="border-bottom: 2px solid #244f64; padding-bottom: 0.2rem;" -->

End-of-line HTML comments can attach attributes to headings, links, images, list items, and horizontal rules.

```local-cms
---<!-- class="rule-divider" -->
```

### Rendered Comment Attribute

---<!-- class="rule-divider" -->

## Links and Images

```local-cms
[Example external link](https://docs.github.com/en/get-started/writing-on-github/getting-started-with-writing-and-formatting-on-github/basic-writing-and-formatting-syntax)<!-- target="_blank" rel="noopener" class="doc-link" -->

![Theme preview](/theme/img/logo.svg "Theme preview")<!-- class="doc-figure" style="max-width: 10rem; display: block;" -->
```

### Rendered Links and Images

[Example external link](https://docs.github.com/en/get-started/writing-on-github/getting-started-with-writing-and-formatting-on-github/basic-writing-and-formatting-syntax)<!-- target="_blank" rel="noopener" class="doc-link" -->

![Theme preview](/theme/img/logo.svg "Theme preview")<!-- class="doc-figure" style="max-width: 10rem; display: block;" -->

## Task Lists

```local-cms
- [x] Parse GitHub-style task items<!-- class="task-item done" disabled-->
- [x] Keep list item attributes<!-- class="task-item done" disabled -->
<!-- class="task-item pending" -->
- [ ] Switch to `marked.js` only when the page needs a more generic renderer
- [ ] Can be toggled
```

### Rendered Task List

- [x] Parse GitHub-style task items<!-- class="task-item done" disabled-->
- [x] Keep list item attributes<!-- class="task-item done" disabled -->
- [ ] Switch to `marked.js` only when the page needs a more generic renderer<!-- class="task-item pending" -->
- [ ] Can be toggled

## Tables

```local-cms
| Syntax | Local CMS addition | Best use |
|:--|:--|--:|
| Attribute comments | Attach HTML attributes without leaving Markdown | Fine tuning |
| Wrapper blocks | Build a container with comment markers | Landing-page callouts |
| Template comments | Reuse a wrapper shape with one line | Repeating docs sections |
```

### Rendered Table

| Syntax | Local CMS addition | Best use |
|:--|:--|--:|
| Attribute comments | Attach HTML attributes without leaving Markdown | Fine tuning |
| Wrapper blocks | Build a container with comment markers | Landing-page callouts |
| Template comments | Reuse a wrapper shape with one line | Repeating docs sections |

## Alerts

```local-cms
> [!NOTE]
> Alerts use GitHub-style markers and render as structured callouts.

> [!TIP]
> The Local CMS extras live in the built-in converter, not the `marked.js` path.
```

### Rendered Alerts

> [!NOTE]
> Alerts use GitHub-style markers and render as structured callouts.

> [!TIP]
> The Local CMS extras live in the built-in converter, not the `marked.js` path.

## Footnotes

```local-cms
Footnotes work for asides and references.[^pipeline]

[^pipeline]: The converter collects footnote definitions and prints them after the main content.
```

### Rendered Footnote

Footnotes work for asides and references.[^pipeline]

[^pipeline]: The converter collects footnote definitions and prints them after the main content.

## Math

Inline math such as $a^2 + b^2 = c^2$ works alongside display blocks.

```local-cms
$$
\int_0^1 x^2\,dx = \frac{1}{3}
$$
```

### Rendered Math

$$
\int_0^1 x^2\,dx = \frac{1}{3}
$$

## Parent Attributes

```local-cms
<!-- class="doc-callout" -->
> A comment-only line can attach attributes to the next rendered block element.
```

### Rendered Parent Attribute

<!-- class="doc-callout" -->
> A comment-only line can attach attributes to the next rendered block element.

## HTML Wrapper Blocks

```local-cms
<!-- html:begin -->
<!-- section.doc-callout -->
### Wrapper Blocks

These comments create a real container while the inner body still runs through Markdown.
<!-- div.rule-accent --> <!-- span.rule-label --> <!-- Wrapper block -->
<!-- html:end -->
```

### Rendered HTML Wrapper Block

<!-- html:begin -->
<!-- section.doc-callout -->
### Wrapper Blocks

These comments create a real container while the inner body still runs through Markdown.
<!-- div.rule-accent --> <!-- span.rule-label --> <!-- Wrapper block -->
<!-- html:end -->

## Markdown Templates

```local-cms
<!-- html:template=interactive -->
### Template Blocks
Templates expand short comment declarations into reusable wrapper markup.
They fit repeated documentation sections and compact marketing callouts.
```

### Rendered Markdown Template

<!-- html:template=interactive -->
### Template Blocks
Templates expand short comment declarations into reusable wrapper markup.
They fit repeated documentation sections and compact marketing callouts.

## Relative Example Links

When frontmatter includes an `examples:` base URL, relative links with slashes are rewritten into source links such as [the page template](themes/default/page.php) and [the converter asset](public/assets/convert.js).

```local-cms
[the page template](themes/default/page.php)

<!-- and -->

[the converter asset](public/assets/convert.js)
```',
    status = 'published',
    published_at = '2026-04-30 21:05:00',
    author_id = 1,
    template = 'page',
    meta_title = 'Markdown Conversion',
    meta_description = 'Documentation page for Local CMS markdown comment attributes, wrapper blocks, templates, alerts, tables, and math rendering.',
    featured_image = '',
    sort_order = 30,
    markdown_math = 1,
    use_marked = 0
WHERE slug = 'markdown-conversion';

UPDATE posts
SET
    title = 'First Launch Note',
    excerpt = 'What this scaffold gives you on day one.',
    body_markdown = '# First launch note

The first scaffold was intentionally small. This next slice makes the content layer more realistic.

## Included now

- richer metadata fields
- category and tag relationships
- safer Markdown rendering

```php
<?php
 $app = require ''bootstrap/app.php'';
?>
```

Read the [theme portability note](/post/theme-portability) next.',
    status = 'published',
    published_at = '2026-04-29 20:10:00',
    author_id = 1,
    meta_title = 'First Launch Note',
    meta_description = 'A summary of the richer content-model and Markdown improvements.',
    featured_image = '',
    markdown_math = 0,
    use_marked = 0
WHERE slug = 'first-launch-note';

UPDATE posts
SET
    title = 'Theme Portability',
    excerpt = 'Why the theme filenames already look familiar.',
    body_markdown = '# Theme portability

Using `header.php`, `footer.php`, `index.php`, `page.php`, `single.php`, and `archive.php` keeps the view layer close to WordPress.

> The backend can evolve independently as long as the theme receives clean data.

That separation is what makes later extraction into a WordPress theme practical.',
    status = 'published',
    published_at = '2026-04-28 20:10:00',
    author_id = 1,
    meta_title = 'Theme Portability',
    meta_description = 'Why Local CMS uses WordPress-shaped template boundaries.',
    featured_image = '',
    markdown_math = 0,
    use_marked = 0
WHERE slug = 'theme-portability';

UPDATE posts
SET
    title = 'Writing with Markdown',
    excerpt = 'How the CMS now handles safer Markdown rendering for authors.',
    body_markdown = '# Writing with Markdown

Markdown now supports:

- headings
- ordered and unordered lists
- blockquotes
- fenced code blocks
- inline `code` and [links](/posts)

## Safe output matters

The fallback renderer escapes raw HTML, allows only safe links, and keeps content portable between systems.',
    status = 'published',
    published_at = '2026-04-27 20:10:00',
    author_id = 1,
    meta_title = 'Writing with Markdown',
    meta_description = 'A walkthrough of the safer Markdown pipeline used by Local CMS.',
    featured_image = '',
    markdown_math = 0,
    use_marked = 0
WHERE slug = 'writing-with-markdown';

UPDATE posts
SET
    title = 'Markdown Conversion Techniques',
    excerpt = 'A post about the Local CMS markdown features that go beyond plain Markdown.',
    body_markdown = '# Markdown Conversion Techniques

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
    status = 'published',
    published_at = '2026-04-30 20:05:42',
    author_id = 1,
    meta_title = 'Markdown Conversion Techniques',
    meta_description = 'A short overview of the Local CMS markdown features that extend plain Markdown with comment-based syntax.',
    featured_image = NULL,
    markdown_math = 0,
    use_marked = 0
WHERE slug = 'markdown-conversion-techniques';