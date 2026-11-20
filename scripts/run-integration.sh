#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd -P)"
cd "$ROOT_DIR"
source "$ROOT_DIR/scripts/e2e-worktree.sh"
jps_e2e_configure_worktree "$ROOT_DIR"
jps_e2e_acquire_lock "$ROOT_DIR"

WP_ENV_HOME="$WP_ENV_HOME" npx --no-install wp-env run tests-cli \
	--env-cwd="wp-content/plugins/$(basename "$ROOT_DIR")" \
	vendor/bin/phpunit -c phpunit-integration.xml.dist
