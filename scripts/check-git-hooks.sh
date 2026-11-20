#!/usr/bin/env bash
# Verifies that Husky (npm's `prepare` script) pointed Git at the .husky hooks.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

# Release archives and CI checkouts without a .git directory have no hooks to check.
if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    exit 0
fi

configured_path="$(git config --local --get core.hooksPath || true)"
if [[ "$configured_path" != .husky/_ ]]; then
    printf 'Git core.hooksPath is %s; expected .husky/_ (run npm install).\n' "${configured_path:-unset}" >&2
    exit 1
fi
for hook in pre-commit pre-push; do
    if [[ ! -f ".husky/_/$hook" ]]; then
        printf 'Missing Husky hook shim .husky/_/%s (run npm install).\n' "$hook" >&2
        exit 1
    fi
done
