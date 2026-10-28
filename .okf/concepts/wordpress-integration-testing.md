---
type: concept
title: WordPress integration testing
description: Verified wp-env and WordPress PHPUnit integration test environment facts.
tags: [wordpress, testing, phpunit]
timestamp: 2026-10-27T00:00:00Z
---

# Test environment

The wp-env `tests-cli` environment exposes `WP_TESTS_DIR=/wordpress-phpunit`. The WordPress 7.1 test bootstrap requires `yoast/phpunit-polyfills` version 1.1.0 or later and sets a PHPUnit minimum of 5.7.21; PHPUnit 10.5 compatibility is **UNVERIFIED**. `WP_Ajax_UnitTestCase`, factories, and `WPDieException` are available.

# Design implications

- Repository ADR `docs/adr/0009-integration-test-stack.md` retains the existing unit suite, adds wp-env integration tests, and records the PHPUnit 9.6 contingency.
- Repository ADR `docs/adr/0008-layered-server-side-enforcement.md` motivates exercising server-side operation paths in integration tests.

# Citations

[1] `WordPress-PHPUnit/tests/phpunit/includes/bootstrap.php:48-56, 146-163` (WordPress 7.1.3).
