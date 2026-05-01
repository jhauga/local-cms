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
- Markdown editor preview tabs in the admin area, plus an optional Markdown Math flag that injects MathJax on published pages and posts
- Page and post titles support a compact inline markdown subset for code, emphasis, strong text, strikethrough, and simple `sub`/`sup`/`ins` tags
- Per-page and per-post Markdown renderer toggle that can lazy-load `marked.js` for richer client-side rendering when the built-in converter is not enough
- Admin-side `Templating` screen for reusable markdown wrapper snippets stored in CMS settings instead of hardcoded client assets
- Image uploads stored under `/uploads/` with configurable date folders, defaulting to `/uploads/YYYY/MM/`
- Root `config.json` support for theme selection, theme media folders, content defaults, and upload date-path settings with safe fallbacks
- Theme directory with `header.php`, `footer.php`, `index.php`, `page.php`, `single.php`, `archive.php`, `functions.php`, and `style.css`
- Built-in CMS assets `convert.js` and `markdown.css` served from `/assets/` for standalone markdown-to-HTML rendering on exported or self-hosted pages
- WordPress-style theme runtime helpers like `get_header()`, `get_footer()`, `get_template_part()`, `body_class()`, and `post_class()` for future extraction
- Repo-local static export command: `perl localcms.pl --build`
- GitHub Pages workflow that publishes `export/` as the deployed site root
- Markdown adapter that uses `league/commonmark` automatically if installed later, with a safe built-in fallback renderer today

## Project Structure

```text
bootstrap/           Application bootstrap
config/              Runtime configuration
config.json          CMS theme and application defaults
database/            SQLite schema and starter content
database/migrations/ Incremental schema and seed changes
public/              Web entry point and dev router
scripts/             CLI build/export entry points
src/                 Core application classes
storage/database/    SQLite database file location
themes/default/      Default frontend theme
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
- update site name and tagline settings

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

## Theme Direction

The default theme is intentionally shaped like a WordPress theme. It now goes beyond filenames: the frontend templates call `get_header()`, `get_footer()`, `get_template_part()`, `bloginfo()`, `body_class()`, and `post_class()` through a small compatibility layer in the app runtime.

That means the theme is now organized around the same structural seams that a later WordPress port will use:

- shared entry markup lives in `themes/default/template-parts/`
- theme metadata and future editor settings live in `themes/default/theme.json`
- the application still owns routing and data queries, but templates already render through WordPress-shaped helpers

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
