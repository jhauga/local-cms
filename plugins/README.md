# Plugins workspace

A staging area for building and testing CMS plugins **inside this repo** before
exporting them to WordPress (or another CMS). It mirrors the role that
[`themes/`](../themes/) plays for themes: develop here, then export a self-contained,
plug-and-play package.

## How it works

- Each subfolder is one plugin, structured exactly as the target CMS expects so it
  can be zipped and dropped in with no rewrites (for WordPress: a top-level plugin
  file with a plugin header, plus its own bundled assets).
- Plugins should be **plug-and-play** — activate and go, with sensible defaults and
  graceful fallbacks. Avoid hard dependencies on the Local CMS runtime; a plugin
  exported to a stock WordPress install must stand on its own.
- Keep a plugin's bundled assets self-contained. Where an asset is shared with the
  Local CMS app (e.g. the Markdown engine), treat the app copy as the single source
  of truth and **sync** it into the plugin at build time rather than hand-editing two
  copies.

## Staple vs. temporary plugins

Some plugins here are throwaway experiments; others are permanent fixtures of this
repo.

| Plugin | Status | Notes |
| ------ | ------ | ----- |
| [`local-cms-markdown/`](local-cms-markdown/) | **Staple** | The Markdown conversion feature, packaged as a WordPress plugin. Shares the canonical engine in [`public/assets/`](../public/assets/). |

Temporary plugins can be removed once their experiment concludes; staple plugins are
maintained alongside the rest of the repo.

## Exporting a plugin

Each plugin ships a `build.php` that refreshes any synced assets and produces a
distributable zip under `_export-plugins/`:

```sh
php plugins/local-cms-markdown/build.php
```

For parity with the theme workflow, the committed `export` scripts also build a
plugin zip (delegating to the plugin's own `build.php` when present):

```bat
export plugins local-cms-markdown
```

```sh
./export.sh plugins local-cms-markdown
```

The zip's root is the plugin's files (no wrapping folder), so extract it straight
into `wp-content/plugins/<slug>/` in WordPress.
