<?php
/**
 * Plugin Name:       JP Permissions for WordPress
 * Plugin URI:        https://github.com/jaypee-solutions/jp-permissions
 * Description:       JP Permissions for WordPress is a WordPress plugin.
 * Version:           0.1.0
 * Author:            Jan-Peter Alten
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jp-permissions
 * Domain Path:       /languages
 * Requires at least: 6.4
 * Requires PHP:      8.0
 *
 * @package JayPee\Permissions
 */

declare( strict_types = 1 );

namespace JayPee\Permissions;

defined( 'ABSPATH' ) || exit;

const VERSION     = '0.1.0';
const PLUGIN_FILE = __FILE__;
const GITHUB_REPO = 'jaypee-solutions/jp-permissions';
const UPDATE_URL  = 'https://github.com/jaypee-solutions/jp-permissions/';

// Bundled by scripts/build-release.sh; present in dev after `composer install`.
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/includes/class-updates.php';
require_once __DIR__ . '/includes/class-group-repository.php';
require_once __DIR__ . '/includes/class-branch-policy.php';
require_once __DIR__ . '/includes/class-capabilities.php';
require_once __DIR__ . '/includes/class-lifecycle.php';
require_once __DIR__ . '/includes/class-write-guard.php';
require_once __DIR__ . '/includes/class-page-creation.php';

Lifecycle::init();
Updates::init();
BranchPolicy::init();
Capabilities::init();
WriteGuard::init();
PageCreation::init();

/**
 * Bootstrap.
 */
add_action(
    'plugins_loaded',
    static function (): void {
        load_plugin_textdomain(
            'jp-permissions',
            false,
            dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages'
        );

        // Register the plugin's own features here.
    }
);
