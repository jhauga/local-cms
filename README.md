# Local ![cms](icon.png)

`Ctrl + click` to view [built example](https://jhauga.github.io/local-cms/).

Local CMS is a simple PHP 8.2 content management scaffold designed around two constraints:

- keep the application small enough to run without framework dependencies
- keep the presentation layer close to WordPress theme conventions so it can be reused later

The current scaffold includes a public entry point, environment and config loading, a lightweight router, a theme loader, SQLite migrations, richer content queries, taxonomy archives, and a default theme with WordPress-shaped template files.

## Current Features

- Dependency-free PHP 8.2 scaffold
- SQLite bootstrap with incremental migrations
- Public front controller and development router
- Richer content model for pages and posts: author fields, meta fields, categories, and tags
- Archive routes for `/posts`, `/category/{slug}`, and `/tag/{slug}`
- Local admin area that automatically uses the seeded admin identity for CRUD on pages, posts, taxonomy terms, and site settings
- Admin-side `Import` screen that splits one running markdown document into any number of pages and posts, with reusable tag/keyword groups, per-item `metadata` blocks, defaults for missing metadata, and a dry-run preview
- Markdown editor preview tabs in the admin area, plus an optional Markdown Math flag that injects MathJax on published pages and posts
- Page and post titles support a compact inline markdown subset for code, emphasis, strong text, strikethrough, and simple `sub`/`sup`/`ins` tags
- Per-page and per-post Markdown renderer toggle that can lazy-load `marked.js` for richer client-side rendering when the built-in converter is not enough
- Admin-side `Templating` screen for reusable markdown wrapper snippets stored in CMS settings instead of hardcoded client assets
- Admin-side `Themes` screen that lists installed themes with screenshots, switches the active theme by writing `config.json`, can browse and install themes from the public WordPress.org theme directory, and offers a live **Preview** of any installed theme (including ported ones) without activating it
- Admin-side `Theme Bridge` screen that lists the helper functions a ported theme calls but the runtime has no real implementation for, and lets each fallback behaviour be overridden and saved to `storage/theme-fallbacks.json`
- Image uploads stored under `/uploads/` with configurable date folders, defaulting to `/uploads/YYYY/MM/`
- Root `config.json` support for theme selection, theme media folders, content defaults, and upload date-path settings with safe fallbacks
- Theme directory with `header.php`, `footer.php`, `index.php`, `page.php`, `single.php`, `archive.php`, `functions.php`, and `style.css`
- Built-in CMS assets `convert.js` and `markdown.css` served from `/assets/` for standalone markdown-to-HTML rendering on exported or self-hosted pages
- WordPress-style theme runtime helpers like `get_header()`, `get_footer()`, `get_template_part()`, `body_class()`, and `post_class()` for future extraction
- Repo-local static export command: `perl localcms.pl --build`
- GitHub Pages workflow that publishes `export/` as the deployed site root
- Markdown adapter that uses `league/commonmark` automatically if installed later, with a safe built-in fallback renderer today
- WordPress-ready default theme: the same templates render inside a stock WordPress install, resolving taxonomy terms, excerpts, and permalinks through helpers that detect the runtime
- `local-builder` theme: a verbose, page-builder-ready variation styled on the Bootstrap framework with a sharp, dark slate gray skin, shipping search, sidebar, and 404 templates in addition to the core set
- `plugins/` workspace for building and testing CMS plugins before export, including the staple `Local CMS Markdown` WordPress plugin
- Cross-platform packaging scripts, `export.bat` and `export.sh`, that zip a theme or plugin for deployment
- Cross-platform porting scripts, `port-cms.bat` and `port-cms.sh`, that stage a theme or plugin for other CMS platforms via a registry of targets and per-CMS adapter hooks

## Project Structure

```text
bootstrap/           Application bootstrap
config/              Runtime configuration
config.json          CMS theme and application defaults
database/            SQLite schema and starter content
database/migrations/ Incremental schema and seed changes
plugins/             Plugin workspace (build/test before export)
public/              Web entry point and dev router
scripts/             CLI build/export entry points
src/                 Core application classes
storage/database/    SQLite database file location
themes/default/      Default frontend theme
themes/local-builder/ Bootstrap-based, page-builder-ready theme
export.bat           Theme/plugin packaging (Windows)
export.sh            Theme/plugin packaging (Linux/macOS)
port-cms.bat         Port a theme/plugin to another CMS (Windows)
port-cms.sh          Port a theme/plugin to another CMS (Linux/macOS)
port-cms/            CMS registry and per-CMS adapter hooks
```

The default theme now also includes `template-parts/` partials and a starter `theme.json` file to make the next WordPress extraction step more mechanical.

The repo root also includes `config.json` for CMS-level theme and application settings. If that file is missing, invalid, or points to a theme directory that does not exist, the application falls back to the `default` theme and uses `img` as the theme media directory. Use `application.uploads.datePath` to control dated subfolders under `/uploads/`, for example `["YYYY", "MM"]` or `["YYYY"]`.

## Local Setup

1. Copy `.env.example` to `.env`.
1. Adjust the values if needed.
1. Start the development server:

```powershell
php -S localhost:8000 public/router.php
```

1. Open `http://localhost:8000`.

The SQLite database is created automatically at `storage/database/cms.sqlite` on first boot. After that, each startup applies any new SQL migrations in `database/migrations`.

## Editing config.json

Use the root `config.json` file to change CMS behavior without editing PHP source. The application reads it on boot, merges the `application` section into built-in defaults, and falls back safely when values are missing or invalid. `APP_THEME` in `.env` still overrides `theme.name` if both are present.

Example configuration:

```json
{
  "theme": {
    "name": "default",
    "media": "img"
  },
  "application": {
    "mode": "local",
    "admin": {
      "title": "Local CMS Studio",
      "chrome": "editorial"
    },
    "content": {
      "defaultFormat": "markdown",
      "previews": true,
      "clientConverter": true
    },
    "uploads": {
      "datePath": ["YYYY", "MM"]
    }
  }
}
```

- `theme.name` selects the folder under `themes/`. If the folder does not exist, the app falls back to `default`.
- `theme.media` selects the theme asset subfolder that is copied to `export/theme/<folder>`. Invalid folder names fall back to `img`.
- `application.mode` is a general runtime mode flag. Keep it at `local` unless you add your own environment-specific branching.
- `application.admin.title` and `application.admin.chrome` are admin-side metadata values carried into the runtime config. They are useful for custom branding or future admin-shell variants.
- `application.content.defaultFormat` sets the seeded default content format in the runtime config. The current default is `markdown`.
- `application.content.previews` keeps editor preview support enabled in the runtime config.
- `application.content.clientConverter` enables the built-in client markdown converter globally, which helps wrapper/template-aware markdown render consistently without relying only on per-item `marked.js`.
- `application.uploads.datePath` controls subfolders inside `/uploads/`. Use `["YYYY", "MM"]` for year/month folders, `["YYYY"]` for year-only folders, or `[]` to upload directly into `/uploads/`. Only `YYYY` or `Y`, and `MM` or `m`, are normalized; other values are ignored.

Common edits:

- Switch to a different theme by changing `theme.name` to the name of a folder under `themes/`.
- Flatten uploads into `/uploads/` by setting `"datePath": []`.
- Keep editor previews on but disable the global client converter by setting `"clientConverter": false`.

## Admin Access

- Admin URL: `/admin`
- Seeded admin identity: `admin@example.com`
- No sign-in is required in local mode; the admin area resolves the seeded admin user automatically.

The admin area supports:

- direct local access without a login screen
- create, edit, and delete pages and posts
- preview Markdown in the editor before saving
- switch individual pages or posts to the `marked.js` renderer from the editor when they need the richer client-side Markdown pass
- enable MathJax for individual pages or posts with the `Markdown Math` option
- upload featured images into `/uploads/` with configurable date folders or keep using external image URLs
- assign categories and tags from the content editor
- create, edit, and delete taxonomy terms
- import a running markdown document from the `Import` screen, as pasted text or an uploaded `.md` file, with an optional dry run
- update site name and tagline settings, plus the default content type used when a running markdown item does not resolve a type
- switch the active theme from the `Themes` screen, with screenshots for each installed theme, and optionally browse and install themes from the WordPress.org directory

Activating a theme writes `theme.name` to `config.json` and takes effect on the next page load. When `APP_THEME` is set in `.env`, it continues to override `config.json`, and the `Themes` screen notes this so the active theme stays predictable.

Installing a theme from the WordPress.org directory downloads the package into a repo-local staging folder under `storage/tmp/`, extracts and validates it there, and only then moves the finished theme into `themes/` (the staging folder is removed afterward). Outbound HTTPS uses the operating system's native certificate store when the PHP build has no CA bundle configured, so the directory works on stock Windows PHP without a bundled `cacert.pem`.

Only themes built for the local runtime can be set as the active theme. A local-runtime theme declares itself with a `Local CMS Runtime: compatible` line in its `style.css` header; the bundled `default` and `local-builder` themes carry it. Compatibility is decided from that marker **without executing any theme code** — a foreign WordPress theme is never loaded to test it, because its `functions.php` may call `exit()`/`die()` when WordPress is absent (the common `defined( 'ABSPATH' ) || exit;` guard) and terminate the process uncatchably. The Themes screen offers **Activate** only for marked themes and labels the rest *Export & port only*; a direct attempt to activate an unmarked theme is declined with an explanation, so it is never written into `config.json`. As a safety net, if `config.json` is ever pointed at an incompatible theme by hand, the runtime falls back to the default theme at boot (again without loading the incompatible one) and resets `config.json` so the site stays reachable. WordPress.org downloads remain available for `export` and `port-cms`.

## Validation

Run a quick route smoke test:

```powershell
php -r 'define("CMS_ROOT", getcwd()); require "src/autoload.php"; $app = require "bootstrap/app.php"; $response = $app->handle(new Cms\Http\Request("GET", "/")); fwrite(STDOUT, (string) $response->getStatusCode());'
```

Check that migrations and taxonomy data exist:

```powershell
@'
<?php
require 'src/autoload.php';
$config = require 'config/database.php';
\Cms\Core\Database::bootstrap($config);
$pdo = \Cms\Core\Database::connection();
echo $pdo->query("SELECT COUNT(*) FROM migrations")->fetchColumn(), PHP_EOL;
echo $pdo->query("SELECT COUNT(*) FROM terms")->fetchColumn(), PHP_EOL;
'@ | php
```

Smoke-test the admin route:

```powershell
php -S localhost:8000 public/router.php
```

Then open `/admin` and sign in with the seeded credentials above.
The legacy `/admin/login` route now redirects back to `/admin`.

The admin area now resolves the seeded user automatically in local mode, so `/admin/login` only redirects back to `/admin`.

Lint all PHP files:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Build the public site into `export/`:

```powershell
perl localcms.pl --build
```

The export command writes the public-facing site into a static folder structure that can be uploaded directly to `public_html` or a similar document root. The current build includes:

- `export/index.html`
- `export/posts/index.html`
- `export/post/{slug}/index.html`
- `export/page/{slug}/index.html`
- `export/category/{slug}/index.html`
- `export/tag/{slug}/index.html`
- `export/theme/style.css`
- `export/assets/convert.js`
- `export/assets/markdown.css`
- `export/theme/<public-assets>` including files from the configured theme media directory such as `export/theme/img/`
- `export/uploads/<configured-date-path>/<image>` for authored uploaded media, defaulting to `export/uploads/<year>/<month>/<image>`

Each build also writes `export/build-manifest.json` so you can inspect which routes and files were generated.

## GitHub Pages

The repository includes [deploy-pages.yml](.github/workflows/deploy-pages.yml), which builds the site with `perl localcms.pl --build` and deploys the generated `export/` directory through the official GitHub Pages actions.

That means GitHub Pages serves the contents of `export/` as the published site root rather than serving the repository root.

The workflow:

- runs on pushes to `main`
- can also be triggered manually with `workflow_dispatch`
- uploads `export/` with `actions/upload-pages-artifact`
- deploys the uploaded artifact with `actions/deploy-pages`

For project Pages sites, the workflow sets `APP_URL` to the repository Pages URL automatically. If you use a custom domain or want to override the detected URL, add a repository variable named `APP_URL` in GitHub and the workflow will use that value instead.

GitHub Pages does not build from your local `storage/database/cms.sqlite` file. The `storage/` directory is gitignored, so the workflow always starts from the committed schema plus SQL migrations on a fresh runner. If localhost and Pages drift after editing content in the admin UI, the missing piece is usually a migration or other tracked data update that mirrors those content changes into the repository.

## Running Markdown Import

The admin `Import` screen turns one markdown document into any number of pages and posts. A document like this creates two published posts in a single import:

```md
---
type: posts
status: publish
---

# First Announcement

Body of the first post.

---

# Second Announcement

Body of the second post.
```

Items are separated by a `---` line with an empty line before and after it. Separator lines inside fenced code blocks are ignored, so code samples survive the split.

### Top-matter

The optional opening block between `---` fences applies to the whole document:

- `type` sets the document shape: `pages`, `posts`, or `mix`.
- `status` sets a shared status: `publish` or `draft`.
- `groups` declares reusable tag and keyword lists that items reference later.
- `default-*` properties fill in any metadata key an item leaves out, for example `default-type`, `default-title`, `default-description`, `default-tags`, `default-keywords`, and `default-status`.

### Per-item metadata

In a `mix` document, each item can carry quasi-top-matter: a fenced code block whose language identifier is `metadata`, holding `key: value` lines.

```md
    ```metadata
    title: Getting Started Guide
    type: page
    status: publish
    tags: guide, how-to
    ```
```

The metadata block is optional. An item without a `type` uses the previous item's type; the first item without one falls back to `default-type` from the top-matter, and finally to the **Default Content Type** setting on the admin `Settings` screen (`post` on a fresh install).

### Placeholders

Top-matter defaults and metadata values can reference groups and the item body:

- `${groups.news}` expands to the `news` group's tag list, or its keyword list when resolving a keywords value.
- `${h1[0].text}` expands to the text of the item's first level 1 heading.
- `${p[0].text}` expands to the text of the item's first paragraph.

A full example:

```md
---
type: mix
groups: {
 news: {
  tags: news, announcements
  keywords: bulletin, update
 }
}
default-type: post
default-title: ${h1[0].text}
default-description: ${p[0].text}
default-tags: ${groups.news}
default-status: publish
---
```

### Import behavior

- Slugs derive from titles. Re-importing a document updates existing items in place instead of duplicating them, so a running markdown file can keep growing and be imported again after each addition.
- Tags and categories that do not exist yet are created automatically.
- Keywords are parsed and shown in the import report but are not stored yet; the content model has no keywords column.
- The **Dry run** option previews what would be created or updated without saving anything.

A ready-to-import proof of concept ships with the CMS at `public/assets/examples/running-markdown.md` and is published with the static build at `/assets/examples/running-markdown.md`. Paste its contents into the `Import` screen, or upload the file, to see a mixed document become two published posts, a page, and a draft.

## Theme Direction

The default theme is intentionally shaped like a WordPress theme. It now goes beyond filenames: the frontend templates call `get_header()`, `get_footer()`, `get_template_part()`, `bloginfo()`, `body_class()`, and `post_class()` through a small compatibility layer in the app runtime.

That means the theme is now organized around the same structural seams that a later WordPress port will use:

- shared entry markup lives in `themes/default/template-parts/`
- theme metadata and future editor settings live in `themes/default/theme.json`
- the application still owns routing and data queries, but templates already render through WordPress-shaped helpers

A second theme, `themes/local-builder/`, demonstrates the same runtime contract as a verbose, page-builder-ready variation. It layers a sharp-edged, dark slate gray skin on the Bootstrap framework (loaded from a CDN, with `style.css` mapping Bootstrap's own tokens onto the theme palette), inverts Bootstrap's rounded geometry, and drops gradients entirely. Beyond the core templates it ships `search.php`, `searchform.php`, `sidebar.php`, and `404.php`; the sidebar is included through `get_template_part('sidebar')` so it renders in both the Local CMS and WordPress runtimes, and the WordPress-only template tags are guarded for runtime parity.

## Exporting to WordPress

The default theme and the bundled plugin can be packaged for a stock WordPress install. Use the `export` script for the current platform:

```powershell
export themes default
export plugins local-cms-markdown
```

```bash
./export.sh themes default
./export.sh plugins local-cms-markdown
```

Each run writes a zip under `_export-themes/` or `_export-plugins/` — the default theme as `local-cms.zip`, the plugin as `local-cms-markdown.zip`. The archive root holds the folder's files directly, with no wrapping directory, so the contents extract straight into `wp-content/themes/<slug>/` or `wp-content/plugins/<slug>/`.

Imported into WordPress, the default theme renders pages, posts, archives, and taxonomy term links without code changes. Term references, excerpts, and permalinks resolve through helpers that detect the runtime: pages omit the excerpt lead, and posts show an excerpt only when one is authored.

### Local CMS Markdown plugin

`plugins/local-cms-markdown/` packages the Markdown engine (`convert.js` and `markdown.css`) as a stand-alone WordPress plugin. After activation, a `Local CMS MD` item in the admin sidebar opens a `Templating` screen for reusable HTML wrapper snippets — the WordPress counterpart of the admin `Templating` page. Render a whole post or page by enabling `Render this content as Markdown` in the editor, or render an inline snippet with the `[localcms_markdown]` shortcode. See [plugins/README.md](plugins/README.md) for the full workflow.

## Porting to other CMS platforms

Beyond WordPress, the same staged theme and plugin can be ported toward other content management systems with the `port-cms` script for the current platform:

```powershell
port-cms --list
port-cms drupal themes/default
port-cms drupal plugin/local-cms-markdown
```

```bash
./port-cms.sh --list
./port-cms.sh drupal themes/default
./port-cms.sh drupal plugin/local-cms-markdown
```

The CMS target is case-insensitive (`DrUpAL` resolves to `drupal`), and the `tool/name` argument accepts singular or plural (`theme`/`themes`, `plugin`/`plugins`). Each run stages a clean copy under `_port-<tool>/<cms>/<slug>/`, applies that CMS's adapter when one exists, and writes an OS-appropriate archive beside it — `<slug>.zip` on Windows and `<slug>.tar.gz` on Linux/macOS. As with export, the `default` theme/plugin maps to the `local-cms` slug.

Supported targets live in [port-cms/registry.txt](port-cms/registry.txt), and each CMS can ship an optional `port-cms/<cms>/transform.{sh,bat}` adapter that rewrites the staged files into that platform's conventions. New platforms are added one at a time — see [port-cms/README.md](port-cms/README.md) for the adapter contract.

**Drupal** is the first implemented adapter. Porting to `drupal` scaffolds a Drupal 9/10/11 theme or module — generating the `<machine>.info.yml`, `<machine>.libraries.yml`, and (for themes) a `<machine>.theme` plus Twig templates that preserve the original WordPress class names so the ported `style.css` applies directly, or (for plugins) a `<machine>.module`. It relocates assets to Drupal's conventional folders (`css/`, `js/`), lifts the theme's inline footer script into a JS library, and preserves the original WordPress files under `_wordpress-source/` for reference. Themes that ship templates beyond the core set — such as `local-builder` with its `search.php`, `sidebar.php`, and `404.php` — have those ported as well through a data-driven map that only emits when the source file is present, so the default theme is unaffected. The adapter is written in PHP, so it requires PHP on the `PATH`. See [port-cms/drupal/README.md](port-cms/drupal/README.md) for the full output and the manual steps that remain.

### Inbound: porting a WordPress theme into Local CMS

The `local-cms` target reverses the direction — instead of staging a Local CMS theme for a foreign platform, it pulls a stock WordPress theme into this repo and makes it runtime-compatible:

```powershell
port-cms local-cms themes/twentytwentyone
```

```bash
./port-cms.sh local-cms themes/twentytwentyone
```

It stages a clean copy, runs the inbound adapter to verify the theme's license permits porting, stamp the `Local CMS Runtime: compatible` marker into `style.css`, neutralize the WordPress-only `defined( 'ABSPATH' ) || exit;` guards, and write a `LICENSE_NOTE.txt`, then prompts `overwrite - y or n` before writing back under `themes/` — `y` overwrites `themes/<name>` in place, `n` writes a converted copy to `themes/port-<name>`. The ported theme runs against a broad WordPress compatibility layer ([src/Support/WordPressCompat.php](src/Support/WordPressCompat.php)) plus a **theme function bridge** ([src/Support/ThemeFunctionBridge.php](src/Support/ThemeFunctionBridge.php)) that shims the theme-specific helpers and classes a theme calls but the runtime does not define — so a stock theme renders with its real templates, styling, and as many of its real functions as can be recovered, falling back safely on the rest instead of fataling on the first undefined call. Fallback behaviours are chosen by a [registry](src/Support/ThemeFallbackRegistry.php) editable from the admin `Theme Bridge` screen. A page-builder theme whose templates the runtime cannot drive has those templates replaced with minimal portable ones, and a block/FSE theme with no `index.php` has a minimal base template generated, so it still renders rather than aborting. The adapter refuses to port a theme whose license forbids modifying the source (no-derivatives or proprietary licenses) or cannot be determined, and aborts without touching `themes/` only when the source is not a WordPress theme at all (no `style.css` or missing `Theme Name` header). See [port-cms/local-cms/README.md](port-cms/local-cms/README.md) for the license allowlist, `LICENSE_NOTE.txt`, the template portability screen, and the theme function bridge.

## Markdown Pipeline

- If `league/commonmark` is installed later through Composer, Local CMS will use it automatically.
- In the current no-Composer environment, the built-in fallback supports headings, ordered and unordered lists, blockquotes, horizontal rules, fenced code blocks, inline code, emphasis, strong text, and safe links.
- `convert.js` and `markdown.css` are CMS-level assets served from `/assets/` and exported to `export/assets/`. They are no longer part of the theme directory and are available regardless of which theme is active. They support tables, task lists, markdown images, titled links, footnotes, alert blockquotes, comment-based attribute injection, lightweight `html:begin` wrappers, settings-backed `html:template=name` shortcuts, `local-cms` code fences for showing raw template syntax, and MathJax-friendly `math-renderer` placeholders.
- When a page or post enables `Use marked.js Renderer`, the editor preview and frontend lazy-load `marked.js`, strip simple YAML frontmatter before rendering, and continue to fall back to the built-in converter if the library cannot be loaded.
- Template definitions live in the admin area under `Templating`. Each snippet must include `{__markdown__}` as the insertion point for the markdown lines collected after `<!-- html:template=name -->`.
- Page and post titles use a separate compact inline markdown renderer so title fields can safely render snippets like `` `code` ``, `*italic*`, `**bold**`, `~~strike~~`, and simple `<sub>` / `<sup>` / `<ins>` tags without invoking the full body markdown pipeline.

To use them in a standalone HTML page, include both files and add a `<div id="content"></div>` target:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Page</title>
  <link rel="stylesheet" href="/assets/markdown.css">
</head>
<body>
  <div id="content"></div>
  <script src="/assets/convert.js"></script>
</body>
</html>
```

The script fetches `file.md` relative to the page, converts it to HTML, and injects the result into `#content`.

- Raw HTML is escaped in the fallback renderer to keep output safe by default.
