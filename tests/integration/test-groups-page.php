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

    public function test_admin_load_hook_creates_renames_updates_roots_and_deletes_group(): void {
        wp_set_current_user( $this->users['admin'] );
        $group_id = $this->submit_form(
            array(
                'jp_permissions_save_group' => 'Save group',
                'name' => 'New group',
                'add_root' => (string) $this->pages['football'],
                'members' => array( (string) $this->users['fb'] ),
                '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
            )
        );
        $this->assertStringContainsString( 'updated=1', $group_id['redirect'] );
        $created_id = max( array_keys( \JayPee\Permissions\GroupRepository::all() ) );
        $group = \JayPee\Permissions\GroupRepository::get( $created_id );
        $this->assertSame( 'New group', $group['name'] );
        $this->assertSame( array( $this->pages['football'] ), $group['roots'] );

        $rename = $this->submit_form(
            array(
                'jp_permissions_save_group' => 'Save group',
                'group_id' => (string) $created_id,
                'name' => 'Renamed group',
                'remove_root' => array( (string) $this->pages['football'] => '1' ),
                'add_root' => (string) $this->pages['tennis'],
                'members' => array( (string) $this->users['fb'] ),
                '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
            )
        );
        $this->assertStringContainsString( 'updated=1', $rename['redirect'] );
        $group = \JayPee\Permissions\GroupRepository::get( $created_id );
        $this->assertSame( 'Renamed group', $group['name'] );
        $this->assertSame( array( $this->pages['tennis'] ), $group['roots'] );

        $delete = $this->submit_form(
            array(
                'jp_permissions_delete_group' => 'Delete group',
                'group_id' => (string) $created_id,
                '_wpnonce' => wp_create_nonce( 'jp_permissions_delete_group_' . $created_id ),
            ),
            array( 'delete' => (string) $created_id )
        );
        $this->assertStringContainsString( 'deleted=1', $delete['redirect'] );
        $this->assertNull( \JayPee\Permissions\GroupRepository::get( $created_id ) );
    }

    public function test_load_hook_denies_non_admin(): void {
        wp_set_current_user( $this->users['admin'] );
        $valid_save = array(
            'jp_permissions_save_group' => 'Save group',
            'name' => 'Forbidden group',
            '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
        );
        wp_set_current_user( $this->users['sub'] );
        $this->expectException( WPDieException::class );
        $this->submit_form( $valid_save );
    }

    public function test_load_hook_rejects_invalid_and_missing_save_nonces(): void {
        wp_set_current_user( $this->users['admin'] );
        foreach ( array( 'invalid', '' ) as $nonce ) {
            $post = array( 'jp_permissions_save_group' => 'Save group', 'name' => 'No save', '_wpnonce' => $nonce );
            try {
                $this->submit_form( $post );
                $this->fail( 'Invalid save nonce was accepted.' );
            } catch ( WPDieException $exception ) {
                $this->assertNotEmpty( $exception->getMessage() );
            }
        }
    }

    public function test_load_hook_rejects_invalid_and_missing_delete_nonces(): void {
        wp_set_current_user( $this->users['admin'] );
        $group_id = 1;
        foreach ( array( 'invalid', '' ) as $nonce ) {
            $post = array( 'jp_permissions_delete_group' => 'Delete group', 'group_id' => (string) $group_id, '_wpnonce' => $nonce );
            try {
                $this->submit_form( $post, array( 'delete' => (string) $group_id ) );
                $this->fail( 'Invalid delete nonce was accepted.' );
            } catch ( WPDieException $exception ) {
                $this->assertNotEmpty( $exception->getMessage() );
            }
        }
    }

    private function submit_form( array $post, array $get = array() ): array {
        $hook = 'load-users_page_jp-permissions-branches';
        remove_all_actions( $hook );
        \JayPee\Permissions\GroupsPage::menu();
        $_POST = $post;
        $_GET = $get;
        $_REQUEST = array_merge( $get, $post );
        $redirect = static function ( string $location ): void {
            throw new RuntimeException( $location );
        };
        add_filter( 'wp_redirect', $redirect );
        try {
            do_action( $hook );
        } catch ( RuntimeException $exception ) {
            return array( 'redirect' => $exception->getMessage() );
        } finally {
            remove_filter( 'wp_redirect', $redirect );
        }
        return array();
    }
}
