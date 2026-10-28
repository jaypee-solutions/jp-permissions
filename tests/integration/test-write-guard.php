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
            $expected = 'menu_order' === $field ? 'order' : 'parent';
            $this->assertSame( 'jp_permissions_forbidden_' . $expected, $response->get_data()['code'] );
        }
        $this->assertSame( $this->pages['football'], (int) get_post_field( 'post_parent', $this->pages['football_training'] ) );
        $this->assertSame( 0, (int) get_post_field( 'menu_order', $this->pages['football_training'] ) );
    }

    public function test_restricted_rest_omitted_and_unchanged_values_and_auto_draft_pass(): void {
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

    public function test_classic_quick_edit_and_bulk_edit_handlers_reject_structure_changes(): void {
        require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';
        wp_set_current_user( $this->users['fb'] );
        foreach ( array( 'post_parent' => $this->pages['tennis'], 'menu_order' => 9 ) as $field => $value ) {
            try {
                edit_post( array( 'post_ID' => $this->pages['football_training'], $field => $value ) );
                $this->fail( 'Expected restricted edit_post update to be forbidden: ' . $field );
            } catch ( WPDieException $exception ) {
                $this->assertStringContainsString( 'only administrators', strtolower( $exception->getMessage() ) );
            }
        }
        $die_handler = static function ( $message ): void {
            throw new WPDieException( (string) $message );
        };
        $get_die_handler = static fn() => $die_handler;
        add_filter( 'wp_die_handler', $get_die_handler );
        add_filter( 'wp_die_ajax_handler', $get_die_handler );
        add_filter( 'wp_doing_ajax', '__return_true' );
        try {
            foreach ( array( 'post_parent' => $this->pages['tennis'], 'menu_order' => 12 ) as $field => $value ) {
                $nonce = wp_create_nonce( 'inlineeditnonce' );
                $_POST = array( 'post_ID' => $this->pages['football_training'], 'post_type' => 'page', '_inline_edit' => $nonce, 'screen' => 'edit-page', 'post_view' => 'list', $field => $value );
                $_REQUEST = $_POST;
                ob_start();
                try {
                    wp_ajax_inline_save();
                    $this->fail( 'Expected Quick Edit structure update to be forbidden: ' . $field );
                } catch ( WPDieException $exception ) {
                    $this->assertStringContainsString( 'only administrators', strtolower( $exception->getMessage() ) );
                } finally {
                    ob_end_clean();
                    $_POST = array();
                    $_REQUEST = array();
                }
            }
            try {
                bulk_edit_posts( array( 'post_type' => 'page', '_status' => '-1', 'post' => array( $this->pages['football_training'] ), 'post_parent' => $this->pages['tennis'] ) );
                $this->fail( 'Expected bulk edit move to be forbidden.' );
            } catch ( WPDieException $exception ) {
                $this->assertStringContainsString( 'only administrators', strtolower( $exception->getMessage() ) );
            }
        } finally {
            remove_filter( 'wp_die_handler', $get_die_handler );
            remove_filter( 'wp_die_ajax_handler', $get_die_handler );
            remove_filter( 'wp_doing_ajax', '__return_true' );
            $_POST = array();
            $_REQUEST = array();
        }
        $this->assertSame( $this->pages['football'], (int) get_post_field( 'post_parent', $this->pages['football_training'] ) );
        $this->assertSame( 0, (int) get_post_field( 'menu_order', $this->pages['football_training'] ) );
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
