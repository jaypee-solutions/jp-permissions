<?php

declare( strict_types = 1 );

final class Write_Guard_Integration_Test extends WP_UnitTestCase {
    use Branch_Fixtures;

    public function test_restricted_rest_updates_reject_parent_and_order_changes(): void {
        wp_set_current_user( $this->users['fb'] );
        foreach ( array( 'parent' => $this->pages['tennis'], 'menu_order' => 7 ) as $field => $value ) {
            $request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $this->pages['football_training'] );
            $request->set_param( $field, $value );
            $response = rest_get_server()->dispatch( $request );
            $this->assertSame( 403, $response->get_status(), $field );
            $this->assertSame( 'jp_permissions_forbidden_' . ( 'parent' === $field ? 'parent' : 'order' ), $response->get_data()['code'] );
        }
        $this->assertSame( $this->pages['football'], (int) get_post_field( 'post_parent', $this->pages['football_training'] ) );
        $this->assertSame( 0, (int) get_post_field( 'menu_order', $this->pages['football_training'] ) );
    }

    public function test_restricted_rest_omitted_and_unchanged_parent_and_order_pass(): void {
        wp_set_current_user( $this->users['fb'] );
        foreach ( array( array(), array( 'parent' => $this->pages['football'], 'menu_order' => 0 ) ) as $params ) {
            $request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $this->pages['football_training'] );
            $request->set_param( 'title', 'Still editable' );
            foreach ( $params as $key => $value ) {
                $request->set_param( $key, $value );
            }
            $this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
        }

        $auto_draft = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'auto-draft', 'post_parent' => $this->pages['football'] ) );
        $this->pages['auto_draft'] = $auto_draft;
        $this->assertNotWPError( wp_update_post( array( 'ID' => $auto_draft, 'post_title' => 'Autosaved draft' ), true ) );
        $this->assertSame( $this->pages['football'], (int) get_post_field( 'post_parent', $auto_draft ) );
    }

    public function test_restricted_classic_edit_and_quick_bulk_core_writes_are_blocked(): void {
        wp_set_current_user( $this->users['fb'] );
        foreach ( array( 'post_parent' => $this->pages['tennis'], 'menu_order' => 9 ) as $field => $value ) {
            try {
                edit_post( array( 'ID' => $this->pages['football_training'], $field => $value ) );
                $this->fail( 'Expected restricted edit_post update to be forbidden: ' . $field );
            } catch ( WPDieException $exception ) {
                $this->assertStringContainsString( 'Forbidden', $exception->getMessage() );
            }
            $this->assertSame( $this->pages['football'], (int) get_post_field( 'post_parent', $this->pages['football_training'] ) );
            $this->assertSame( 0, (int) get_post_field( 'menu_order', $this->pages['football_training'] ) );
        }
        foreach ( array( 'post_parent' => $this->pages['tennis'], 'menu_order' => 11 ) as $field => $value ) {
            try {
                wp_update_post( array( 'ID' => $this->pages['football_training'], $field => $value ), true );
                $this->fail( 'Expected restricted core update to be forbidden: ' . $field );
            } catch ( WPDieException $exception ) {
                $this->assertStringContainsString( 'Forbidden', $exception->getMessage() );
            }
        }
    }

    public function test_unrestricted_users_can_reorder_pages(): void {
        foreach ( array( 'staff', 'fb_staff', 'admin' ) as $user ) {
            wp_set_current_user( $this->users[ $user ] );
            $request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $this->pages['football_training'] );
            $request->set_param( 'menu_order', 3 );
            $this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
            $this->assertSame( 3, (int) get_post_field( 'menu_order', $this->pages['football_training'] ) );
        }
    }
}
