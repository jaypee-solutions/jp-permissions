#!/usr/bin/env bash
# Fails when the plugin hooks a name that WordPress does not define.
# Sources come from WP_CORE_DIR (CI: scripts/fetch-hook-sources.sh) or from the
# wp-env cache under .wp-env/. Without sources it fails in CI and skips locally.
# The checking itself lives in check-hook-names.php; extra hook names and prefixes
# go into hook-allowlist.txt.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
WP_ENV_HOME="${WP_ENV_HOME:-$ROOT_DIR/.wp-env}"
CORE_DIR="${WP_CORE_DIR:-}"
if [[ -z "$CORE_DIR" ]]; then
    core_version="$(find "$WP_ENV_HOME" -type f -path '*/wp-includes/version.php' -print -quit 2>/dev/null || true)"
    [[ -n "$core_version" ]] && CORE_DIR="${core_version%/wp-includes/version.php}"
fi

source_missing=0
[[ -n "$CORE_DIR" && -f "$CORE_DIR/wp-includes/version.php" ]] || source_missing=1

if ((source_missing)); then
    if [[ "${CI:-}" == true || "${GITHUB_ACTIONS:-}" == true ]]; then
        echo 'check-hook-names: WordPress source unavailable in CI.' >&2
        exit 1
    fi
    echo 'check-hook-names: WordPress source unavailable; skipping locally.' >&2
    exit 0
fi
php "$SCRIPT_DIR/check-hook-names.php" "$ROOT_DIR" "$CORE_DIR" "" 'jp_permissions_' "$SCRIPT_DIR/hook-allowlist.txt"
