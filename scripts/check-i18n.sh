#!/usr/bin/env bash
# Validates every languages/*.po catalog with `msgfmt -c`: syntax, duplicate
# entries (per msgctxt) and printf-style format strings.
set -euo pipefail
ROOT_DIR="${CHECK_I18N_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
if ! command -v msgfmt >/dev/null 2>&1; then
    if [[ "${CI:-}" == true || "${GITHUB_ACTIONS:-}" == true ]] && [[ "${ALLOW_MISSING_MSGFMT:-}" != 1 ]]; then
        echo 'check-i18n: msgfmt (gettext) is required in CI; set ALLOW_MISSING_MSGFMT=1 to skip.' >&2
        exit 1
    fi
    echo 'check-i18n: msgfmt unavailable; skipping catalog validation.' >&2
    exit 0
fi
shopt -s nullglob
catalogs=("$ROOT_DIR"/languages/*.po)
if [[ -z "${catalogs[0]:-}" ]]; then
    echo 'check-i18n: no languages/*.po catalogs found; skipping catalog validation.'
    exit 0
fi
failed=0
for catalog in "${catalogs[@]}"; do
    msgfmt -c --output-file=/dev/null "$catalog" || failed=1
done
exit "$failed"
