#!/usr/bin/env bash
# Downloads the WordPress core and plugin sources named in .wp-env.json so that
# check-hook-names.sh can look hooks up without wp-env. Used by the CI lint jobs;
# .wp-env.json stays the single place where versions are pinned (and bumped by
# Renovate).
#
# Sources land in ${HOOK_SOURCE_DIR:-${RUNNER_TEMP:-${TMPDIR:-/tmp}}/hook-sources}.
# WP_CORE_DIR (and WOOCOMMERCE_DIR when WooCommerce is listed) are appended to
# $GITHUB_ENV when that is set, otherwise printed as `export` lines.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG="$ROOT_DIR/.wp-env.json"
DEST="${HOOK_SOURCE_DIR:-${RUNNER_TEMP:-${TMPDIR:-/tmp}}/hook-sources}"

if [[ ! -f "$CONFIG" ]]; then
    echo "fetch-hook-sources: $CONFIG not found." >&2
    exit 1
fi

# Prints "core<TAB>URL" and "plugin<TAB>URL" lines; composer:2 has php but no jq.
entries="$(php -r '
$config = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
if (is_string($config["core"] ?? null)) {
    echo "core\t", $config["core"], "\n";
}
foreach ($config["plugins"] ?? [] as $plugin) {
    if (is_string($plugin)) {
        echo "plugin\t", $plugin, "\n";
    }
}
' "$CONFIG")"

# download_zip URL PARENT_DIR TOP_LEVEL: replaces PARENT_DIR/TOP_LEVEL with the
# archive contents (every ZIP used here has a single top-level directory).
download_zip() {
    local url="$1" parent="$2" top="$3" archive
    archive="$DEST/$(basename "$url")"
    curl -fsSL --retry 3 -o "$archive" "$url"
    rm -rf "${parent:?}/$top"
    mkdir -p "$parent"
    unzip -q "$archive" -d "$parent"
    rm -f "$archive"
}

mkdir -p "$DEST"
core_dir=''
wc_dir=''
while IFS=$'\t' read -r kind url; do
    case "$kind:$url" in
        core:https://wordpress.org/*.zip)
            download_zip "$url" "$DEST/core" wordpress
            core_dir="$DEST/core/wordpress"
            ;;
        core:*)
            echo "fetch-hook-sources: .wp-env.json \"core\" is not a wordpress.org ZIP: $url" >&2
            exit 1
            ;;
        plugin:https://downloads.wordpress.org/plugin/*.zip)
            file="$(basename "$url")"
            slug="${file%%.*}"
            download_zip "$url" "$DEST/plugins" "$slug"
            if [[ "$slug" == woocommerce ]]; then
                wc_dir="$DEST/plugins/$slug"
            fi
            ;;
    esac
done <<< "$entries"

if [[ ! -f "$core_dir/wp-includes/version.php" ]]; then
    echo 'fetch-hook-sources: WordPress core source missing after download.' >&2
    exit 1
fi
if [[ -n "$wc_dir" && ! -f "$wc_dir/woocommerce.php" ]]; then
    echo 'fetch-hook-sources: WooCommerce source missing after download.' >&2
    exit 1
fi

exports=("WP_CORE_DIR=$core_dir")
if [[ -n "$wc_dir" ]]; then
    exports+=("WOOCOMMERCE_DIR=$wc_dir")
fi
for line in "${exports[@]}"; do
    if [[ -n "${GITHUB_ENV:-}" ]]; then
        echo "$line" >> "$GITHUB_ENV"
    else
        echo "export $line"
    fi
done
