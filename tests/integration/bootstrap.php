<?php

declare( strict_types = 1 );

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
    define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

require getenv( 'WP_TESTS_DIR' ) . '/includes/bootstrap.php';
require_once dirname( __DIR__, 2 ) . '/jp-permissions.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$plugin_basename = plugin_basename( \JayPee\Permissions\PLUGIN_FILE );
if ( ! is_plugin_active( $plugin_basename ) ) {
    activate_plugin( $plugin_basename );
}

require_once __DIR__ . '/class-branch-fixtures.php';

/**
 * A handler reaching exit()/die() (AJAX, redirects) ends PHPUnit with status 0
 * and silently skips the remaining tests; fail the run unless PHPUnit finished.
 */
final class Integration_Run_Completion implements \PHPUnit\Runner\AfterLastTestHook {

    public static bool $completed = false;

    public function executeAfterLastTest(): void {
        self::$completed = true;
    }
}

register_shutdown_function(
    static function (): void {
        if ( ! Integration_Run_Completion::$completed ) {
            fwrite( STDERR, PHP_EOL . 'Integration run aborted before PHPUnit finished: a test or handler reached exit()/die().' . PHP_EOL );
            exit( 1 );
        }
    }
);
