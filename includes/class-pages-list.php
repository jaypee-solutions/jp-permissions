<?php
/** Restricts the admin Pages list to the current user's authorized pages. */

declare( strict_types = 1 );

namespace JayPee\Permissions;

use WP_Query;

final class PagesList {
    public static function init(): void {
        add_action( 'pre_get_posts', array( self::class, 'restrict_query' ) );
    }

    public static function restrict_query( WP_Query $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() || 'page' !== $query->get( 'post_type' ) ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! BranchPolicy::is_restricted( $user_id ) ) {
            return;
        }

        $page_ids = BranchPolicy::authorized_page_ids( $user_id );
        $query->set( 'post__in', empty( $page_ids ) ? array( 0 ) : $page_ids );
    }
}
