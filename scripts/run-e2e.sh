#!/usr/bin/env bash
# run-e2e.sh — reliable E2E test runner for wp-env + Playwright.
#
# OpCache note: WordPress runs under PHP OpCache inside Docker. Any .php file
# change is invisible until OpCache is reset. This script flushes both the
# "cli" and "tests-cli" containers before seeding so the test run always
# reflects the current source files, not cached bytecode.
set -euo pipefail

cd "$(dirname "$0")/.."
source scripts/e2e-worktree.sh
jps_e2e_configure_worktree "$PWD"
# A lock inherited from a parent run belongs to that parent; never release it here.
LOCK_INHERITED="${E2E_LOCK_HELD:-}"
jps_e2e_acquire_lock "$PWD"

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
PLUGIN_DIR_NAME="$(basename "$(pwd -P)")"
WP_BASE_URL="${WP_ENV_URL:-http://localhost:${WP_ENV_PORT}}"

# Linked worktrees of every plugin hash into 50 port pairs and every main
# checkout uses 8888, so a site answering on our port may belong to another
# plugin. wp-env mounts this checkout at /wp-content/plugins/<directory name>/;
# fetch a static file from there and compare it with the local copy.
# Prints: ours | down | foreign
probe_site() {
  local body status
  body="$(mktemp)"
  status="$(curl -s --max-time 3 -o "$body" -w '%{http_code}' \
    "$WP_BASE_URL/wp-content/plugins/$PLUGIN_DIR_NAME/readme.txt" || true)"
  if [[ "$status" == 200 ]] && cmp -s "$body" readme.txt; then
    echo ours
  elif [[ -z "$status" || "$status" == 000 ]]; then
    echo down
  else
    echo foreign
  fi
  rm -f "$body"
}

case "$(probe_site)" in
  ours)
    echo "▶ wp-env already running, skipping start." ;;
  down)
    echo "▶ Starting wp-env..."
    npm run env:start ;;
  *)
    cat >&2 <<EOF
Error: $WP_BASE_URL answers, but it does not serve this checkout
($PWD): /wp-content/plugins/$PLUGIN_DIR_NAME/readme.txt there is missing or differs.
Another plugin's wp-env (or another service) is using port ${WP_ENV_PORT}.
Not running Playwright against it. Pick a free port pair and retry, e.g.:
  WP_ENV_PORT=9100 WP_ENV_TESTS_PORT=9101 npm run test:e2e:run
EOF
    exit 1 ;;
esac

# ── OpCache flush ────────────────────────────────────────────────────────────
if [[ "$DO_OPCACHE" == true ]]; then
  echo "▶ Flushing OpCache (cli + tests-cli)..."
  for container in cli tests-cli; do
    WP_ENV_HOME="$WP_ENV_HOME" node_modules/.bin/wp-env run "$container" \
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

# Playwright UI mode is an interactive session that can stay open for hours.
# Everything that needs the shared environment is done, so release the queue
# before launching it; every other run keeps the lock until it exits.
UI_MODE=false
for arg in ${PLAYWRIGHT_ARGS[@]+"${PLAYWRIGHT_ARGS[@]}"}; do
  [[ "$arg" == --ui ]] && UI_MODE=true
done
if [[ "$UI_MODE" == true && -z "$LOCK_INHERITED" ]]; then
  jps_e2e_release_lock
fi

# ── Run Playwright ────────────────────────────────────────────────────────────
echo "▶ Running E2E tests..."
set +e
npx --no-install playwright test ${PLAYWRIGHT_ARGS[@]+"${PLAYWRIGHT_ARGS[@]}"}
PLAYWRIGHT_EXIT=$?
set -e

# ── Optional stop ─────────────────────────────────────────────────────────────
if [[ "$DO_STOP" == true ]]; then
  # The UI session released the queue; wait for our turn before touching the shared env.
  if [[ "$UI_MODE" == true && -z "$LOCK_INHERITED" ]]; then
    jps_e2e_acquire_lock "$PWD"
  fi
  echo "▶ Stopping wp-env..."
  npm run env:stop || true
fi

exit $PLAYWRIGHT_EXIT
