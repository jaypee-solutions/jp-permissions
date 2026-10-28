<?php

declare( strict_types = 1 );

function jp_test_build_admin_menu(): void {
    global $menu, $submenu, $pagenow, $typenow, $_wp_menu_nopriv, $_wp_submenu_nopriv;
    require ABSPATH . 'wp-admin/menu.php';
}

final class Page_Creation_Redirect extends RuntimeException {}

final class Page_Creation_Integration_Test extends WP_UnitTestCase {
    use Branch_Fixtures;

    public function test_add_subpage_capability_and_row_action_follow_parent_authority(): void {
        foreach ( [ 'fb', 'admin', 'staff', 'fb_staff' ] as $user ) {
            wp_set_current_user( $this->users[ $user ] );
            $this->assertTrue( current_user_can( 'jp_permissions_add_subpage', $this->pages['football_training'] ), $user );
            $this->assertArrayHasKey( 'add_subpage', apply_filters( 'page_row_actions', [], get_post( $this->pages['football_training'] ) ) );
        }
        foreach ( [ 'fb', 'orphan', 'sub' ] as $user ) {
            wp_set_current_user( $this->users[ $user ] );
            $this->assertFalse( current_user_can( 'jp_permissions_add_subpage', $this->pages['tennis'] ), $user );
        }
    }

    public function test_add_subpage_handler_creates_auto_draft_and_redirects(): void {
        wp_set_current_user( $this->users['fb'] );
        $_GET = $_REQUEST = [ 'parent' => $this->pages['football_training'], '_wpnonce' => wp_create_nonce( 'jp_permissions_add_subpage_' . $this->pages['football_training'] ) ];
        add_filter( 'wp_redirect', static function ( $location ) { throw new Page_Creation_Redirect( $location ); } );
        try {
            do_action( 'admin_post_jp_permissions_add_subpage' );
            $this->fail( 'Expected redirect.' );
        } catch ( Page_Creation_Redirect $exception ) {
            $child = get_posts( [ 'post_type' => 'page', 'post_status' => 'auto-draft', 'post_parent' => $this->pages['football_training'], 'numberposts' => 1 ] );
            $this->assertCount( 1, $child );
            $this->assertStringContainsString( 'post.php?post=' . $child[0]->ID . '&action=edit', $exception->getMessage() );
            $this->assertNotFalse( wp_next_scheduled( 'wp_scheduled_auto_draft_delete' ) );
            $this->pages['created'] = $child[0]->ID;
        } finally {
            $_GET = $_REQUEST = [];
            remove_all_filters( 'wp_redirect' );
        }
    }

    public function test_restricted_rest_creation_requires_authorized_parent_and_zero_order(): void {
        wp_set_current_user( $this->users['fb'] );
        foreach ( [ [], [ 'parent' => $this->pages['tennis'] ] ] as $params ) {
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
        $_GET = [ 'post_type' => 'page' ];
        add_filter( 'wp_redirect', static function ( $location ) { throw new Page_Creation_Redirect( $location ); } );
        try {
            do_action( 'load-post-new.php' );
            $this->fail( 'Expected redirect.' );
        } catch ( Page_Creation_Redirect $exception ) {
            $this->assertStringContainsString( 'edit.php?post_type=page', $exception->getMessage() );
            $this->assertStringContainsString( 'jp_permissions_notice=use_add_subpage', $exception->getMessage() );
        } finally {
            remove_all_filters( 'wp_redirect' );
        }
        $_GET = [ 'jp_permissions_notice' => 'use_add_subpage' ];
        ob_start();
        do_action( 'admin_notices' );
        $notice = ob_get_clean();
        $this->assertStringContainsString( 'Add subpage', $notice );
        $_GET = [];
        $GLOBALS['pagenow'] = 'edit.php';
    }

    public function test_pages_admin_menu_remains_accessible_after_hiding_add_new(): void {
        wp_set_current_user( $this->users['fb'] );
        $GLOBALS['pagenow'] = 'edit.php';
        $GLOBALS['typenow'] = 'page';
        $_GET = $_REQUEST = [ 'post_type' => 'page' ];
        $GLOBALS['menu'] = $GLOBALS['submenu'] = [];
        $GLOBALS['_wp_menu_nopriv'] = $GLOBALS['_wp_submenu_nopriv'] = [];
        $GLOBALS['wp_did_header'] = false;

        try {
            jp_test_build_admin_menu();
            $this->assertFalse( has_action( 'admin_menu', [ \JayPee\Permissions\PageCreation::class, 'remove_add_new_menu' ] ) );
            $this->assertSame( 10, has_action( 'admin_init', [ \JayPee\Permissions\PageCreation::class, 'remove_add_new_menu' ] ) );
            remove_action( 'admin_init', 'wp_admin_headers' );
            remove_action( 'admin_init', 'send_frame_options_header' );
            remove_action( 'admin_init', [ WP_Privacy_Policy_Content::class, 'add_suggested_content' ], 1 );
            remove_action( 'admin_init', [ WP_Privacy_Policy_Content::class, 'text_change_check' ], 100 );
            try {
                do_action( 'admin_init' );
            } finally {
                add_action( 'admin_init', 'wp_admin_headers' );
                add_action( 'admin_init', 'send_frame_options_header' );
                add_action( 'admin_init', [ WP_Privacy_Policy_Content::class, 'add_suggested_content' ], 1 );
                add_action( 'admin_init', [ WP_Privacy_Policy_Content::class, 'text_change_check' ], 100 );
            }
            $this->assertSame( 'edit.php?post_type=page', get_admin_page_parent() );
            $this->assertTrue( user_can_access_admin_page() );
            $this->assertNotContains( 'post-new.php?post_type=page', wp_list_pluck( $GLOBALS['submenu']['edit.php?post_type=page'] ?? [], 2 ) );
            $_GET = $_REQUEST = [ 'post_type' => 'post' ];
            $GLOBALS['typenow'] = 'post';
            $GLOBALS['parent_file'] = '';
            $this->assertFalse( user_can_access_admin_page() );
        } catch ( WPDieException $exception ) {
            $this->fail( 'The Pages screen was denied: ' . $exception->getMessage() );
        } finally {
            $_GET = $_REQUEST = [];
            $GLOBALS['pagenow'] = 'edit.php';
            $GLOBALS['typenow'] = '';
        }
    }

    public function test_core_insert_requires_authorized_parent_and_default_order(): void {
        wp_set_current_user( $this->users['fb'] );
        foreach ( [ [ 'post_parent' => 0 ], [ 'post_parent' => $this->pages['tennis'] ], [ 'post_parent' => $this->pages['football_training'], 'menu_order' => 2 ] ] as $args ) {
            try {
                wp_insert_post( array_merge( [ 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Denied' ], $args ) );
                $this->fail( 'Expected core insert to be forbidden.' );
            } catch ( WPDieException $exception ) {
                $message = strtolower( $exception->getMessage() );
                $this->assertTrue( str_contains( $message, 'not allowed' ) || str_contains( $message, 'only administrators' ) );
            }
        }
    }
}
