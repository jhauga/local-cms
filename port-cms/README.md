# port-cms

Port a theme or plugin staged in this repo from WordPress to another content
management system. `port-cms` is the cross-platform companion to `export`:
`export` packages a theme/plugin for stock WordPress, while `port-cms` stages it
for a different CMS (Drupal, Joomla, Ghost, Grav, ...).

This directory is the tool's base. Individual CMS platforms are added one at a
time as small, self-contained adapters.

## Usage

```bash
port-cms <cms> <tool/name>     # Port a theme or plugin to <cms>
port-cms -l | --list           # List available CMS targets
port-cms -h | --help           # Show help
```

| Part        | Meaning                                                            |
| :--         | :--                                                                |
| `<cms>`     | Target CMS, case-insensitive (`drupal`, `DrUpAL` resolve the same) |
| `tool/name` | `tool` = `theme(s)` or `plugin(s)`; `name` = folder under it       |

```bash
# Windows
port-cms drupal themes/default
port-cms drupal plugin/local-cms-markdown

# Linux/macOS
./port-cms.sh drupal themes/default
./port-cms.sh drupal plugin/local-cms-markdown
```

The `default` theme/plugin name maps to the `local-cms` slug, because most CMS
already ship a theme named `default`.

## Output

Each run stages a clean copy of the source under `_port-<tool>/<cms>/<slug>/`,
applies the CMS adapter (if present), then writes an OS-appropriate archive
beside it:

- Windows &rarr; `_port-<tool>/<cms>/<slug>.zip`
- Linux/macOS &rarr; `_port-<tool>/<cms>/<slug>.tar.gz`

Both `_port-themes/` and `_port-plugins/` are gitignored; the scripts also add
the entry on first run if it is missing.

## Inbound: porting a WordPress theme into Local CMS

The `local-cms` target reverses the flow. Instead of staging a Local CMS theme
for a foreign platform, it pulls a stock **WordPress theme** into this repo and
makes it runtime-compatible:

```bash
# Windows
port-cms local-cms themes/twentytwentyone

# Linux/macOS
./port-cms.sh local-cms themes/twentytwentyone
```

`port-cms` stages a clean copy, runs the
[local-cms adapter](local-cms/README.md) to verify the theme's license permits
porting, stamp the `Local CMS Runtime: compatible` marker, neutralize the
WordPress-only exit guards, and write a `LICENSE_NOTE.txt`, then asks before
writing back under `themes/`:

```
overwrite - y or n?
```

- `y` &rarr; overwrites `themes/<name>` in place
- `n` &rarr; writes a converted copy to `themes/port-<name>`, leaving the original

The ported theme renders against a broad WordPress compatibility layer, so a
stock classic theme that uses common template tags and theme-setup hooks works
without manual edits. The adapter refuses to port a theme whose license forbids
modifying the source (no-derivatives or proprietary) or cannot be determined,
and aborts without touching `themes/` if the source is not a portable WordPress
theme (no `style.css`, missing `Theme Name` header, or no `index.php` base
template). The adapter is written in PHP, so it requires PHP on the `PATH`.

## Adding a CMS

1. Add the CMS name to [registry.txt](registry.txt) (one per line).
2. Optionally add an adapter that rewrites the staged files into the target
   platform's conventions:
   - `port-cms/<cms>/transform.sh` — Linux/macOS
   - `port-cms/<cms>/transform.bat` — Windows

Until an adapter exists, `port-cms` still works: it packages the staged source
as-is, and `--list` shows the CMS without the `(adapter)` marker.

### Adapter contract

The script invokes the adapter after staging and before archiving:

```
transform <tool> <name> <staging_dir>
```

- `<tool>` — `themes` or `plugins`
- `<name>` — the original source folder name (e.g. `default`)
- `<staging_dir>` — absolute path to the staged copy; rewrite it **in place**

Exit non-zero to abort the port. Anything left in `<staging_dir>` when the
adapter returns is what gets archived, so adapters are free to add, remove,
rename, or restructure files to match the target CMS (manifests, template
hierarchy, info files, directory layout, and so on).
