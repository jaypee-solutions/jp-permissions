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
    public function test_post_actions_and_pages_bulk_actions_enforce_delete_capability(): void {
        foreach ( array( 'trash', 'untrash', 'delete' ) as $action ) {
            wp_set_current_user( $this->users['admin'] );
            $page_id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'untrash' === $action ? 'trash' : 'publish' ) );
            wp_set_current_user( $this->users['fb'] );
            $this->run_deletion_action( $action, $page_id, 'trash-post_' . $page_id, false );
            $this->assertSame( 'untrash' === $action ? 'trash' : 'publish', get_post_status( $page_id ), 'post.php ' . $action );

            wp_set_current_user( $this->users['admin'] );
            $bulk_id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'untrash' === $action ? 'trash' : 'publish' ) );
            wp_set_current_user( $this->users['fb'] );
            $this->run_deletion_action( $action, $bulk_id, 'bulk-posts', false );
            $this->assertSame( 'untrash' === $action ? 'trash' : 'publish', get_post_status( $bulk_id ), 'Pages bulk ' . $action );

            wp_set_current_user( $this->users['admin'] );
            $admin_id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'untrash' === $action ? 'trash' : 'publish' ) );
            $this->run_deletion_action( $action, $admin_id, 'bulk-posts', true );
            $this->assertSame( 'delete' === $action ? false : ( 'untrash' === $action ? 'draft' : 'trash' ), get_post_status( $admin_id ), 'Administrator ' . $action );
        }
    }

    private function run_deletion_action( string $action, int $page_id, string $nonce_action, bool $expect_success ): void {
        $nonce = wp_create_nonce( $nonce_action );
        $this->assertTrue( (bool) wp_verify_nonce( $nonce, $nonce_action ) );
        $die_handler = static function ( $message ): void {
            throw new WPDieException( (string) $message );
        };
        $get_die_handler = static fn() => $die_handler;
        add_filter( 'wp_die_handler', $get_die_handler );
        add_filter( 'wp_die_ajax_handler', $get_die_handler );
        try {
            if ( ! current_user_can( 'delete_post', $page_id ) ) {
                wp_die( 'Sorry, you are not allowed to delete this item.', '', array( 'response' => 403 ) );
            }
            if ( 'trash' === $action ) {
                $result = wp_trash_post( $page_id );
            } elseif ( 'untrash' === $action ) {
                $result = wp_untrash_post( $page_id );
            } else {
                $result = wp_delete_post( $page_id, true );
            }
            $this->assertTrue( $expect_success, 'Restricted action unexpectedly succeeded.' );
            $this->assertNotFalse( $result );
        } catch ( WPDieException $exception ) {
            $this->assertFalse( $expect_success, $exception->getMessage() );
            $this->assertSame( 'Sorry, you are not allowed to delete this item.', $exception->getMessage() );
        } finally {
            remove_filter( 'wp_die_handler', $get_die_handler );
            remove_filter( 'wp_die_ajax_handler', $get_die_handler );
        }
    }
}
