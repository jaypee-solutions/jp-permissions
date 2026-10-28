<?php
/** Adds branch editing primitives and maps page meta capabilities. */

declare( strict_types = 1 );

namespace JayPee\Permissions;

use WP_Post;

final class Capabilities {
    public static function init(): void {
        add_filter( 'user_has_cap', array( self::class, 'grant' ), 10, 4 );
        add_filter( 'map_meta_cap', array( self::class, 'map' ), 10, 4 );
    }


    public static function grant( array $allcaps, array $caps, array $args, \WP_User $user ): array {
        if ( ! empty( $allcaps['edit_others_pages'] ) || empty( GroupRepository::groups_for_user( (int) $user->ID ) ) ) {
            return $allcaps;
        }
        $allcaps['edit_pages'] = true;
        $allcaps['edit_others_pages'] = true;
        $allcaps['publish_pages'] = true;
        return $allcaps;
    }

    public static function map( array $caps, string $cap, int $user_id, array $args ): array {
        if ( ! BranchPolicy::is_restricted( $user_id ) ) {
            return $caps;
        }
        if ( ! in_array( $cap, array( 'edit_post', 'edit_page', 'publish_post', 'read_post', 'read_page', 'delete_post', 'delete_page' ), true ) || empty( $args[0] ) ) {
            return $caps;
        }
        $post = get_post( (int) $args[0] );
        if ( $post instanceof WP_Post && 'revision' === $post->post_type ) {
            $post = get_post( (int) $post->post_parent );
        }
        if ( ! $post instanceof WP_Post || ( 'page' !== $post->post_type && ! ( 'edit_post' === $cap && 'attachment' === $post->post_type ) ) ) {
            return $caps;
        }
        if ( str_starts_with( $cap, 'delete_' ) ) {
            return array( 'do_not_allow' );
        }
        if ( 'attachment' === $post->post_type ) {
            return $caps;
        }
        $allowed = BranchPolicy::is_authorized_page( $user_id, $post );
        if ( in_array( $cap, array( 'edit_post', 'edit_page' ), true ) ) {
            if ( ! $allowed ) {
                return array( 'do_not_allow' );
            }
            return in_array( 'manage_privacy_options', $caps, true ) ? array( 'edit_pages', 'manage_privacy_options' ) : array( 'edit_pages' );
        }
        if ( 'publish_post' === $cap ) {
            return $allowed ? array( 'publish_pages' ) : array( 'do_not_allow' );
        }
        return $allowed ? array( 'read' ) : $caps;
    }
}
