#!/usr/bin/env bash
set -euo pipefail

file="${1:-.copier-answers.yml}"
src="$(yq -r '._src_path // ""' "$file")"
if [[ -z "$src" ]]; then
  echo "::error::_src_path missing or empty in $file" >&2
  exit 1
fi
if [[ ! "$src" =~ ^https://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$ ]]; then
  echo "::error::_src_path must be https://github.com/<owner>/<repo>, got: $src" >&2
  exit 1
fi
printf '%s\n' "$src"
