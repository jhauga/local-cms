#!/usr/bin/env bash
# Local CMS inbound adapter for port-cms (Linux/macOS). Delegates to transform.php.
set -euo pipefail

here="$(cd "$(dirname "$0")" && pwd)"

if ! command -v php >/dev/null 2>&1; then
  echo "[local-cms] PHP not found on PATH; the Local CMS adapter needs PHP." >&2
  exit 1
fi

exec php "$here/transform.php" "$@"
