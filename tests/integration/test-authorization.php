<?php

declare( strict_types = 1 );

final class Authorization_Integration_Test extends WP_UnitTestCase {
    use Branch_Fixtures;

    public function test_branch_editor_role_and_capability_matrix(): void {
        $this->assertNotFalse( get_role( 'jp_branch_editor' ) );
        $this->assertSame( [ 'read', 'upload_files' ], array_keys( get_role( 'jp_branch_editor' )->capabilities ) );
        foreach ( [ 'football_training', 'youth', 'tennis_training', 'courses' ] as $key ) {
            wp_set_current_user( $this->users['fb'] );
            $expected = in_array( $key, [ 'football_training', 'youth' ], true );
            $this->assertSame( $expected, current_user_can( 'edit_post', $this->pages[ $key ] ), $key );
            $this->assertSame( $expected, current_user_can( 'publish_post', $this->pages[ $key ] ), $key . ' publish' );
            $this->assertFalse( current_user_can( 'delete_post', $this->pages[ $key ] ), $key . ' delete' );
        }
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['football_training'] ) );
    }

    public function test_nested_additive_branches_and_unrestricted_members(): void {
        wp_set_current_user( $this->users['fb'] );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['youth'] ) );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['football_training'] ) );
        wp_set_current_user( $this->users['youth'] );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['youth'] ) );
        $this->assertFalse( current_user_can( 'edit_post', $this->pages['football_training'] ) );
        wp_set_current_user( $this->users['multi'] );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['tennis'] ) );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['courses'] ) );
        $this->assertFalse( current_user_can( 'edit_post', $this->pages['football'] ) );
        $this->assertFalse( current_user_can( 'edit_post', $this->pages['home'] ) );
        wp_set_current_user( $this->users['fb_staff'] );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['tennis'] ) );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['football'] ) );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['home'] ) );
        wp_set_current_user( $this->users['orphan'] );
        $this->assertFalse( current_user_can( 'edit_post', $this->pages['football'] ) );
        wp_set_current_user( $this->users['admin'] );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['home'] ) );
        foreach ( $this->pages as $page_id ) {
            $this->assertTrue( current_user_can( 'edit_post', $page_id ) );
        }
    }

    public function test_corrupt_configuration_fails_closed_for_branch_editors(): void {
        delete_option( 'jp_permissions_groups' );
        wp_set_current_user( $this->users['fb'] );
        $this->assertFalse( current_user_can( 'edit_post', $this->pages['football'] ) );
        update_option( 'jp_permissions_groups', 'corrupt' );
        $this->assertFalse( current_user_can( 'edit_post', $this->pages['football'] ) );
        wp_set_current_user( $this->users['fb_staff'] );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['home'] ) );
    }

    public function test_edit_publish_read_and_delete_matrix_for_all_fixtures(): void {
        $draft = self::factory()->post->create( [
            'post_type' => 'page',
            'post_status' => 'draft',
            'post_title' => 'Foreign draft',
            'post_author' => $this->users['admin'],
        ] );
        $authorized_draft = self::factory()->post->create( [
            'post_type' => 'page',
            'post_status' => 'draft',
            'post_title' => 'Authorized draft',
            'post_parent' => $this->pages['youth'],
            'post_author' => $this->users['admin'],
        ] );
        $authorized_matrix = [
            'admin' => [ true, true, true, true ],
            'staff' => [ true, true, true, true ],
            'fb' => [ true, true, true, false ],
            'multi' => [ false, false, false, false ],
            'youth' => [ true, true, true, false ],
            'fb_staff' => [ true, true, true, true ],
            'orphan' => [ false, false, false, false ],
            'sub' => [ false, false, false, false ],
        ];
        foreach ( $authorized_matrix as $fixture => $expected ) {
            wp_set_current_user( $this->users[ $fixture ] );
            $this->assertSame( $expected[0], current_user_can( 'edit_post', $authorized_draft ), $fixture . ' authorized edit' );
            $this->assertSame( $expected[1], current_user_can( 'publish_post', $authorized_draft ), $fixture . ' authorized publish' );
            $this->assertSame( $expected[2], current_user_can( 'read_post', $authorized_draft ), $fixture . ' authorized read' );
            $this->assertSame( $expected[3], current_user_can( 'delete_post', $authorized_draft ), $fixture . ' authorized delete' );
        }
        $matrix = [
            'admin' => [ true, true, true, true ],
            'staff' => [ true, true, true, true ],
            'fb' => [ false, false, false, false ],
            'multi' => [ false, false, false, false ],
            'youth' => [ false, false, false, false ],
            'fb_staff' => [ true, true, true, true ],
            'orphan' => [ false, false, false, false ],
            'sub' => [ false, false, false, false ],
        ];
        foreach ( $matrix as $fixture => $expected ) {
            wp_set_current_user( $this->users[ $fixture ] );
            $this->assertSame( $expected[0], current_user_can( 'edit_post', $draft ), $fixture . ' edit' );
            $this->assertSame( $expected[1], current_user_can( 'publish_post', $draft ), $fixture . ' publish' );
            $this->assertSame( $expected[2], current_user_can( 'read_post', $draft ), $fixture . ' read' );
            $this->assertSame( $expected[3], current_user_can( 'delete_post', $draft ), $fixture . ' delete' );
        }
    }

    public function test_authorized_pages_allow_publish_schedule_private_and_revisions(): void {
        $page_id = $this->pages['youth'];
        wp_set_current_user( $this->users['fb'] );
        $this->assertTrue( current_user_can( 'publish_post', $page_id ) );
        $revision = wp_save_post_revision( $page_id );
        $this->assertNotFalse( $revision );
        $this->assertTrue( current_user_can( 'edit_post', $revision ) );
        $this->assertTrue( current_user_can( 'read_post', $revision ) );
        $this->assertFalse( current_user_can( 'publish_post', $this->pages['tennis'] ) );
    }

    public function test_restricted_user_can_publish_schedule_and_set_private_only_inside_branch(): void {
        $page_id = $this->pages['football_training'];
        wp_set_current_user( $this->users['fb'] );
        foreach ( [ 'publish', 'future', 'private' ] as $status ) {
            $request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $page_id );
            $request->set_param( 'status', $status );
            if ( 'future' === $status ) {
                $request->set_param( 'date', '2030-01-01T12:00:00' );
            }
            $response = rest_get_server()->dispatch( $request );
            $this->assertSame( 200, $response->get_status(), $status );
            $this->assertSame( $status, get_post_status( $page_id ), $status );
        }
        $request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $this->pages['tennis'] );
        $request->set_param( 'status', 'private' );
        $this->assertSame( 403, rest_get_server()->dispatch( $request )->get_status() );
    }

    public function test_revision_list_preview_and_restore_follow_branch_authorization(): void {
        $page_id = $this->pages['youth'];
        $foreign_id = $this->pages['tennis'];
        wp_set_current_user( $this->users['admin'] );
        $revision = wp_save_post_revision( $page_id );
        $foreign_revision = wp_save_post_revision( $foreign_id );
        wp_set_current_user( $this->users['fb'] );
        $request = new WP_REST_Request( 'GET', '/wp/v2/pages/' . $page_id . '/revisions' );
        $this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
        $request = new WP_REST_Request( 'GET', '/wp/v2/pages/' . $foreign_id . '/revisions' );
        $this->assertSame( 403, rest_get_server()->dispatch( $request )->get_status() );
        $this->assertTrue( current_user_can( 'edit_post', $revision ) );
        $this->assertSame( $page_id, wp_restore_post_revision( $revision ) );
        $this->assertFalse( current_user_can( 'edit_post', $foreign_revision ) );
    }

    public function test_foreign_page_is_denied_through_edit_capability_and_rest(): void {
        foreach ( [ 'fb' => 'tennis', 'youth' => 'football_training' ] as $fixture => $page_key ) {
            $foreign = $this->pages[ $page_key ];
            wp_set_current_user( $this->users[ $fixture ] );
            $this->assertFalse( current_user_can( 'edit_post', $foreign ) );
            $request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $foreign );
            $request->set_param( 'title', 'Unauthorized' );
            $response = rest_get_server()->dispatch( $request );
            $this->assertSame( 403, $response->get_status() );
        }

    }

    public function test_system_context_and_hierarchy_cycles_do_not_block_or_grant(): void {
        wp_set_current_user( 0 );
        $this->assertFalse( current_user_can( 'edit_post', $this->pages['football'] ) );
        wp_set_current_user( $this->users['fb'] );
        add_filter( 'wp_doing_cron', '__return_true' );
        $system_page = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $this->pages['tennis'] ], true );
        $this->assertIsInt( $system_page );
        $this->assertSame( $this->pages['tennis'], (int) get_post( $system_page )->post_parent );
        $cycle_a = self::factory()->post->create( [ 'post_type' => 'page', 'post_status' => 'publish' ] );
        $cycle_b = self::factory()->post->create( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $cycle_a ] );
        remove_filter( 'wp_doing_cron', '__return_true' );
        global $wpdb;
        $wpdb->update( $wpdb->posts, [ 'post_parent' => $cycle_b ], [ 'ID' => $cycle_a ] );
        clean_post_cache( $cycle_a );
        update_option( 'jp_permissions_groups', [
            'schema' => 1,
            'next_id' => 2,
            'groups' => [ 1 => [ 'name' => 'Cycle', 'roots' => [ $cycle_a ], 'members' => [ $this->users['fb'] ] ] ],
        ] );
        wp_set_current_user( $this->users['fb'] );
        $this->assertFalse( current_user_can( 'edit_post', $cycle_a ) );
        $this->assertFalse( current_user_can( 'edit_post', $this->pages['football'] ) );
    }
}
