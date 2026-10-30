#!/usr/bin/env bash
# run-e2e.sh — reliable E2E test runner for wp-env + Playwright.
#
# OpCache note: WordPress runs under PHP OpCache inside Docker. Any .php file
# change is invisible until OpCache is reset. This script flushes both the
# "cli" and "tests-cli" containers before seeding so the test run always
# reflects the current source files, not cached bytecode.
set -euo pipefail

cd "$(dirname "$0")/.."

# ── Defaults ────────────────────────────────────────────────────────────────
DO_STOP=false
DO_SEED=true
DO_OPCACHE=true
PLAYWRIGHT_ARGS=()

# ── Argument parsing ─────────────────────────────────────────────────────────
usage() {
  cat <<EOF
Usage: scripts/run-e2e.sh [options]

  --grep PATTERN   Forward to Playwright (run matching tests only)
  --stop           Stop wp-env after the run
  --no-seed        Skip seed (use existing DB state)
  --no-opcache     Skip OpCache flush (when no PHP files changed)
  --              Forward remaining arguments to Playwright
  -h, --help       Show this help
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --grep)
      if [[ $# -lt 2 || -z "${2-}" ]]; then
        echo "Error: --grep requires a PATTERN argument." >&2; usage; exit 1
      fi
      PLAYWRIGHT_ARGS+=(--grep "$2"); shift 2 ;;
    --stop)
      DO_STOP=true; shift ;;
    --no-seed)
      DO_SEED=false; shift ;;
    --no-opcache)
      DO_OPCACHE=false; shift ;;
    -h|--help)
      usage; exit 0 ;;
    --)
      shift
      PLAYWRIGHT_ARGS+=("$@")
      break ;;
    *)
      PLAYWRIGHT_ARGS+=("$1"); shift ;;
  esac
done

WP_ENV_PORT="${WP_ENV_PORT:-8888}"
WP_ENV_URL="${WP_ENV_URL:-http://localhost:${WP_ENV_PORT}}"

# ── wp-env: start only if not already running ────────────────────────────────
WP_BASE_URL="${WP_ENV_URL:-http://localhost:8888}"
if curl -fs --max-time 3 "$WP_BASE_URL/wp-login.php" | grep -q 'id="user_login"'; then
  echo "▶ wp-env already running, skipping start."
else
  echo "▶ Starting wp-env..."
  npm run env:start
fi

# ── OpCache flush ────────────────────────────────────────────────────────────
if [[ "$DO_OPCACHE" == true ]]; then
  echo "▶ Flushing OpCache (cli + tests-cli)..."
  for container in cli tests-cli; do
    WP_ENV_HOME=./.wp-env node_modules/.bin/wp-env run "$container" \
      wp eval 'opcache_reset();' --path=/var/www/html
  done
fi

# ── Seed ─────────────────────────────────────────────────────────────────────
if [[ "$DO_SEED" == true ]]; then
  echo "▶ Seeding fixtures..."
  npm run env:seed --silent
fi

# ── Playwright env vars ───────────────────────────────────────────────────────
export WP_BASE_URL
export WP_ADMIN_USER=admin
export WP_ADMIN_PASS=password

# Plugin-specific fixture IDs and the like: an optional, gitignored-or-committed
# hook so this runner stays the same file in every plugin.
if [[ -f scripts/e2e-env.sh ]]; then
  # shellcheck disable=SC1091
  source scripts/e2e-env.sh
fi

# ── Run Playwright ────────────────────────────────────────────────────────────
echo "▶ Running E2E tests..."
set +e
npx --no-install playwright test "${PLAYWRIGHT_ARGS[@]}"
PLAYWRIGHT_EXIT=$?
set -e

# ── Optional stop ─────────────────────────────────────────────────────────────
if [[ "$DO_STOP" == true ]]; then
  echo "▶ Stopping wp-env..."
  npm run env:stop || true
fi

exit $PLAYWRIGHT_EXIT
