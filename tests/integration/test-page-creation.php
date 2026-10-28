<?php

declare( strict_types = 1 );

final class Page_Creation_Integration_Test extends WP_UnitTestCase {
    use Branch_Fixtures;

    public function test_add_subpage_capability_and_row_action_follow_parent_authority(): void {
        foreach ( array( 'fb', 'admin', 'staff', 'fb_staff' ) as $user ) {
            wp_set_current_user( $this->users[ $user ] );
            $this->assertTrue( current_user_can( 'jp_permissions_add_subpage', $this->pages['football_training'] ), $user );
            $this->assertArrayHasKey( 'add_subpage', apply_filters( 'page_row_actions', array(), get_post( $this->pages['football_training'] ) ) );
        }
        foreach ( array( 'fb', 'orphan', 'sub' ) as $user ) {
            wp_set_current_user( $this->users[ $user ] );
            $this->assertFalse( current_user_can( 'jp_permissions_add_subpage', $this->pages['tennis'] ), $user );
        }
    }

    public function test_add_subpage_handler_creates_auto_draft_and_redirects(): void {
        wp_set_current_user( $this->users['fb'] );
        $_GET = array( 'parent' => $this->pages['football_training'], '_wpnonce' => wp_create_nonce( 'jp_permissions_add_subpage_' . $this->pages['football_training'] ) );
        add_filter( 'wp_redirect', static function ( $location ) { throw new RuntimeException( $location ); } );
        try {
            PageCreation::add_subpage();
            $this->fail( 'Expected redirect.' );
        } catch ( RuntimeException $exception ) {
            $child = get_posts( array( 'post_type' => 'page', 'post_status' => 'auto-draft', 'post_parent' => $this->pages['football_training'], 'numberposts' => 1 ) );
            $this->assertCount( 1, $child );
            $this->assertStringContainsString( 'post.php?post=' . $child[0]->ID . '&action=edit', $exception->getMessage() );
            $this->pages['created'] = $child[0]->ID;
        } finally {
            $_GET = array();
            remove_all_filters( 'wp_redirect' );
        }
    }

    public function test_restricted_rest_creation_requires_authorized_parent_and_zero_order(): void {
        wp_set_current_user( $this->users['fb'] );
        foreach ( array( array(), array( 'parent' => $this->pages['tennis'] ) ) as $params ) {
            $request = new WP_REST_Request( 'POST', '/wp/v2/pages' );
            $request->set_param( 'title', 'Denied' );
            foreach ( $params as $key => $value ) { $request->set_param( $key, $value ); }
            $response = rest_get_server()->dispatch( $request );
            $this->assertSame( 403, $response->get_status() );
            $this->assertSame( 'jp_permissions_forbidden_parent', $response->get_data()['code'] );
        }
        $request = new WP_REST_Request( 'POST', '/wp/v2/pages' );
        $request->set_param( 'title', 'Denied order' );
        $request->set_param( 'parent', $this->pages['football_training'] );
        $request->set_param( 'menu_order', 4 );
        $response = rest_get_server()->dispatch( $request );
        $this->assertSame( 403, $response->get_status() );
        $this->assertSame( 'jp_permissions_forbidden_order', $response->get_data()['code'] );
        $request = new WP_REST_Request( 'POST', '/wp/v2/pages' );
        $request->set_param( 'title', 'Allowed' );
        $request->set_param( 'parent', $this->pages['football_training'] );
        $response = rest_get_server()->dispatch( $request );
        $this->assertSame( 201, $response->get_status() );
        $this->assertSame( $this->pages['football_training'], $response->get_data()['parent'] );
    }

    public function test_generic_new_page_redirect_and_notice(): void {
        wp_set_current_user( $this->users['fb'] );
        $GLOBALS['pagenow'] = 'post-new.php';
        $_GET = array( 'post_type' => 'page' );
        add_filter( 'wp_redirect', static function ( $location ) { throw new RuntimeException( $location ); } );
        try {
            PageCreation::redirect_generic_new_page();
            $this->fail( 'Expected redirect.' );
        } catch ( RuntimeException $exception ) {
            $this->assertStringContainsString( 'edit.php?post_type=page', $exception->getMessage() );
            $this->assertStringContainsString( 'jp_permissions_notice=use_add_subpage', $exception->getMessage() );
        } finally {
            remove_all_filters( 'wp_redirect' );
        }
        $_GET = array( 'jp_permissions_notice' => 'use_add_subpage' );
        ob_start();
        PageCreation::notice();
        $notice = ob_get_clean();
        $this->assertStringContainsString( 'Add subpage', $notice );
        $_GET = array();
        $GLOBALS['pagenow'] = 'edit.php';
    }

    public function test_core_insert_requires_authorized_parent_and_default_order(): void {
        wp_set_current_user( $this->users['fb'] );
        foreach ( array( array( 'post_parent' => 0 ), array( 'post_parent' => $this->pages['tennis'] ), array( 'post_parent' => $this->pages['football_training'], 'menu_order' => 2 ) ) as $args ) {
            try {
                wp_insert_post( array_merge( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Denied' ), $args ) );
                $this->fail( 'Expected core insert to be forbidden.' );
            } catch ( WPDieException $exception ) {
                $this->assertStringContainsString( 'forbidden', strtolower( $exception->getMessage() ) );
            }
        }
    }
}
