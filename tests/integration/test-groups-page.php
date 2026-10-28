<?php

declare( strict_types = 1 );

final class Groups_Page_Integration_Test extends WP_UnitTestCase {
    use Branch_Fixtures;

    public function test_administrator_sees_group_list_and_unrestricted_members(): void {
        wp_set_current_user( $this->users['admin'] );
        ob_start();
        \JayPee\Permissions\GroupsPage::render();
        $html = ob_get_clean();
        $this->assertStringContainsString( 'Page Branches', $html );
        $this->assertStringContainsString( 'Football Editors', $html );
        $this->assertStringContainsString( 'unrestricted', $html );
        $this->assertStringContainsString( 'Add group', $html );
        $this->assertStringContainsString( 'Delete', $html );
    }

    public function test_edit_form_shows_roots_hierarchically_and_members(): void {
        wp_set_current_user( $this->users['admin'] );
        $_GET['edit'] = '1';
        ob_start();
        \JayPee\Permissions\GroupsPage::render();
        $html = ob_get_clean();
        unset( $_GET['edit'] );
        $staff = get_userdata( $this->users['fb_staff'] );
        $this->assertStringContainsString( $staff->display_name . ' (' . $staff->user_login . ')', $html );
        $this->assertStringContainsString( 'football', $html );
        $this->assertStringContainsString( 'Add root', $html );
        $this->assertStringContainsString( 'remove_root', $html );
        $this->assertStringContainsString( '_wpnonce', $html );
    }

    public function test_repository_validates_trimmed_and_case_insensitive_names(): void {
        $invalid = \JayPee\Permissions\GroupRepository::save( null, ' ', array(), array() );
        $this->assertWPError( $invalid );
        $this->assertSame( 'jp_permissions_invalid_name', $invalid->get_error_code() );
        $duplicate = \JayPee\Permissions\GroupRepository::save( null, 'football editors', array(), array() );
        $this->assertWPError( $duplicate );
        $this->assertSame( 'jp_permissions_duplicate_name', $duplicate->get_error_code() );
        $id = \JayPee\Permissions\GroupRepository::save( null, '  New group  ', array(), array() );
        $this->assertSame( 'New group', \JayPee\Permissions\GroupRepository::get( $id )['name'] );
        \JayPee\Permissions\GroupRepository::delete( $id );
        $next = \JayPee\Permissions\GroupRepository::save( null, 'Next group', array(), array() );
        $this->assertGreaterThan( $id, $next );
    }

    public function test_load_hook_re_renders_invalid_submitted_name(): void {
        wp_set_current_user( $this->users['admin'] );
        \JayPee\Permissions\GroupsPage::menu();
        $_POST = array(
            'jp_permissions_save_group' => 'Save group',
            'name' => '',
            '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
        );
        $_REQUEST = $_POST;
        do_action( 'load-users_page_jp-permissions-branches' );
        ob_start();
        \JayPee\Permissions\GroupsPage::render();
        $html = ob_get_clean();
        unset( $_POST['jp_permissions_save_group'], $_POST['name'], $_POST['_wpnonce'] );
        $_REQUEST = array();
        $this->assertStringContainsString( 'notice-error', $html );
        $this->assertStringContainsString( 'Enter a name', $html );
    }

    public function test_saving_drops_non_page_auto_draft_trashed_and_unknown_roots(): void {
        $post = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish' ) );
        $auto_draft = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'auto-draft' ) );
        $trashed = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'trash' ) );
        $id = \JayPee\Permissions\GroupRepository::save(
            null,
            'Root validation',
            array( $this->pages['football'], $post, $auto_draft, $trashed, 987654321 ),
            array( $this->users['fb'], 987654321 )
        );
        $group = \JayPee\Permissions\GroupRepository::get( $id );
        $this->assertSame( array( $this->pages['football'] ), $group['roots'] );
        $this->assertSame( array( $this->users['fb'] ), $group['members'] );
        wp_delete_post( $post, true );
        wp_delete_post( $auto_draft, true );
        wp_delete_post( $trashed, true );
    }
}
