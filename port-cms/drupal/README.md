# Drupal adapter

Ports a staged Local CMS (WordPress-shaped) theme or plugin into a Drupal
9/10/11 **scaffold**. Invoked automatically by `port-cms` when the target CMS is
`drupal`; the OS entry points (`transform.sh`, `transform.bat`) delegate to
`transform.php`, so this adapter requires PHP on the `PATH`.

```bash
port-cms drupal themes/default
port-cms drupal plugin/local-cms-markdown
```

## What it produces

**Theme** (`_port-themes/drupal/<slug>/`):

- `<machine>.info.yml` — theme metadata and regions (`header`, `primary_menu`, `content`, `sidebar`, `footer`); built on the core `stable9` base theme so the proven document shell, and any templates this adapter does not provide, come from core
- `<machine>.libraries.yml` — the global stylesheet plus the lifted behavior script
- `<machine>.theme` — `hook_preprocess_page()` exposing the site name/slogan to the footer
- `css/style.css` — the stylesheet, relocated to Drupal's conventional folder
- `js/theme.js` — the sticky-header script lifted out of the WordPress `footer.php` inline `<script>`
- `templates/` — Twig ported from the WordPress markup, with the original class names preserved so `css/style.css` applies directly (the document `html.html.twig` is left to `stable9`):
  - `page.html.twig` — the `page-shell` / `site-header` / `site-footer` layout wrapping the regions
  - `node.html.twig` — full post/page view (`content-panel entry-shell`, `entry-grid`, `prose`), from `content-article.php`
  - `node--teaser.html.twig` — listing card (`story-card`), from `post-card.php`
  - `menu--main.html.twig` / `menu--footer.html.twig` — re-emit the `nav-link`/`footer-link` classes
  - `block--system-branding-block.html.twig` — re-emits `site-branding`/`site-title`/`site-tagline`
- `config/install/block.block.*.yml` — default block placements (branding, main menu, page title, tabs, messages, breadcrumbs, primary admin actions, main content) so the theme is usable the moment it is installed
- `img/`, `screenshot.png` — carried over as-is
- `_wordpress-source/` — the original WordPress templates, kept for reference

Themes that ship templates beyond the core set get those ported too, only when the
source file is present (so the default theme is unaffected). The richer
`local-builder` theme, for example, also produces:

- `block--search-form-block.html.twig` — from `searchform.php` / `search.php`, re-emitting the `widget`/`search-form` classes around Drupal's search form; place a *Search form* block in the sidebar region to use it
- `region--sidebar.html.twig` — from `sidebar.php`, wrapping the sidebar region's blocks in the `site-sidebar` aside
- `templates/page--404.html.twig` plus a `hook_theme_suggestions_page_alter()` in `<machine>.theme` — from `404.php`, so the ported not-found layout renders on 404 responses (the Drupal equivalent of WordPress loading `404.php`)

These are driven by a single "emit when present" map in `transform.php`, the one place to teach the adapter a new template; a sibling CMS adapter can mirror the same shape.

**Plugin** (`_port-plugins/drupal/<slug>/`):

- `<machine>.info.yml` — module metadata read from the plugin header
- `<machine>.libraries.yml` — defines the `markdown` library (`assets/convert.js` + `assets/markdown.css`)
- `<machine>.module` — `hook_help()` and `hook_preprocess_html()` adding the `localcms-theme` body class the engine looks for
- `src/Plugin/Filter/LocalCmsMarkdownFilter.php` — a text-format filter that wraps field text in `<div data-markdown-body>` and attaches the engine and the configured templates (the Drupal counterpart of the plugin's "Render as Markdown" toggle)
- `src/Form/TemplatingForm.php` — the templating admin screen (`/admin/config/content/<slug>`), with the routing, menu link, permission, and config schema that go with it
- `js/templates.js` — seeds `window.LocalCmsMarkdownTemplates` from `drupalSettings` so the engine can apply templates
- `config/install/<machine>.settings.yml` — the default `interactive` and `rule` templates
- `config/install/filter.format.<machine>.yml` — a ready-made **Local CMS Markdown** text format with the filter enabled, imported when the module is installed
- `assets/`, `README.md` — carried over as-is
- `_wordpress-source/` — the original plugin PHP, kept for reference

To use Markdown after installing the module: at **People → Permissions** grant *Use the Local CMS Markdown text format* to the relevant roles, set a field (e.g. Body) to allow that format, then pick **Local CMS Markdown** while authoring. `convert.js` renders the Markdown client-side.

### Using it with CKEditor 5

The filter is declared `TYPE_TRANSFORM_IRREVERSIBLE`, not `TYPE_MARKUP_LANGUAGE`, so it does **not** trip CKEditor 5's *"CKEditor 5 only works with HTML-based text formats"* check. You can therefore enable it on a CKEditor 5 format such as **Content**:

1. Edit the format and enable the **Local CMS Markdown** filter. Drag it **last** in the filter processing order (after *Limit allowed HTML tags*) so its wrapper is not stripped.
2. In the format's **CKEditor 5 toolbar**, add the **Source editing** (`< >`) button.
3. Author Markdown in the editor's **Source** view.

Because CKEditor 5 is an HTML editor, it wraps content in `<p>`/`<br>` and escapes characters; the filter detects that and reconstructs the Markdown source before handing it to the engine. (Raw HTML typed inside the Markdown is stripped in this mode — keep authored content as Markdown.) For Markdown without any WYSIWYG interference, use the bundled plain-text **Local CMS Markdown** format instead.

### Templating

The same template system as Local CMS's `/admin/templating` and the WordPress plugin's *Templating* screen is available at **Configuration → Content authoring → Local CMS Markdown** (`/admin/config/content/<slug>`; grant the *Administer Local CMS Markdown templates* permission to reach it). Each template is a **name** plus an **HTML snippet** that must contain the `{__markdown__}` placeholder. The module ships the `interactive` and `rule` defaults.

Saved templates are published to the engine via `drupalSettings`, so authors call one from Markdown exactly as elsewhere:

```markdown
<!-- html:template=interactive -->
Your **Markdown** goes here.
```

`convert.js` substitutes the wrapped Markdown into the named snippet's `{__markdown__}` placeholder, identically to the WordPress and Local CMS runtimes.

The slug becomes a valid Drupal machine name (`local-cms` → `local_cms`).

## Class reconciliation

WordPress and Drupal wrap menus, blocks, and content in different markup and
classes. This adapter uses the "Twig approach": the generated templates hardcode
the original Local CMS class names, and the `menu--*` and branding overrides
re-emit them, so the ported `css/style.css` styles the Drupal output without
edits. The alternative "CSS approach" — rewriting the stylesheet's selectors to
target Drupal's default classes — is left to you if you prefer it.

## Install and test

1. Copy the unpacked `<slug>/` folder into your site's `themes/` directory (or
   upload the archive via **Appearance → Install new theme**).
2. Go to **Appearance**, find the theme under *Uninstalled themes*, and
   **Install and set as default**. The default block placements in
   `config/install/` (branding, main menu, page title, tabs, messages, and the
   main content block) are imported at this point, so the theme is usable right
   away — no manual block placement needed.
3. Rebuild the cache: **Configuration → Performance → Clear all caches**, or run
   `drush cache:rebuild`.

> **Updating an existing install:** `config/install/` is only read when the
> theme is *first* installed. To pick up new or changed block placements after
> re-porting, **uninstall** the theme and **install** it again (set another
> theme as default first, then switch back). Replacing files and clearing the
> cache alone will not re-import the block config.

## What still needs a human

This is a faithful scaffold, not a full content-model migration. The WordPress
PHP templates are preserved under `_wordpress-source/` so you can port any
remaining markup (the hero panel, term pills, and template-part details) into
Twig and map content fields to the node display. The theme builds on the
`stable9` base theme, so anything this adapter does not override falls back to
core's proven templates. Delete `_wordpress-source/` once the port is complete.
