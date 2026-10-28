# ADR 0009: WordPress integration test stack

## Status

Accepted

## Context

Stub-based unit tests remain useful, but capability and write behavior depends on WordPress core. wp-env `tests-cli` supplies `WP_TESTS_DIR=/wordpress-phpunit`; the WordPress test bootstrap requires `yoast/phpunit-polyfills` 1.1.0 or later. PHPUnit 10.5 compatibility is UNVERIFIED.

## Decision

Keep the existing stub-based unit suite. Add WordPress integration tests in wp-env `tests-cli` using `yoast/phpunit-polyfills:^1.1.0`, with a repository-local `.github/workflows/integration.yml` and Playwright for three critical flows using plain `@playwright/test` (not `@wordpress/e2e-test-utils-playwright`). If the WordPress test library fails under PHPUnit 10.5, pin `phpunit/phpunit:^9.6` for the whole repository; unit tests are 9.6-compatible.

## Consequences

Integration tests exercise real WordPress capability and request paths. The separate workflow avoids modifying the template-owned `ci.yml`. PHPUnit compatibility remains an implementation-time check with a concrete fallback.
