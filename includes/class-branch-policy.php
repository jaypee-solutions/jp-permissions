<?php
/** Resolves membership and branch ancestry. */

declare( strict_types = 1 );

namespace JayPee\Permissions;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class BranchPolicy {
    private static array $roots = [];
    private static array $authorized_pages = [];

    public static function init(): void {
        add_action( 'update_option_' . GroupRepository::OPTION, [ self::class, 'reset' ] );
        add_action( 'add_option_' . GroupRepository::OPTION, [ self::class, 'reset' ] );
    }

    public static function reset(): void {
        self::$roots = [];
        self::$authorized_pages = [];
    }

    public static function is_restricted( int $user_id ): bool {
        if ( 0 === $user_id || empty( GroupRepository::groups_for_user( $user_id ) ) ) {
            return false;
        }
        $user = get_userdata( $user_id );
        return $user instanceof \WP_User && empty( $user->allcaps['edit_others_pages'] );
    }
    public static function is_valid_branch_page( WP_Post $page ): bool {
        return 'page' === $page->post_type && ! in_array( $page->post_status, [ 'auto-draft', 'trash' ], true );
    }


    public static function roots_for( int $user_id ): array {
        if ( ! isset( self::$roots[ $user_id ] ) ) {
            $roots = [];
            foreach ( GroupRepository::groups_for_user( $user_id ) as $group ) {
                $roots = array_merge( $roots, $group['roots'] );
            }
            self::$roots[ $user_id ] = array_values( array_unique( $roots ) );
        }
        return self::$roots[ $user_id ];
    }

    public static function authorized_page_ids( int $user_id ): array {
        if ( ! isset( self::$authorized_pages[ $user_id ] ) ) {
            $roots = self::roots_for( $user_id );
            $authorized = [];
            foreach ( get_posts( [ 'post_status' => 'any', 'post_type' => 'page', 'numberposts' => -1 ] ) as $page ) {
                if ( self::has_authorized_ancestor( $page, $roots ) ) {
                    $authorized[ $page->ID ] = $page->ID;
                }
            }
            self::$authorized_pages[ $user_id ] = array_values( $authorized );
        }
        return self::$authorized_pages[ $user_id ];
    }

    public static function is_authorized_page( int $user_id, WP_Post $page ): bool {
        return 'page' === $page->post_type && self::has_authorized_ancestor( $page, self::roots_for( $user_id ) );
    }

    private static function has_authorized_ancestor( WP_Post $page, array $roots ): bool {
        $cursor = $page;
        $visited = [];
        $found_root = false;
        while ( $cursor instanceof WP_Post ) {
            if ( isset( $visited[ $cursor->ID ] ) ) {
                return false;
            }
            $visited[ $cursor->ID ] = true;
            $found_root = $found_root || in_array( $cursor->ID, $roots, true );
            $parent_id = (int) $cursor->post_parent;
            $cursor = $parent_id > 0 ? get_post( $parent_id ) : null;
        }
        return $found_root;
    }

    public static function is_restricted_context(): bool {
        return ! wp_doing_cron() && self::is_restricted( get_current_user_id() );
    }
}
