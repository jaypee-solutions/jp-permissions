#!/usr/bin/env bash
#
# Verifies that the version numbers agree:
#   - plugin header "Version:" == PHP constant JayPee\Permissions\VERSION
#   - (optional) git tag vX.Y.Z == plugin version
#
# Usage: scripts/check-version.sh [<tag-or-ref>]
#   e.g. scripts/check-version.sh v1.0.0
#        scripts/check-version.sh refs/tags/v1.0.0

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_FILE="$ROOT_DIR/jp-permissions.php"

header_version="$(sed -n -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([0-9][^[:space:]]*).*/\1/p' "$PLUGIN_FILE" | head -n1)"
const_version="$(sed -n -E "s/^const VERSION[[:space:]]*=[[:space:]]*'([^']+)'.*/\\1/p" "$PLUGIN_FILE" | head -n1)"

if [[ -z "$header_version" || -z "$const_version" ]]; then
	echo "check-version: could not read the version from $PLUGIN_FILE" >&2
	exit 1
fi

if [[ "$header_version" != "$const_version" ]]; then
	echo "check-version: plugin header version ($header_version) != VERSION constant ($const_version)" >&2
	exit 1
fi

echo "check-version: plugin version is $header_version"

ref="${1:-}"
if [[ -n "$ref" ]]; then
	tag="${ref#refs/tags/}"
	tag="${tag#v}"
	if [[ "$tag" != "$header_version" ]]; then
		echo "check-version: git tag ($ref) != plugin version ($header_version)" >&2
		exit 1
	fi
	echo "check-version: tag $ref matches the plugin version"
fi
