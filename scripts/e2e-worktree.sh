#!/usr/bin/env bash
# Isolate linked worktrees and serialize access to each wp-env environment.

jps_e2e_is_linked_worktree() {
	local root="${1:-$PWD}" git_dir common_dir
	git_dir="$(git -C "$root" rev-parse --git-dir 2>/dev/null)" || return 1
	common_dir="$(git -C "$root" rev-parse --git-common-dir 2>/dev/null)" || return 1
	[[ "$git_dir" != "$common_dir" ]]
}

jps_e2e_configure_worktree() {
	local root="${1:-$PWD}" canonical hash base
	canonical="$(cd "$root" && pwd -P)"
	if jps_e2e_is_linked_worktree "$canonical"; then
		hash="$(printf '%s' "$canonical" | cksum | cut -d ' ' -f 1)"
		base=$(( 8900 + (hash % 50) * 2 ))
	else
		base=8888
	fi
	: "${WP_ENV_PORT:=$base}"
	: "${WP_ENV_TESTS_PORT:=$((WP_ENV_PORT + 1))}"
	export WP_ENV_PORT WP_ENV_TESTS_PORT
	export WP_ENV_URL="${WP_ENV_URL:-http://localhost:${WP_ENV_PORT}}"
	export WP_ENV_TESTS_URL="${WP_ENV_TESTS_URL:-http://localhost:${WP_ENV_TESTS_PORT}}"
	export WP_ENV_HOME="${WP_ENV_HOME:-$canonical/.wp-env}"
}

# Idempotent: drops this process's queue ticket (if any) and forgets it, so a
# later jps_e2e_acquire_lock queues again. Safe to call early and again on EXIT.
jps_e2e_release_lock() {
	if [[ -n "${E2E_LOCK_TICKET:-}" ]]; then
		rm -f "$E2E_LOCK_TICKET"
		unset E2E_LOCK_TICKET E2E_LOCK_HELD
	fi
	if declare -F cleanup_docker_shim >/dev/null; then
		cleanup_docker_shim
	fi
}

jps_e2e_acquire_lock() {
	[[ "${E2E_LOCK_DISABLE:-0}" == 1 || "${E2E_LOCK_HELD:-}" == 1 ]] && return 0
	local root="${1:-$PWD}" canonical lockdir ticket pid first holder owner_pid slug waited=0
	canonical="$(cd "$root" && pwd -P)"
	slug="$(node -e 'const p = require(process.argv[1]); process.stdout.write(p.name || String())' "$canonical/package.json" 2>/dev/null || true)"
	[[ -n "$slug" ]] || slug="$(basename "$canonical")"
	lockdir="${E2E_LOCK_DIR:-${TMPDIR:-/tmp}/${slug}-e2e.queue}"
	mkdir -p "$lockdir"
	owner_pid="${BASHPID:-$$}"
	while ! mkdir "$lockdir/allocator" 2>/dev/null; do
		if [[ -r "$lockdir/allocator/pid" ]]; then
			read -r pid < "$lockdir/allocator/pid" || true
			if [[ -n "${pid:-}" ]] && ! kill -0 "$pid" 2>/dev/null; then
				rm -f "$lockdir/allocator/pid"
				rmdir "$lockdir/allocator" 2>/dev/null || true
			fi
		fi
		sleep 0.02
	done
	printf '%s\n' "$owner_pid" > "$lockdir/allocator/pid"
	local next=0
	if [[ -f "$lockdir/next" ]]; then
		{ read -r next < "$lockdir/next"; } 2>/dev/null || next=0
	fi
	next=$((next + 1))
	ticket="$lockdir/ticket-$(printf '%010d' "$next")"
	printf '%s\n' "$next" > "$lockdir/next.pending-$owner_pid"
	mv -f "$lockdir/next.pending-$owner_pid" "$lockdir/next"
	printf '%s\n' "$owner_pid" > "$lockdir/pending-$next"
	mv -f "$lockdir/pending-$next" "$ticket"
	rm -f "$lockdir/allocator/pid"
	rmdir "$lockdir/allocator"
	while true; do
		holder=
		for first in "$lockdir"/ticket-*; do
			[[ -f "$first" ]] || continue
			if ! { IFS= read -r pid < "$first"; } 2>/dev/null; then
				continue
			fi
			if ! kill -0 "$pid" 2>/dev/null; then
				rm -f "$first"
				continue
			fi
			holder="$pid"
			break
		done
		if [[ "$first" == "$ticket" ]]; then
			break
		fi
		if [[ "$waited" == 0 && -n "$holder" ]]; then
			printf 'e2e: waiting for PID %s to finish (queue: %s)\n' "$holder" "$lockdir" >&2
			waited=1
		fi
		sleep 0.05
	done
	E2E_LOCK_DIR="$lockdir"
	E2E_LOCK_TICKET="$ticket"
	E2E_LOCK_HELD=1
	export E2E_LOCK_DIR E2E_LOCK_TICKET E2E_LOCK_HELD
	trap 'jps_e2e_release_lock' EXIT
	trap 'exit 130' INT
	trap 'exit 143' TERM
}
