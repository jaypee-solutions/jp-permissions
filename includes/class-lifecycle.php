<?php
/** Registers plugin lifecycle hooks and preserves configuration consistency. */

declare( strict_types = 1 );

namespace JayPee\Permissions;

defined( 'ABSPATH' ) || exit;

final class Lifecycle {
    public static function init(): void {
        register_activation_hook( PLUGIN_FILE, [ self::class, 'activate' ] );
        add_action( 'plugins_loaded', [ self::class, 'maybe_upgrade' ] );
        add_action( 'deleted_post', [ self::class, 'deleted_post' ], 10, 2 );
        add_action( 'deleted_user', [ self::class, 'deleted_user' ] );
    }

    public static function activate(): void {
        if ( is_multisite() ) {
            wp_die( esc_html__( 'JP Permissions does not support multisite.', 'jp-permissions' ) );
        }
        register_uninstall_hook( PLUGIN_FILE, [ self::class, 'uninstall' ] );
        self::register_role();
    }

    public static function maybe_upgrade(): void {
        self::register_role();
        $config = get_option( GroupRepository::OPTION, [] );
        if ( ! is_array( $config ) || ! isset( $config['schema'] ) || GroupRepository::SCHEMA !== $config['schema'] ) {
            return;
        }
    }
    public static function register_role(): void {
        if ( ! get_role( 'jp_branch_editor' ) ) {
            add_role( 'jp_branch_editor', __( 'Branch Editor', 'jp-permissions' ), [ 'read' => true, 'upload_files' => true ] );
        }
    }

    public static function deleted_post( int $post_id, ?\WP_Post $post = null ): void {
        if ( 'page' === ( $post->post_type ?? get_post_type( $post_id ) ) ) {
            GroupRepository::remove_root( $post_id );
        }
    }

    public static function deleted_user( int $user_id ): void {
        GroupRepository::remove_member( $user_id );
    }

    public static function uninstall(): void {
        delete_option( GroupRepository::OPTION );
        $users = get_users( [ 'role' => 'jp_branch_editor', 'fields' => 'ID' ] );
        foreach ( $users as $user_id ) {
            $user = new \WP_User( (int) $user_id );
            $user->set_role( 'subscriber' );
        }
        remove_role( 'jp_branch_editor' );
    }
}
