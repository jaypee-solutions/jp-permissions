<?php

declare( strict_types = 1 );

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
    define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

require getenv( 'WP_TESTS_DIR' ) . '/includes/bootstrap.php';
require_once dirname( __DIR__, 2 ) . '/jp-permissions.php';
require_once __DIR__ . '/class-branch-fixtures.php';
