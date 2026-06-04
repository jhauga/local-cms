#!/usr/bin/env bash
# ============================================================================
#  export.sh - Build a distributable zip for a Local CMS theme or plugin.
#
#  Linux/macOS port of export.bat. Uses standard tooling (zip, with a python3
#  fallback) and no external dependencies.
#
#  Usage:
#    ./export.sh [type] [name]
#      type : themes | plugins        (default: themes)
#      name : folder under <type>     (default: "default" for themes,
#             "local-cms-markdown" for plugins)
#
#  Examples:
#    ./export.sh                              # Zip themes/default
#    ./export.sh themes default               # Zip the default theme
#    ./export.sh plugins local-cms-markdown   # Build/zip the markdown plugin
#
#  Output: _export-<type>/<name>.zip whose ROOT is the folder's files (e.g.
#  style.css, template-parts/). Extract it straight into the target folder,
#  e.g. wp-content/themes/<slug>/ or wp-content/plugins/<slug>/.
# ============================================================================

set -euo pipefail

cd "$(dirname "$0")"

type="${1:-themes}"
name="${2:-}"

case "$type" in
  -h|--help|/?)
    echo "Usage: ./export.sh [themes|plugins] [name]"
    echo "  ./export.sh                              Zip themes/default"
    echo "  ./export.sh themes <name>                Zip a theme folder"
    echo "  ./export.sh plugins <name>               Build/zip a plugin folder"
    exit 0
    ;;
  themes|plugins) ;;
  *)
    echo "[export] Unknown type '$type'. Use 'themes' or 'plugins'." >&2
    exit 1
    ;;
esac

if [ -z "$name" ]; then
  if [ "$type" = "plugins" ]; then
    name="local-cms-markdown"
  else
    name="default"
  fi
fi

src="$type/$name"

if [ ! -d "$src" ]; then
  echo "[export] Source folder not found: $src" >&2
  exit 1
fi

# Plugins that ship their own build.php manage their assets + zip themselves.
if [ "$type" = "plugins" ] && [ -f "$src/build.php" ]; then
  if ! command -v php >/dev/null 2>&1; then
    echo "[export] PHP not found on PATH; cannot run $src/build.php" >&2
    exit 1
  fi
  echo "[export] Delegating to $src/build.php ..."
  exec php "$src/build.php"
fi

# The default theme folder ships as "local-cms.zip" (matches the WordPress slug).
zip_name="$name"
if [ "$name" = "default" ]; then
  zip_name="local-cms"
fi

out_dir="_export-$type"
zip_path="$out_dir/$zip_name.zip"

mkdir -p "$out_dir"
rm -f "$zip_path"

echo "[export] Zipping $src -> $zip_path"

# Zip the folder's CONTENTS at the archive root (no wrapping <name>/ directory),
# so it extracts straight into wp-content/<type>/<slug>/.
abs_zip="$(pwd)/$zip_path"
if command -v zip >/dev/null 2>&1; then
  ( cd "$src" && zip -rq "$abs_zip" . )
elif command -v python3 >/dev/null 2>&1; then
  python3 - "$src" "$zip_path" <<'PY'
import os, sys, zipfile

root, zip_path = sys.argv[1], sys.argv[2]

with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as archive:
    for dirpath, _dirs, files in os.walk(root):
        for filename in files:
            full = os.path.join(dirpath, filename)
            arcname = os.path.relpath(full, root)  # contents at the archive root
            archive.write(full, arcname)
PY
else
  echo "[export] Neither 'zip' nor 'python3' is available to build the archive." >&2
  exit 1
fi

echo "[export] Done: $zip_path"
