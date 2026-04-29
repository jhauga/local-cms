# Local CMS

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
- Admin login with session auth, CSRF protection, and CRUD for pages, posts, taxonomy terms, and site settings
- Theme directory with `header.php`, `footer.php`, `index.php`, `page.php`, `single.php`, `archive.php`, `functions.php`, and `style.css`
- WordPress-style theme runtime helpers like `get_header()`, `get_footer()`, `get_template_part()`, `body_class()`, and `post_class()` for future extraction
- Repo-local static export command: `perl localcms.pl --build`
- GitHub Pages workflow that publishes `export/` as the deployed site root
- Markdown adapter that uses `league/commonmark` automatically if installed later, with a safe built-in fallback renderer today

## Project Structure

```text
bootstrap/           Application bootstrap
config/              Runtime configuration
database/            SQLite schema and starter content
database/migrations/ Incremental schema and seed changes
public/              Web entry point and dev router
scripts/             CLI build/export entry points
src/                 Core application classes
storage/database/    SQLite database file location
themes/default/      Default frontend theme
```

The default theme now also includes `template-parts/` partials and a starter `theme.json` file to make the next WordPress extraction step more mechanical.

## Local Setup

1. Copy `.env.example` to `.env`.
2. Adjust the values if needed.
3. Start the development server:

```powershell
php -S localhost:8000 public/router.php
```

4. Open `http://localhost:8000`.

The SQLite database is created automatically at `storage/database/cms.sqlite` on first boot. After that, each startup applies any new SQL migrations in `database/migrations`.

## Admin Access

- Admin URL: `/admin`
- Seeded email: `admin@example.com`
- Seeded password: `LocalCMS123!`

The admin area supports:

- sign in and sign out with server-side session auth
- create, edit, and delete pages and posts
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

Smoke-test the admin login route:

```powershell
php -S localhost:8000 public/router.php
```

Then open `/admin` and sign in with the seeded credentials above.

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

## Theme Direction

The default theme is intentionally shaped like a WordPress theme. It now goes beyond filenames: the frontend templates call `get_header()`, `get_footer()`, `get_template_part()`, `bloginfo()`, `body_class()`, and `post_class()` through a small compatibility layer in the app runtime.

That means the theme is now organized around the same structural seams that a later WordPress port will use:

- shared entry markup lives in `themes/default/template-parts/`
- theme metadata and future editor settings live in `themes/default/theme.json`
- the application still owns routing and data queries, but templates already render through WordPress-shaped helpers

## Markdown Pipeline

- If `league/commonmark` is installed later through Composer, Local CMS will use it automatically.
- In the current no-Composer environment, the built-in fallback supports headings, ordered and unordered lists, blockquotes, horizontal rules, fenced code blocks, inline code, emphasis, strong text, and safe links.
- Raw HTML is escaped in the fallback renderer to keep output safe by default.

## Current Frontend Slice

Prompt 4 refined the default frontend theme and started the WordPress compatibility layer so shared presentation assets can be extracted more cleanly.
