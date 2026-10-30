# Testing gotchas

## Environment

- Start and stop wp-env through `npm run env:start` and `npm run env:stop`. They shim Podman's missing `host-gateway`; a bare `npx wp-env start` fails there with "failed to create new hosts file".
- Every other `wp-env` command needs `WP_ENV_HOME=./.wp-env`, the home the npm scripts use.
- Ports 8888/8889 are often taken by another project's wp-env. For a second environment (another worktree), export `WP_ENV_PORT`, `WP_ENV_TESTS_PORT`, `WP_ENV_URL` and `WP_ENV_TESTS_URL` before the npm scripts; `.wp-env.json` keeps the defaults.
- Run `composer install` after every pull or merge. Composer resolves for PHP 8.0 while the host PHP is newer, so a stale `vendor/` can hold packages the wp-env PHP cannot parse.
- WordPress core source for reading: `.wp-env/<hash>/WordPress`; the test library: `.wp-env/<hash>/WordPress-PHPUnit`.

## Done criterion

A run is green when PHPUnit prints `OK (N tests, M assertions)` and exits 0. The integration bootstrap exits 1 when a test or handler reaches `exit()`/`die()` before PHPUnit finishes.

## Integration tests

- Drive behaviour the way WordPress does: `do_action`/`apply_filters`, REST requests through `rest_get_server()->dispatch()`, capability checks, and core functions. `scripts/check-test-seams.sh` (part of `composer lint`) rejects direct static calls into the plugin namespace, because they pass even when a hook registration is missing.
- Capture redirects by throwing a dedicated exception class from a `wp_redirect` filter; PHPUnit's own failures extend `RuntimeException`.
- Route `wp_die_handler` and `wp_die_ajax_handler` to a handler that throws `WPDieException`, so handlers never reach `exit`.

## E2E

`tests/seed.php` turns off the editor's first-run welcome guide and starter-pattern chooser for every user; brand-new users otherwise get modals that cover the editor at unpredictable times. Pass Playwright flags through the runner: `npm run test:e2e:run -- --retries 0`.
