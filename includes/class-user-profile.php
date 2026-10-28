<?php
/** Displays a user's branch memberships on their profile. */

declare( strict_types = 1 );

namespace JayPee\Permissions;

use WP_User;

final class UserProfile {
    public static function init(): void {
        add_action( 'show_user_profile', array( self::class, 'render' ) );
        add_action( 'edit_user_profile', array( self::class, 'render' ) );
    }

    public static function render( WP_User $user ): void {
        if ( ! current_user_can( 'manage_options' ) && get_current_user_id() !== $user->ID ) {
            return;
        }
        $groups = GroupRepository::groups_for_user( $user->ID );
        if ( empty( $groups ) ) {
            return;
        }

        echo '<h2>' . esc_html__( 'Page Branches', 'jp-permissions' ) . '</h2><table class="form-table"><tbody><tr><th>' . esc_html__( 'Groups and branch roots', 'jp-permissions' ) . '</th><td><ul>';
        foreach ( $groups as $group ) {
            echo '<li><strong>' . esc_html( $group['name'] ) . '</strong> (';
            $roots = array();
            foreach ( $group['roots'] as $root_id ) {
                $root = get_post( $root_id );
                if ( $root instanceof \WP_Post ) {
                    $roots[] = $root->post_title;
                }
            }
            echo $roots ? esc_html( implode( ', ', $roots ) ) : esc_html__( 'No branch roots', 'jp-permissions' );
            echo ')';
            if ( ! empty( $user->allcaps['edit_others_pages'] ) ) {
                echo ' <span>(' . esc_html__( 'unrestricted', 'jp-permissions' ) . ')</span>';
            }
            echo '</li>';
        }
        echo '</ul></td></tr></tbody></table>';
    }
}
