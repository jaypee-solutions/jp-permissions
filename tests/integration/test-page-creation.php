<?php

declare( strict_types = 1 );

function jp_test_build_admin_menu(): void {
    global $menu, $submenu, $pagenow, $typenow, $_wp_menu_nopriv, $_wp_submenu_nopriv;
    require ABSPATH . 'wp-admin/menu.php';
}

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
        $_GET = $_REQUEST = array( 'parent' => $this->pages['football_training'], '_wpnonce' => wp_create_nonce( 'jp_permissions_add_subpage_' . $this->pages['football_training'] ) );
        add_filter( 'wp_redirect', static function ( $location ) { throw new RuntimeException( $location ); } );
        try {
\JayPee\Permissions\PageCreation::add_subpage();
            $this->fail( 'Expected redirect.' );
        } catch ( RuntimeException $exception ) {
            $child = get_posts( array( 'post_type' => 'page', 'post_status' => 'auto-draft', 'post_parent' => $this->pages['football_training'], 'numberposts' => 1 ) );
            $this->assertCount( 1, $child );
            $this->assertStringContainsString( 'post.php?post=' . $child[0]->ID . '&action=edit', $exception->getMessage() );
            $this->pages['created'] = $child[0]->ID;
        } finally {
            $_GET = $_REQUEST = array();
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
            \JayPee\Permissions\PageCreation::redirect_generic_new_page();
            $this->fail( 'Expected redirect.' );
        } catch ( RuntimeException $exception ) {
            $this->assertStringContainsString( 'edit.php?post_type=page', $exception->getMessage() );
            $this->assertStringContainsString( 'jp_permissions_notice=use_add_subpage', $exception->getMessage() );
        } finally {
            remove_all_filters( 'wp_redirect' );
        }
        $_GET = array( 'jp_permissions_notice' => 'use_add_subpage' );
        ob_start();
        \JayPee\Permissions\PageCreation::notice();
        $notice = ob_get_clean();
        $this->assertStringContainsString( 'Add subpage', $notice );
        $_GET = array();
        $GLOBALS['pagenow'] = 'edit.php';
    }

    public function test_pages_admin_menu_remains_accessible_after_hiding_add_new(): void {
        wp_set_current_user( $this->users['fb'] );
        $GLOBALS['pagenow'] = 'edit.php';
        $GLOBALS['typenow'] = 'page';
        $_GET = $_REQUEST = array( 'post_type' => 'page' );
        $GLOBALS['menu'] = $GLOBALS['submenu'] = array();
        $GLOBALS['_wp_menu_nopriv'] = $GLOBALS['_wp_submenu_nopriv'] = array();
        $GLOBALS['wp_did_header'] = false;

        try {
            jp_test_build_admin_menu();
            $this->assertFalse( has_action( 'admin_menu', array( \JayPee\Permissions\PageCreation::class, 'remove_add_new_menu' ) ) );
            $this->assertSame( 10, has_action( 'admin_init', array( \JayPee\Permissions\PageCreation::class, 'remove_add_new_menu' ) ) );
            \JayPee\Permissions\PageCreation::remove_add_new_menu();
            $this->assertSame( 'edit.php?post_type=page', get_admin_page_parent() );
            $this->assertTrue( user_can_access_admin_page() );
            $this->assertNotContains( 'post-new.php?post_type=page', wp_list_pluck( $GLOBALS['submenu']['edit.php?post_type=page'] ?? array(), 2 ) );
            $_GET = $_REQUEST = array( 'post_type' => 'post' );
            $GLOBALS['typenow'] = 'post';
            $GLOBALS['parent_file'] = '';
            $this->assertFalse( user_can_access_admin_page() );
        } catch ( WPDieException $exception ) {
            $this->fail( 'The Pages screen was denied: ' . $exception->getMessage() );
        } finally {
            $_GET = $_REQUEST = array();
            $GLOBALS['pagenow'] = 'edit.php';
            $GLOBALS['typenow'] = '';
        }
    }

    public function test_core_insert_requires_authorized_parent_and_default_order(): void {
        wp_set_current_user( $this->users['fb'] );
        foreach ( array( array( 'post_parent' => 0 ), array( 'post_parent' => $this->pages['tennis'] ), array( 'post_parent' => $this->pages['football_training'], 'menu_order' => 2 ) ) as $args ) {
            try {
                wp_insert_post( array_merge( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Denied' ), $args ) );
                $this->fail( 'Expected core insert to be forbidden.' );
            } catch ( WPDieException $exception ) {
                $message = strtolower( $exception->getMessage() );
                $this->assertTrue( str_contains( $message, 'not allowed' ) || str_contains( $message, 'only administrators' ) );
            }
        }
    }
}
