<?php
/**
 * Integration test for plugin activation.
 *
 * @package JayPee\Permissions
 */

declare( strict_types = 1 );

final class Test_Plugin_Activation extends WP_UnitTestCase {

    public function test_plugin_activates_without_error(): void {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        deactivate_plugins( plugin_basename( \JayPee\Permissions\PLUGIN_FILE ) );

        $result = activate_plugin( plugin_basename( \JayPee\Permissions\PLUGIN_FILE ) );

        $this->assertNotWPError( $result );
        $this->assertTrue( is_plugin_active( plugin_basename( \JayPee\Permissions\PLUGIN_FILE ) ) );
    }
}
