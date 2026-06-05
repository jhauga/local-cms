#!/usr/bin/env bash
# ============================================================================
#  port-cms.sh - Port a Local CMS theme or plugin to another CMS (Linux/macOS).
#
#  Companion to export.sh. Where export packages a theme/plugin for stock
#  WordPress, port-cms stages it for a different content management system
#  (Drupal, Joomla, Ghost, Grav, ...). This is the cross-platform base: it
#  handles argument parsing, output scaffolding, .gitignore hygiene, staging,
#  and archiving. Each target CMS is registered in port-cms/registry.txt and
#  may ship an optional adapter at port-cms/<cms>/transform.sh that rewrites
#  the staged files into that platform's conventions.
#
#  Usage:
#    ./port-cms.sh <cms> <tool/name>     Port a theme or plugin to <cms>
#    ./port-cms.sh -l | --list           List available CMS targets
#    ./port-cms.sh -h | --help           Show this help
#
#      <cms>       Target CMS (case-insensitive, e.g. drupal, DrUpAL)
#      tool/name   tool = theme(s) | plugin(s); name = folder under that tool
#
#  Examples:
#    ./port-cms.sh drupal themes/default
#    ./port-cms.sh drupal plugin/local-cms-markdown
#
#  Output (Linux/macOS): _port-<tool>/<cms>/<slug>.tar.gz
#  The default theme/plugin name maps to the "local-cms" slug, since most CMS
#  already ship a theme named "default".
# ============================================================================

set -euo pipefail

cd "$(dirname "$0")"

registry="port-cms/registry.txt"

usage() {
  echo "Usage: ./port-cms.sh <cms> <tool/name>"
  echo "  ./port-cms.sh drupal themes/default              Port the default theme to Drupal"
  echo "  ./port-cms.sh drupal plugin/local-cms-markdown   Port the markdown plugin to Drupal"
  echo "  ./port-cms.sh -l, --list                         List available CMS targets"
  echo "  ./port-cms.sh -h, --help                         Show this help"
}

# Trim surrounding whitespace and strip inline '#' comments from a registry line.
clean_line() {
  local s="${1%%#*}"
  s="${s#"${s%%[![:space:]]*}"}"
  s="${s%"${s##*[![:space:]]}"}"
  printf '%s' "$s"
}

arg1="${1:-}"
arg2="${2:-}"

case "$arg1" in
  -h|--help|/\?|"") usage; exit 0 ;;
esac

if [ ! -f "$registry" ]; then
  echo "[port-cms] Registry not found: $registry" >&2
  exit 1
fi

if [ "$arg1" = "-l" ] || [ "$arg1" = "--list" ]; then
  echo "Available CMS targets for porting:"
  while IFS= read -r line || [ -n "$line" ]; do
    line="$(clean_line "$line")"
    [ -z "$line" ] && continue
    note=""
    if [ -f "port-cms/$line/transform.sh" ] || [ -f "port-cms/$line/transform.bat" ]; then
      note=" (adapter)"
    fi
    echo "  $line$note"
  done < "$registry"
  exit 0
fi

target="$arg1"
spec="$arg2"

if [ -z "$spec" ]; then
  echo "[port-cms] Missing tool/name. Example: ./port-cms.sh drupal themes/default" >&2
  exit 1
fi

# Resolve the CMS against the registry (case-insensitive); keep its casing.
cms=""
target_lc="$(printf '%s' "$target" | tr '[:upper:]' '[:lower:]')"
while IFS= read -r line || [ -n "$line" ]; do
  line="$(clean_line "$line")"
  [ -z "$line" ] && continue
  line_lc="$(printf '%s' "$line" | tr '[:upper:]' '[:lower:]')"
  if [ "$line_lc" = "$target_lc" ]; then
    cms="$line"
    break
  fi
done < "$registry"

if [ -z "$cms" ]; then
  echo "[port-cms] Unsupported CMS \"$target\". Run \"./port-cms.sh --list\" to see options." >&2
  exit 1
fi

# Split tool/name on the first slash.
tool="${spec%%/*}"
name="${spec#*/}"
if [ "$name" = "$spec" ] || [ -z "$name" ]; then
  echo "[port-cms] Expected tool/name, e.g. themes/default or plugin/local-cms-markdown." >&2
  exit 1
fi

# Normalize the tool to its folder name (accept singular or plural).
case "$tool" in
  theme|themes)   tool="themes" ;;
  plugin|plugins) tool="plugins" ;;
  *)
    echo "[port-cms] Unknown tool \"$tool\". Use \"theme(s)\" or \"plugin(s)\"." >&2
    exit 1
    ;;
esac

src="$tool/$name"
if [ ! -d "$src" ]; then
  echo "[port-cms] Source folder not found: $src" >&2
  exit 1
fi

# The "default" theme/plugin ships under the "local-cms" slug.
slug="$name"
[ "$name" = "default" ] && slug="local-cms"

# Ensure the per-tool output root is gitignored.
out_root="_port-$tool"
if ! grep -qi "$out_root" .gitignore 2>/dev/null; then
  echo "$out_root/" >> .gitignore
fi

out_dir="$out_root/$cms"
mkdir -p "$out_dir"

# Stage a clean copy of the source for transformation.
work="$out_dir/$slug"
rm -rf "$work"
mkdir -p "$work"
echo "[port-cms] Staging $src -> $work"
cp -R "$src/." "$work/"

# Apply the optional per-CMS adapter (the "add a CMS" extension seam).
hook="port-cms/$cms/transform.sh"
if [ -f "$hook" ]; then
  echo "[port-cms] Applying $cms adapter: $hook"
  if ! bash "$hook" "$tool" "$name" "$work"; then
    echo "[port-cms] Adapter failed for $cms." >&2
    exit 1
  fi
else
  echo "[port-cms] No $cms adapter found; packaging the staged source as-is."
fi

# Archive: tar.gz on Linux/macOS.
targz="$out_dir/$slug.tar.gz"
rm -f "$targz"
if ! command -v tar >/dev/null 2>&1; then
  echo "[port-cms] 'tar' not found on PATH; cannot build $targz" >&2
  exit 1
fi
echo "[port-cms] Packaging $targz"
tar -czf "$targz" -C "$work" .

echo "[port-cms] Done: $targz"
