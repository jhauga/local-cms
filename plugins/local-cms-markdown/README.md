# Local CMS Markdown (WordPress plugin)

The Local CMS Markdown conversion feature, packaged as a stand-alone, plug-and-play
WordPress plugin. It renders Markdown **in the browser** using the same engine the
Local CMS theme uses ([`public/assets/convert.js`](../../public/assets/convert.js) +
[`markdown.css`](../../public/assets/markdown.css)).

## What it does

- Treats a post/page body as Markdown when **Render this content as Markdown** is
  ticked, or renders an inline snippet via the `[localcms_markdown]` shortcode.
- Supports both renderers: the built-in Local CMS converter and **marked.js** (GFM).
- Handles GitHub-style alerts, tables, task lists, footnotes, fenced code, the
  Local CMS HTML-template/wrapper comments, and **MathJax** (loaded automatically
  when math is detected).
- Emits a `<div data-markdown-body>` wrapper and adds the `localcms-theme` body class
  so the engine renders it — exactly mirroring the theme runtime.

## How it works

Conversion is client-side. On a Markdown view the plugin:

1. Outputs the raw Markdown inside `<div data-markdown-body data-markdown-renderer="…">`
   (escaped so it survives transport; `convert.js` reads it back via `textContent`).
2. Adds `localcms-theme` to `<body>` — the hook `convert.js` waits for.
3. Enqueues `markdown.css`, `convert.js`, and (when needed) MathJax.

Assets load **only** on singular views that actually use Markdown, so the stylesheet
never restyles unrelated pages.

## Usage

### Whole post or page

Edit a post/page → in the **Local CMS Markdown** box, tick *Render this content as
Markdown* and pick a renderer → write Markdown in the body → publish.

### Inline snippet

```text
[localcms_markdown renderer="marked"]
# Hello

- GitHub **flavored** markdown
- $E = mc^2$
[/localcms_markdown]
```

The shortcode captures its raw Markdown *before* `wpautop` runs, so formatting is
preserved.

## Templating (admin screen)

After activation a **Local CMS MD** item appears in the wp-admin sidebar. Its
**Templating** screen is the WordPress-compatible counterpart of Local CMS's
`/admin/templating` page: add/remove reusable HTML wrapper snippets, each with a
short name and an HTML body containing the `{__markdown__}` placeholder.

Authors then call a saved template from Markdown:

```html
<!-- html:template=callout -->
This paragraph is wrapped by the "callout" template.
```

Saved templates are stored in the `localcms_markdown_templates` option and flow into
`window.LocalCmsMarkdownTemplates` on the front end automatically. Validation matches
Local CMS: names are `[A-Za-z0-9_-]+` and unique, and each snippet must include
`{__markdown__}`.

You can also register templates programmatically (these merge with the saved ones):

```php
add_filter('localcms_markdown_templates', function (array $templates) {
    $templates['callout'] = '<aside class="callout">{__markdown__}</aside>';
    return $templates;
});
```

## Build / export

Assets are synced from the canonical app copy — do not hand-edit `assets/` here.

```sh
php build.php          # sync assets + emit _export-plugins/local-cms-markdown.zip
```

From the repo root the committed `export` scripts wrap this (and zip themes too):

```bat
export plugins local-cms-markdown
```

```sh
./export.sh plugins local-cms-markdown
```

The zip's root is the plugin's files (no wrapping folder), so extract it straight
into `wp-content/plugins/local-cms-markdown/`.
