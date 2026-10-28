<?php
/** Enforces administrator-only page structure changes. */

declare( strict_types = 1 );

namespace JayPee\Permissions;

use WP_Error;
use WP_Post;

final class WriteGuard {
    public static function init(): void {
        add_filter( 'rest_pre_insert_page', array( self::class, 'rest_pre_insert' ), 10, 2 );
        add_filter( 'wp_insert_post_data', array( self::class, 'insert_post_data' ), 99, 4 );
    }

    public static function rest_pre_insert( $prepared, \WP_REST_Request $request ) {
        if ( ! BranchPolicy::is_restricted_context() || ! is_object( $prepared ) ) {
            return $prepared;
        }
        if ( ! empty( $prepared->ID ) ) {
            $stored = get_post( (int) $prepared->ID );
            if ( $stored instanceof WP_Post ) {
                if ( $request->has_param( 'parent' ) && (int) $prepared->post_parent !== (int) $stored->post_parent ) {
                    return self::error( 'parent' );
                }
                if ( $request->has_param( 'menu_order' ) && (int) $prepared->menu_order !== (int) $stored->menu_order ) {
                    return self::error( 'order' );
                }
            }
        }
        return $prepared;
    }

    public static function insert_post_data( array $data, array $postarr, array $unsanitized_postarr, bool $update ): array {
        if ( ! BranchPolicy::is_restricted_context() || 'page' !== ( $data['post_type'] ?? '' ) || 'trash' === ( $data['post_status'] ?? '' ) ) {
            return $data;
        }
        if ( ! $update ) {
            $parent = get_post( (int) ( $data['post_parent'] ?? 0 ) );
            if ( ! $parent instanceof WP_Post || 'page' !== $parent->post_type || in_array( $parent->post_status, array( 'auto-draft', 'trash' ), true ) || ! BranchPolicy::is_authorized_page( get_current_user_id(), $parent ) ) {
                self::deny( 'parent' );
            }
            if ( (int) ( $data['menu_order'] ?? 0 ) !== 0 ) {
                self::deny( 'order' );
            }
            return $data;
        }
        if ( empty( $postarr['ID'] ) ) {
            return $data;
        }
        $stored = get_post( (int) $postarr['ID'] );
        if ( ! $stored instanceof WP_Post ) {
            return $data;
        }
        if ( array_key_exists( 'post_parent', $unsanitized_postarr ) && (int) $data['post_parent'] !== (int) $stored->post_parent ) {
            self::deny( 'parent' );
        }
        if ( array_key_exists( 'menu_order', $unsanitized_postarr ) && (int) $data['menu_order'] !== (int) $stored->menu_order ) {
            self::deny( 'order' );
        }
        return $data;
    }

    private static function error( string $field ): WP_Error {
        if ( 'parent' === $field ) {
            return new WP_Error( 'jp_permissions_forbidden_parent', __( 'You are not allowed to place a page here. Pages can only be created inside your page branches, and only administrators can move pages.', 'jp-permissions' ), array( 'status' => 403 ) );
        }
        return new WP_Error( 'jp_permissions_forbidden_order', __( 'Only administrators can change the order of pages.', 'jp-permissions' ), array( 'status' => 403 ) );
    }

    private static function deny( string $field ): void {
        $error = self::error( $field );
        wp_die( esc_html( $error->get_error_message() ), esc_html__( 'Forbidden', 'jp-permissions' ), array( 'response' => 403, 'back_link' => true ) );
    }
}
