<?php
/** Restricts page creation to authorized parents for branch editors. */

declare( strict_types = 1 );

namespace JayPee\Permissions;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class PageCreation {
    public static function init(): void {
        add_filter( 'map_meta_cap', [ self::class, 'map_add_subpage_capability' ], 10, 4 );
        add_filter( 'page_row_actions', [ self::class, 'row_action' ], 10, 2 );
        add_action( 'admin_post_jp_permissions_add_subpage', [ self::class, 'add_subpage' ] );
        add_action( 'load-post-new.php', [ self::class, 'redirect_generic_new_page' ] );
        add_action( 'admin_notices', [ self::class, 'notice' ] );
        // After wp-admin/menu.php's access check: removing on admin_menu collapses the Pages submenu and denies the screen.
        add_action( 'admin_init', [ self::class, 'remove_add_new_menu' ] );
        add_action( 'admin_bar_menu', [ self::class, 'remove_add_new_admin_bar' ], 999 );
    }

    public static function map_add_subpage_capability( array $caps, string $cap, int $user_id, array $args ): array {
        if ( 'jp_permissions_add_subpage' !== $cap ) {
            return $caps;
        }
        $parent_id = isset( $args[0] ) ? absint( $args[0] ) : 0;
        $parent = get_post( $parent_id );
        if ( ! $parent instanceof WP_Post || ! BranchPolicy::is_valid_branch_page( $parent ) || ( BranchPolicy::is_restricted( $user_id ) && ! BranchPolicy::is_authorized_page( $user_id, $parent ) ) ) {
            return [ 'do_not_allow' ];
        }
        return user_can( $user_id, 'edit_pages' ) ? [ 'edit_pages' ] : [ 'do_not_allow' ];
    }

    public static function row_action( array $actions, WP_Post $post ): array {
        // phpcs:ignore WordPress.WP.Capabilities.Unknown -- This is a mapped meta capability.
        if ( current_user_can( 'jp_permissions_add_subpage', $post->ID ) ) {
            $url = wp_nonce_url( admin_url( 'admin-post.php?action=jp_permissions_add_subpage&parent=' . $post->ID ), 'jp_permissions_add_subpage_' . $post->ID );
            $actions['add_subpage'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Add subpage', 'jp-permissions' ) . '</a>';
        }
        return $actions;
    }

    public static function add_subpage(): void {
        $parent_id = isset( $_GET['parent'] ) ? absint( wp_unslash( $_GET['parent'] ) ) : 0;
        check_admin_referer( 'jp_permissions_add_subpage_' . $parent_id );
        // phpcs:ignore WordPress.WP.Capabilities.Unknown -- This is a mapped meta capability.
        if ( ! current_user_can( 'jp_permissions_add_subpage', $parent_id ) ) {
            wp_die( esc_html__( 'You are not allowed to add a subpage here.', 'jp-permissions' ), '', [ 'response' => 403 ] );
        }
        if ( ! wp_next_scheduled( 'wp_scheduled_auto_draft_delete' ) ) {
            wp_schedule_event( time(), 'daily', 'wp_scheduled_auto_draft_delete' );
        }
        $id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'auto-draft', 'post_parent' => $parent_id, 'menu_order' => 0 ], true );
        if ( is_wp_error( $id ) ) {
            wp_die( esc_html( $id->get_error_message() ), '', [ 'response' => 500 ] );
        }
        wp_safe_redirect( get_edit_post_link( $id, 'url' ) );
        exit;
    }

    public static function redirect_generic_new_page(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The redirect is a read-only navigation.
        if ( ! BranchPolicy::is_restricted_context() || 'post-new.php' !== $GLOBALS['pagenow'] || 'page' !== sanitize_key( wp_unslash( $_GET['post_type'] ?? 'post' ) ) ) {
            return;
        }
        wp_safe_redirect( add_query_arg( 'jp_permissions_notice', 'use_add_subpage', admin_url( 'edit.php?post_type=page' ) ) );
        exit;
    }

    public static function notice(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The notice query argument is informational.
        if ( 'use_add_subpage' === sanitize_key( wp_unslash( $_GET['jp_permissions_notice'] ?? '' ) ) ) {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'To create a page, use Add subpage on an authorized page in the Pages list.', 'jp-permissions' ) . '</p></div>';
        }
    }

    public static function remove_add_new_menu(): void {
        if ( BranchPolicy::is_restricted_context() ) {
            remove_submenu_page( 'edit.php?post_type=page', 'post-new.php?post_type=page' );
        }
    }

    public static function remove_add_new_admin_bar( \WP_Admin_Bar $bar ): void {
        if ( BranchPolicy::is_restricted_context() ) {
            $bar->remove_node( 'new-page' );
        }
    }
}
