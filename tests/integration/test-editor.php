<?php

declare( strict_types = 1 );

final class Editor_Integration_Test extends WP_UnitTestCase {
    use Branch_Fixtures;

    public function test_editor_script_is_enqueued_only_for_restricted_users_editing_pages(): void {
        $cases = [
            [ 'fb', 'page', true ],
            [ 'multi', 'page', true ],
            [ 'youth', 'page', true ],
            [ 'admin', 'page', false ],
            [ 'orphan', 'page', false ],
            [ 'staff', 'page', false ],
            [ 'fb_staff', 'page', false ],
            [ 'sub', 'page', false ],
            [ 'fb', 'post', false ],
        ];
        foreach ( $cases as $case ) {
            list( $user, $post_type, $expected ) = $case;
            wp_set_current_user( 0 );
            $post = self::factory()->post->create_and_get( [ 'post_type' => $post_type ] );
            wp_set_current_user( $this->users[ $user ] );
            $GLOBALS['post'] = $post;
            do_action( 'enqueue_block_editor_assets' );
            $this->assertSame( $expected, in_array( 'jp-permissions-editor', wp_scripts()->queue, true ), $user . ' editing ' . $post_type );
            wp_dequeue_script( 'jp-permissions-editor' );
            wp_deregister_script( 'jp-permissions-editor' );
        }
        unset( $GLOBALS['post'] );
    }
}
