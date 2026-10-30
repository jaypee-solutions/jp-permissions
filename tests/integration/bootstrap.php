<?php
/**
 * WordPress integration-test bootstrap.
 *
 * @package JayPee\Permissions
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
    define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once getenv( 'WP_TESTS_DIR' ) . '/includes/functions.php';

tests_add_filter(
    'muplugins_loaded',
    static function (): void {
        require_once dirname( __DIR__, 2 ) . '/jp-permissions.php';
    }
);

require getenv( 'WP_TESTS_DIR' ) . '/includes/bootstrap.php';

// Activation registers the Branch Editor role and the uninstall hook, as on a real site.
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$plugin_basename = plugin_basename( \JayPee\Permissions\PLUGIN_FILE );
if ( ! is_plugin_active( $plugin_basename ) ) {
    activate_plugin( $plugin_basename );
}

require_once __DIR__ . '/class-branch-fixtures.php';

/**
 * Detect handlers that terminate PHP before PHPUnit can report a result.
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
