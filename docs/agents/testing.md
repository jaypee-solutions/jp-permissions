# Testing gotchas

## Environment

- Use `npm run env:start` and `npm run env:stop`. They apply the Podman `host-gateway` shim; a bare `npx wp-env start` fails on Podman with “failed to create new hosts file.”
- wp-env 12 caches host-side sources under `${WP_ENV_HOME}/wp-env-<project>-<hash>/` (default `WP_ENV_HOME=./.wp-env`). Core is in `wordpress-7/` and `tests-wordpress-7/`; downloaded plugins such as WooCommerce are siblings such as `woocommerce.latest-stable/`.
- The checkout is bind-mounted at `/var/www/html/wp-content/plugins/<repository-directory-name>` in the `wordpress`/`cli` and `tests-wordpress`/`tests-cli` containers; the directory name is the checkout basename, not `plugin_slug`. Edit the checkout source, not its container mount.
- For example, read the tests-container WooCommerce copy with `WP_ENV_HOME=./.wp-env npx --no-install wp-env run tests-cli -- cat /var/www/html/wp-content/plugins/woocommerce.latest-stable/woocommerce.php`. Its host-side copy is `${WP_ENV_HOME}/wp-env-<project>-<hash>/woocommerce.latest-stable/`.
- `wp-env run cli` and `wp-env run tests-cli` run at `/var/www/html`; `test:integration` selects the plugin directory with `--env-cwd=wp-content/plugins/<repository-directory-name>`. The PHPUnit library is mounted at `/wordpress-phpunit` from `WordPress-PHPUnit/tests/phpunit` (development) or `tests-WordPress-PHPUnit/tests/phpunit` (tests) under the wp-env cache.
- Raw `npx wp-env` commands must set `WP_ENV_HOME=./.wp-env` (or the chosen custom home) or they use wp-env's unrelated default home. The lifecycle, E2E, and integration scripts export the correct home and ports.
- The helper uses wp-env-supported `WP_ENV_PORT` / `WP_ENV_TESTS_PORT`: the main checkout defaults to 8888/8889; linked worktrees use stable ports 8900–8999. `WP_ENV_URL`, `WP_ENV_TESTS_URL`, and `WP_ENV_HOME` can be overridden.
- wp-env lifecycle, integration and E2E commands queue per plugin (package name) using FIFO PID tickets in `${TMPDIR:-/tmp}/<slug>-e2e.queue`; abandoned tickets are removed. A waiting command prints one `e2e: waiting for PID …` line. `npm run test:e2e:ui` leaves the queue once the site is ready; every other run holds it until it exits. Set `E2E_LOCK_DIR` to use another queue or `E2E_LOCK_DISABLE=1` to disable locking.
- `npm run test:e2e:run` checks that the site on its port serves this checkout (it compares `readme.txt`). If another plugin's wp-env answers there, it exits with an error instead of testing that site; set `WP_ENV_PORT`/`WP_ENV_TESTS_PORT` to a free pair.
- Run `composer install` after every pull or merge. Composer resolves for PHP 8.0 while the host PHP is newer, so stale `vendor/` packages may use syntax unsupported by wp-env PHP.

## Done criterion

A PHPUnit run is green when it prints `OK (N tests, M assertions)` and exits 0. The integration bootstrap exits 1 if a test or handler reaches `exit()`/`die()` before PHPUnit finishes. For user-visible changes, also run the full E2E suite and lint/hook checks below.
Run `composer lint` (PHPCS and repository-specific checks) and `composer hooks:check` (after `npm install`) to verify the lint rules and Git hook wiring.

## Lint

- `check-hook-names: … unknown hook '<name>'` means the name lacks the plugin prefix and neither WordPress core, WooCommerce (when required) nor `scripts/hook-allowlist.txt` defines it. Look it up in the wp-env source cache before allowlisting it: a typo or an invented hook is a bug. Hooks of other plugins go into `scripts/hook-allowlist.txt`.
- The hook check skips locally until `npm run env:start` has filled the wp-env cache; CI always runs it.
- `check-i18n` runs `msgfmt -c` on `languages/*.po`; the same msgid under different `msgctxt` values is valid.

## Integration tests

- Drive behaviour the way WordPress does: `do_action`/`apply_filters`, REST requests through `rest_get_server()->dispatch()`, capability checks, and core functions. `scripts/check-test-seams.sh` (part of `composer lint`) rejects direct static calls into the plugin namespace, because they pass even when a hook registration is missing.
- Capture redirects by throwing a dedicated exception class from a `wp_redirect` filter; PHPUnit's own failures extend `RuntimeException`.
- Route `wp_die_handler` and `wp_die_ajax_handler` to a handler that throws `WPDieException`, so handlers never reach `exit`.

## E2E

`tests/seed.php` disables the editor's first-run welcome guide and starter-pattern chooser for every user; fresh users otherwise get unpredictable modals. Keep seeded fixtures idempotent: use stable SKUs/slugs and create only when absent.
Run the full E2E suite with `npm run test:e2e:run`; it starts wp-env if needed, flushes OpCache, seeds, and runs Playwright. Pass Playwright options through the runner, e.g. `npm run test:e2e:run -- --retries 0`.
Import `test`/`expect` from `tests/e2e/support/fixtures.ts`, `wpCli` from `tests/e2e/support/wp-cli.ts`, and `withOptions` from `tests/e2e/support/options.ts`. Use the options fixture or `withOptions()` when changing WordPress options; it restores present and absent values in `finally`, including after a thrown callback. Mutations made directly through WP-CLI must explicitly restore the previous state in `finally`.
`wpCli()` returns WP-CLI's stdout verbatim minus the final newline; wp-env status lines and PHP notices go to stderr. Each call takes about 2 s, so put a test's bulk WP-CLI work into one `wp eval`.
