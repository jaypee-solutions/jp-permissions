<?php

declare( strict_types = 1 );

final class Deletion_Integration_Test extends WP_UnitTestCase {
    use Branch_Fixtures;

    public function test_restricted_rest_delete_is_denied_without_changing_page(): void {
        wp_set_current_user( $this->users['fb'] );
        $request = new WP_REST_Request( 'DELETE', '/wp/v2/pages/' . $this->pages['football_training'] );
        $response = rest_get_server()->dispatch( $request );
        $this->assertSame( 403, $response->get_status() );
        $this->assertSame( 'publish', get_post_status( $this->pages['football_training'] ) );
    }

    public function test_restricted_regular_update_cannot_transition_into_trash(): void {
        wp_set_current_user( $this->users['fb'] );
        $die_handler = static function ( $message ): void {
            throw new WPDieException( (string) $message );
        };
        $get_die_handler = static fn() => $die_handler;
        add_filter( 'wp_die_handler', $get_die_handler );
        add_filter( 'wp_die_ajax_handler', $get_die_handler );
        try {
            wp_update_post( array( 'ID' => $this->pages['football_training'], 'post_status' => 'trash' ), true );
            $this->fail( 'Expected the trash transition to be forbidden.' );
        } catch ( WPDieException $exception ) {
            $this->assertStringContainsString( 'Only administrators can trash, restore, or delete pages.', $exception->getMessage() );
        } finally {
            remove_filter( 'wp_die_handler', $get_die_handler );
            remove_filter( 'wp_die_ajax_handler', $get_die_handler );
        }
        $this->assertSame( 'publish', get_post_status( $this->pages['football_training'] ) );
    }
}
