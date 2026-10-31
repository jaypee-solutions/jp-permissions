<?php

declare( strict_types = 1 );

final class Root_Lock_Integration_Test extends WP_UnitTestCase {
    use Branch_Fixtures;

    public function test_restricted_rest_locks_root_slug_and_status_but_not_content_or_descendant_status(): void {
        wp_set_current_user( $this->users['fb'] );
        foreach ( [ 'football', 'youth' ] as $root ) {
            foreach ( [ 'slug' => 'changed-' . $root, 'status' => 'draft' ] as $field => $value ) {
                $request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $this->pages[ $root ] );
                $request->set_param( $field, $value );
                $response = rest_get_server()->dispatch( $request );
                $this->assertSame( 403, $response->get_status() );
                $this->assertSame( 'jp_permissions_forbidden_root_change', $response->get_data()['code'] );
            }
        }
        $football = get_post( $this->pages['football'] );
        $youth = get_post( $this->pages['youth'] );
        $this->assertSame( 'football', $football->post_name );
        $this->assertSame( 'publish', $football->post_status );
        $this->assertSame( 'youth', $youth->post_name );
        $this->assertSame( 'publish', $youth->post_status );

        $request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $this->pages['football'] );
        $request->set_param( 'title', 'Edited root title' );
        $request->set_param( 'content', 'Edited root content' );
        $this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
        $this->assertSame( 'Edited root title', get_post_field( 'post_title', $this->pages['football'] ) );
        $this->assertSame( 'Edited root content', get_post_field( 'post_content', $this->pages['football'] ) );

        $request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $this->pages['football_training'] );
        $request->set_param( 'status', 'draft' );
        $this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
        $this->assertSame( 'draft', get_post_field( 'post_status', $this->pages['football_training'] ) );
    }

    public function test_quick_edit_rejects_root_slug_and_status_changes_without_exiting(): void {
        require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';
        wp_set_current_user( $this->users['fb'] );
        $die_handler = static function ( $message ): void {
            throw new WPDieException( (string) $message );
        };
        $get_die_handler = static fn() => $die_handler;
        add_filter( 'wp_die_handler', $get_die_handler );
        add_filter( 'wp_die_ajax_handler', $get_die_handler );
        add_filter( 'wp_doing_ajax', '__return_true' );
        try {
            foreach ( [ 'football', 'youth' ] as $root ) {
                foreach ( [ 'post_name' => 'changed-' . $root, 'post_status' => 'draft' ] as $field => $value ) {
                    $nonce = wp_create_nonce( 'inlineeditnonce' );
                    $_POST = [ 'post_ID' => $this->pages[ $root ], 'post_type' => 'page', '_inline_edit' => $nonce, 'screen' => 'edit-page', 'post_view' => 'list', $field => $value ];
                    $_REQUEST = $_POST;
                    ob_start();
                    try {
                        wp_ajax_inline_save();
                        $this->fail( 'Expected Quick Edit root change to be forbidden: ' . $field );
                    } catch ( WPDieException $exception ) {
                        $this->assertStringContainsString( 'only administrators can change the address or status', strtolower( $exception->getMessage() ) );
                    } finally {
                        ob_end_clean();
                        $_POST = [];
                        $_REQUEST = [];
                    }
                }
            }
        } finally {
            remove_filter( 'wp_die_handler', $get_die_handler );
            remove_filter( 'wp_die_ajax_handler', $get_die_handler );
            remove_filter( 'wp_doing_ajax', '__return_true' );
        }
        $this->assertSame( 'football', get_post_field( 'post_name', $this->pages['football'] ) );
        $this->assertSame( 'publish', get_post_field( 'post_status', $this->pages['football'] ) );
        $this->assertSame( 'youth', get_post_field( 'post_name', $this->pages['youth'] ) );
        $this->assertSame( 'publish', get_post_field( 'post_status', $this->pages['youth'] ) );
    }

    public function test_administrators_can_change_root_slug_and_status(): void {
        wp_set_current_user( $this->users['admin'] );
        $request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $this->pages['football'] );
        $request->set_param( 'slug', 'football-updated' );
        $request->set_param( 'status', 'draft' );
        $this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
        $this->assertSame( 'football-updated', get_post_field( 'post_name', $this->pages['football'] ) );
        $this->assertSame( 'draft', get_post_field( 'post_status', $this->pages['football'] ) );
    }
}
