#!/usr/bin/env bash
# Drupal adapter for port-cms (Linux/macOS). Delegates to transform.php.
set -euo pipefail

here="$(cd "$(dirname "$0")" && pwd)"

if ! command -v php >/dev/null 2>&1; then
  echo "[drupal] PHP not found on PATH; the Drupal adapter needs PHP." >&2
  exit 1
fi

exec php "$here/transform.php" "$@"
