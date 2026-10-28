#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# wp-env mounts "." under the repository's own directory name, so that — not the
# plugin slug — is what WP-CLI addresses the plugin by.
PLUGIN_DIR_NAME="$(basename "$ROOT_DIR")"

# Route Docker CLI to Podman when no Docker daemon is reachable (e.g. after removing Colima).
if ! docker info &>/dev/null 2>&1 && command -v podman &>/dev/null; then
	_podman_sock="$(podman machine inspect --format '{{.ConnectionInfo.PodmanSocket.Path}}' 2>/dev/null || true)"
	if [[ -S "$_podman_sock" ]]; then
		export DOCKER_HOST="unix://${_podman_sock}"
	fi
fi

# Shim: wp-env writes host-gateway in extra_hosts which Podman (applehv) cannot resolve.
# Activate when the effective Docker endpoint is Podman (via DOCKER_HOST or active context).
_docker_endpoint="${DOCKER_HOST:-$(docker context inspect --format '{{.Endpoints.docker.Host}}' 2>/dev/null || true)}"
_is_podman=0
if command -v podman &>/dev/null; then
	[[ "$_docker_endpoint" == *podman* ]] && _is_podman=1
	# Also match when the endpoint is Podman's socket under a differently named context.
	if [[ $_is_podman -eq 0 ]]; then
		_podman_sock="$(podman machine inspect --format '{{.ConnectionInfo.PodmanSocket.Path}}' 2>/dev/null || true)"
		[[ -n "$_podman_sock" && "$_docker_endpoint" == *"$_podman_sock"* ]] && _is_podman=1
	fi
fi
if [[ $_is_podman -eq 1 ]]; then
	# Run awk inside the SSH session to avoid a local broken-pipe under set -o pipefail.
	_podman_host_ip="$(podman machine ssh 'ip route show default | awk "/default via/{print \$3; exit}"' 2>/dev/null || true)"
	_podman_host_ip="${_podman_host_ip:-192.168.127.1}"
	_real_docker="$(command -v docker)"
	_docker_shim_dir="$(mktemp -d)"

	cat > "$_docker_shim_dir/docker" << DOCKER_SHIM
#!/usr/bin/env bash
set -uo pipefail

if [[ "\${1:-}" == "compose" ]]; then
	compose_file=""
	for ((i=2; i<=\$#; i++)); do
		if [[ "\${!i}" == "-f" || "\${!i}" == "--file" ]]; then
			j=\$((i+1)); compose_file="\${!j}"; break
		fi
	done
	if [[ -z "\$compose_file" && -f "docker-compose.yml" ]]; then
		compose_file="docker-compose.yml"
	fi

	if [[ -n "\$compose_file" && -f "\$compose_file" ]] && grep -q 'host-gateway' "\$compose_file"; then
		# "-i.bak" is the only in-place form both BSD and GNU sed accept; the
		# BSD-only "-i ''" fails when GNU sed comes first on PATH. Never swallow
		# a failure here: Podman cannot resolve "host-gateway" and would fail
		# much later with an opaque "failed to create new hosts file" error.
		if ! sed -i.bak "s/host-gateway/${_podman_host_ip}/g" "\$compose_file"; then
			echo "wp-env-ensure: could not rewrite host-gateway in \$compose_file" >&2
			exit 1
		fi
		rm -f "\$compose_file.bak"
	fi
fi

exec "${_real_docker}" "\$@"
DOCKER_SHIM

	chmod +x "$_docker_shim_dir/docker"
	export PATH="$_docker_shim_dir:$PATH"
	# shellcheck disable=SC2064
	trap "rm -rf '$_docker_shim_dir'" EXIT
fi

export WP_ENV_HOME="${WP_ENV_HOME:-./.wp-env}"

WP_ENV_URL="${WP_ENV_URL:-http://localhost:8888}"
WP_ENV_TESTS_URL="${WP_ENV_TESTS_URL:-http://localhost:8889}"
WP_ENV_TITLE="${WP_ENV_TITLE:-$PLUGIN_DIR_NAME}"
WP_ENV_ADMIN_USER="${WP_ENV_ADMIN_USER:-admin}"
WP_ENV_ADMIN_PASSWORD="${WP_ENV_ADMIN_PASSWORD:-password}"
WP_ENV_ADMIN_EMAIL="${WP_ENV_ADMIN_EMAIL:-wordpress@example.com}"

if [[ -x "$ROOT_DIR/node_modules/.bin/wp-env" ]]; then
	WP_ENV_BIN=( "$ROOT_DIR/node_modules/.bin/wp-env" )
else
	WP_ENV_BIN=( npx --no-install wp-env )
fi

wp_env() {
	WP_ENV_HOME="$WP_ENV_HOME" "${WP_ENV_BIN[@]}" "$@"
}

wp_cli() {
	local environment="$1"
	shift

	wp_env run "$environment" wp "$@" --path=/var/www/html
}

is_installed() {
	local environment="$1"

	wp_cli "$environment" core is-installed >/dev/null 2>&1
}

activate_plugin() {
	local environment="$1"
	local plugin="$2"

	if wp_cli "$environment" plugin is-active "$plugin" >/dev/null 2>&1; then
		return 0
	fi

	wp_cli "$environment" plugin activate "$plugin" >/dev/null
}

# Plugins pulled from wordpress.org in .wp-env.json, by slug. wp-env names the
# extracted directory after the zip, so "woocommerce.latest-stable" is tried first.
dependency_plugins() {
	if [[ ! -f "$ROOT_DIR/.wp-env.json" ]]; then
		return 0
	fi

	grep -oE 'downloads\.wordpress\.org/plugin/[a-z0-9-]+' "$ROOT_DIR/.wp-env.json" \
		| sed 's|.*/||' \
		| sort -u
}

activate_required_plugins() {
	local environment="$1"
	local plugin

	while read -r plugin; do
		[[ -z "$plugin" ]] && continue
		activate_plugin "$environment" "${plugin}.latest-stable" \
			|| activate_plugin "$environment" "$plugin" \
			|| echo "wp-env: could not activate dependency plugin ${plugin}."
	done < <(dependency_plugins)

	activate_plugin "$environment" "$PLUGIN_DIR_NAME"
}

# A fresh wp-env install ships no .htaccess, so Apache 404s every pretty
# permalink (pages and CPT archives alike). A hard flush writes the file.
ensure_permalinks() {
	local environment="$1"

	if [[ "$(wp_cli "$environment" option get permalink_structure 2>/dev/null || true)" != *%postname%* ]]; then
		wp_cli "$environment" option update permalink_structure '/%postname%/' >/dev/null
	fi

	# Do not swallow the error: without .htaccess every pretty permalink 404s
	# even though the site is otherwise reachable.
	if ! wp_cli "$environment" rewrite flush --hard >/dev/null 2>&1; then
		echo "wp-env: rewrite flush failed for ${environment} — pretty permalinks will most likely 404."
	fi
}

repair_site() {
	local environment="$1"
	local url="$2"

	if is_installed "$environment"; then
		activate_required_plugins "$environment"
		ensure_permalinks "$environment"
		return 0
	fi

	echo "wp-env: WordPress in ${environment} is not installed; attempting in-place repair."

	if ! wp_cli "$environment" db query "SELECT 1" >/dev/null 2>&1; then
		echo "wp-env: Database for ${environment} is not reachable."
		return 1
	fi

	if ! wp_cli "$environment" core install \
		--url="$url" \
		--title="$WP_ENV_TITLE" \
		--admin_user="$WP_ENV_ADMIN_USER" \
		--admin_password="$WP_ENV_ADMIN_PASSWORD" \
		--admin_email="$WP_ENV_ADMIN_EMAIL" \
		--skip-email; then
		echo "wp-env: In-place WordPress install failed for ${environment}."
		return 1
	fi

	wp_cli "$environment" option update home "$url" >/dev/null
	wp_cli "$environment" option update siteurl "$url" >/dev/null
	activate_required_plugins "$environment"
	ensure_permalinks "$environment"
	is_installed "$environment"
}

ensure_sites() {
	local failed=0

	repair_site cli "$WP_ENV_URL" || failed=1
	repair_site tests-cli "$WP_ENV_TESTS_URL" || failed=1

	return "$failed"
}

wait_for_http_site() {
	local url="$1"
	local label="$2"
	local deadline=$(( SECONDS + 120 ))
	local body

	while (( SECONDS < deadline )); do
		body="$(curl -fsS --max-time 5 "${url}/wp-login.php" 2>/dev/null || true)"
		if [[ "$body" == *'id="user_login"'* ]]; then
			return 0
		fi

		sleep 2
	done

	echo "wp-env: ${label} did not become HTTP-ready at ${url}."
	return 1
}

wait_for_http_sites() {
	local failed=0

	wait_for_http_site "$WP_ENV_URL" "WordPress development site" || failed=1
	wait_for_http_site "$WP_ENV_TESTS_URL" "WordPress test site" || failed=1

	return "$failed"
}

# Return path to the wp-env docker-compose.yml (empty string if not found).
# Uses -print -quit (not just -quit) so BSD find on macOS actually prints the result.
_compose_file() {
	find "$WP_ENV_HOME" -maxdepth 2 -name "docker-compose.yml" -print -quit 2>/dev/null || true
}

# wp-env's extract-zip sometimes fails to rename the extraction temp dir on Podman/macOS,
# leaving plugin zips extracted into .temp subdirs instead of the final bind-mount path.
# This function detects and fixes that, and also flattens the nested-dir case.
_ensure_plugin_sources() {
	local hash_dir
	hash_dir="$(find "$WP_ENV_HOME" -maxdepth 1 -mindepth 1 -type d -print -quit 2>/dev/null)" || true
	[[ -z "$hash_dir" ]] && return 0

	for zip_file in "$hash_dir"/*.zip; do
		[[ -f "$zip_file" ]] || continue
		local final_dir="${zip_file%.zip}"

		# Determine the plugin's own directory name from the zip's first entry.
		local plugin_name
		# awk NR==1 reads only one line; no exit needed, so no broken-pipe.
		plugin_name="$(unzip -Z1 "$zip_file" 2>/dev/null | awk -F/ 'NR==1{print $1}')" || true
		[[ -z "$plugin_name" ]] && continue

		# Skip if final_dir already has a PHP file at its root (correct extraction).
		# Use find -quit to avoid a broken-pipe when piping to grep/head.
		if [[ -n "$(find "$final_dir" -maxdepth 1 -name "*.php" -print -quit 2>/dev/null)" ]]; then
			continue
		fi

		# Case 1a: nested extraction — plugin dir ended up inside final_dir.
		if [[ -d "$final_dir/$plugin_name" ]]; then
			local tmp="${final_dir}_flatten"
			mv "$final_dir/$plugin_name" "$tmp"
			rm -rf "$final_dir"
			mv "$tmp" "$final_dir"
			continue
		fi

		# Case 1b: extract from zip.
		local temp_dir="${final_dir}.temp"
		rm -rf "$final_dir" "$temp_dir"
		unzip -q "$zip_file" -d "$temp_dir" 2>/dev/null || continue
		if [[ -d "$temp_dir/$plugin_name" ]]; then
			mv "$temp_dir/$plugin_name" "$final_dir"
			rm -rf "$temp_dir"
		else
			mv "$temp_dir" "$final_dir"
		fi
	done
}

# Bring all compose services up without relying on wp-env's own lifecycle management.
_compose_up() {
	local compose_file
	compose_file="$(_compose_file)"
	[[ -z "$compose_file" ]] && return 1
	docker compose -f "$compose_file" up -d
}

# Probe MySQL directly via docker compose exec — does not require the cli service.
_mysql_ready() {
	local service="$1"
	local compose_file
	compose_file="$(_compose_file)"
	[[ -z "$compose_file" ]] && return 1
	docker compose -f "$compose_file" exec -T "$service" \
		mariadb -uroot -ppassword -e "SELECT 1" &>/dev/null 2>&1
}

wait_for_dbs() {
	local deadline=$(( SECONDS + 90 ))
	local dev=0 tests=0
	while (( SECONDS < deadline )); do
		[[ $dev   -eq 0 ]] && _mysql_ready mysql       && dev=1
		[[ $tests -eq 0 ]] && _mysql_ready tests-mysql && tests=1
		[[ $dev -eq 1 && $tests -eq 1 ]] && return 0
		sleep 3
	done
	echo "wp-env: Databases did not become ready in time."
	return 1
}

destroy_and_restart() {
	echo "wp-env: Repair failed; destroying wp-env state and starting fresh."
	# 'yes | wp_env destroy' causes SIGPIPE (141) under set -euo pipefail; echo y is sufficient.
	echo y | wp_env destroy

	# Remove stale .temp dirs so extract-zip starts clean.
	find "$WP_ENV_HOME" -maxdepth 2 -name "*.temp" -exec rm -rf {} + 2>/dev/null || true

	# --update forces source re-extraction after destroy removed plugin directories.
	wp_env start --update

	# Ensure plugin dirs are correctly extracted and all containers are up.
	_ensure_plugin_sources
	_compose_up
	wait_for_dbs
	ensure_sites
	wait_for_http_sites
}

start() {
	wp_env start

	# Ensure plugin dirs are correctly extracted even if wp-env's extraction failed.
	_ensure_plugin_sources
	# Bring all containers up explicitly — wp-env may skip some when shouldConfigureWp=true.
	_compose_up

	if ! wait_for_dbs || ! ensure_sites || ! wait_for_http_sites; then
		destroy_and_restart
	fi
}

seed() {
	ensure_sites || destroy_and_restart

	if [[ ! -f "$ROOT_DIR/tests/seed.php" ]]; then
		echo "wp-env: no tests/seed.php — nothing to seed."
		return 0
	fi

	wp_env run cli wp eval-file "/var/www/html/wp-content/plugins/${PLUGIN_DIR_NAME}/tests/seed.php"
}

case "${1:-start}" in
	start)
		start
		;;
	stop)
		wp_env stop
		;;
	ensure)
		ensure_sites
		;;
	seed)
		seed
		;;
	*)
		echo "Usage: $0 {start|stop|ensure|seed}" >&2
		exit 2
		;;
esac
