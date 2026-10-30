#!/usr/bin/env bash
#
# Builds an installable plugin ZIP for GitHub Releases.
#
# Output: build/jp-permissions-v<VERSION>.zip
#         (contains the folder jp-permissions/ as its top level)
#
# Runtime dependencies are installed with --no-dev and then prefixed with
# php-scoper. WordPress loads every active plugin into one PHP process, so two
# JayPee plugins bundling different versions of the same library (Plugin Update
# Checker above all) would otherwise race for the same class names and whichever
# loaded first would win. Prefixing removes that race; includes/class-updates.php
# resolves the prefixed name at runtime.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

SLUG="jp-permissions"
PHP_SCOPER_VERSION="0.18.19"

# SHA-256 of the pinned php-scoper.phar. A version tag is not a content pin:
# GitHub release assets can be replaced under an existing tag, and this PHAR
# rewrites the code that ends up in the shipped ZIP. Bump it together with
# PHP_SCOPER_VERSION; the build refuses to run on a mismatch.
PHP_SCOPER_SHA256="170fb84bd3390defb30f99f7dc39c9a89d10c29973accc26f31c00abc5b25933"

"$ROOT_DIR/scripts/check-version.sh" "${1:-}"

VERSION="$(sed -n -E "s/^const VERSION[[:space:]]*=[[:space:]]*'([^']+)'.*/\\1/p" "$ROOT_DIR/$SLUG.php" | head -n1)"

BUILD_DIR="$ROOT_DIR/build"
DIST_DIR="$BUILD_DIR/$SLUG"
STAGE_DIR="$BUILD_DIR/.stage"
SCOPED_DIR="$BUILD_DIR/.scoped"
ZIP_NAME="${SLUG}-v${VERSION}.zip"

rm -rf "$BUILD_DIR"
mkdir -p "$DIST_DIR" "$STAGE_DIR"

# ── Production files ─────────────────────────────────────────────────────────
cp "$ROOT_DIR/$SLUG.php" "$DIST_DIR/"

for path in README.md readme.txt LICENSE includes assets admin blocks languages; do
	if [[ -e "$ROOT_DIR/$path" ]]; then
		cp -R "$ROOT_DIR/$path" "$DIST_DIR/"
	fi
done

# WordPress loads compiled .mo catalogs at runtime. Compile them in the staged
# release directory only, so the working tree never receives build artifacts.
shopt -s nullglob
po_files=( "$DIST_DIR/languages"/*.po )
shopt -u nullglob

if [[ -n "${po_files[0]:-}" ]]; then
	if ! command -v msgfmt >/dev/null 2>&1; then
		echo "build-release: msgfmt (gettext) is required to compile translation catalogs" >&2
		exit 1
	fi

	for po_file in "${po_files[@]}"; do
		msgfmt --check --output-file "${po_file%.po}.mo" "$po_file"
	done
fi

# ── Bundled runtime dependencies, prefixed ───────────────────────────────────
resolve_php_scoper() {
	if [[ -x "$ROOT_DIR/vendor/bin/php-scoper" ]]; then
		echo "$ROOT_DIR/vendor/bin/php-scoper"
		return 0
	fi

	if command -v php-scoper >/dev/null 2>&1; then
		command -v php-scoper
		return 0
	fi

	# Pinned PHAR, cached across builds. Renovate keeps PHP_SCOPER_VERSION current.
	local cache_dir="${XDG_CACHE_HOME:-$HOME/.cache}/jaypee"
	local phar="$cache_dir/php-scoper-${PHP_SCOPER_VERSION}.phar"

	if [[ ! -f "$phar" ]]; then
		mkdir -p "$cache_dir"
		# renovate: datasource=github-releases depName=humbug/php-scoper
		curl -fsSL --retry 3 \
			-o "$phar.tmp" \
			"https://github.com/humbug/php-scoper/releases/download/${PHP_SCOPER_VERSION}/php-scoper.phar"

		# Verify before the file becomes executable or enters the cache, so a
		# tampered download is never reachable by a later build.
		if ! verify_php_scoper "$phar.tmp"; then
			rm -f "$phar.tmp"
			return 1
		fi

		chmod +x "$phar.tmp"
		mv "$phar.tmp" "$phar"
	else
		# The cache may predate this check, or have been written by something
		# else entirely; a cached PHAR is not more trustworthy than a fresh one.
		verify_php_scoper "$phar" || return 1
	fi

	echo "$phar"
}

sha256_of() {
	if command -v sha256sum >/dev/null 2>&1; then
		sha256sum "$1" | cut -d' ' -f1
	elif command -v shasum >/dev/null 2>&1; then
		shasum -a 256 "$1" | cut -d' ' -f1
	else
		echo "build-release: neither sha256sum nor shasum is available to verify php-scoper" >&2
		return 1
	fi
}

verify_php_scoper() {
	local file="$1"
	local actual

	actual="$(sha256_of "$file")" || return 1

	if [[ "$actual" != "$PHP_SCOPER_SHA256" ]]; then
		cat >&2 <<-EOF
			build-release: php-scoper.phar does not match the pinned checksum.

			  expected: $PHP_SCOPER_SHA256
			  actual:   $actual

			Refusing to run it: this PHAR rewrites the code that goes into the
			release ZIP. If PHP_SCOPER_VERSION was just bumped, confirm the new
			release and update PHP_SCOPER_SHA256 in scripts/build-release.sh:

			  curl -fsSL https://github.com/humbug/php-scoper/releases/download/${PHP_SCOPER_VERSION}/php-scoper.phar | shasum -a 256
		EOF
		return 1
	fi
}

cp "$ROOT_DIR/composer.json" "$STAGE_DIR/"
if [[ -f "$ROOT_DIR/composer.lock" ]]; then
	cp "$ROOT_DIR/composer.lock" "$STAGE_DIR/"
fi

composer install \
	--working-dir="$STAGE_DIR" \
	--no-dev --no-interaction --no-progress --no-scripts --prefer-dist

if [[ -d "$STAGE_DIR/vendor" ]]; then
	PHP_SCOPER="$(resolve_php_scoper)"

	# php-scoper resolves the finder paths in scoper.inc.php relative to the
	# working directory, so the staged copy has to be the one it runs in.
	( cd "$STAGE_DIR" && "$PHP_SCOPER" add-prefix \
		--config="$ROOT_DIR/scoper.inc.php" \
		--output-dir="$SCOPED_DIR" \
		--force --no-interaction --quiet )

	composer dump-autoload \
		--working-dir="$SCOPED_DIR" \
		--no-dev --classmap-authoritative --no-interaction

	cp -R "$SCOPED_DIR/vendor" "$DIST_DIR/vendor"

	# A silent scoping failure would ship colliding class names to every site
	# running another JayPee plugin, and only show up as a fatal error there.
	if grep -rqs --include='*.php' -E '^[[:space:]]*namespace[[:space:]]+YahnisElsts' "$DIST_DIR/vendor"; then
		echo "build-release: bundled dependencies are not prefixed — php-scoper did not run correctly" >&2
		exit 1
	fi

	# Prefixing can succeed and still leave PUC's string-resolved classes
	# unusable. That failure is invisible in the ZIP and only fatals on a live
	# site, so load the bundle here and check that it actually resolves.
	php "$ROOT_DIR/scripts/verify-scoped-bundle.php" "$DIST_DIR/vendor"
fi

# ── Package ──────────────────────────────────────────────────────────────────
find "$DIST_DIR" -name '.DS_Store' -delete

( cd "$BUILD_DIR" && zip -rq "$ZIP_NAME" "$SLUG" )

rm -rf "$STAGE_DIR" "$SCOPED_DIR"

echo "build-release: $BUILD_DIR/$ZIP_NAME"
