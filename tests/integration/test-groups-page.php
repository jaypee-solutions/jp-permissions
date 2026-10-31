<?php

declare( strict_types = 1 );

final class Groups_Page_Redirect extends Exception {}

final class Groups_Page_Integration_Test extends WP_UnitTestCase {
    use Branch_Fixtures;

    public function test_administrator_sees_group_list_and_unrestricted_members(): void {
        wp_set_current_user( $this->users['admin'] );
        $config = get_option( 'jp_permissions_groups' );
        $config['groups'][1]['members'][] = $this->users['admin'];
        update_option( 'jp_permissions_groups', $config );
        $html = $this->render_groups_page();
        $restricted = get_userdata( $this->users['fb'] );
        $staff = get_userdata( $this->users['fb_staff'] );
        $admin = get_userdata( $this->users['admin'] );
        $this->assertStringContainsString( 'Page Branches', $html );
        $this->assertStringContainsString( 'Football Editors', $html );
        $this->assertStringContainsString( $restricted->display_name . ' (' . $restricted->user_login . ')', $html );
        $this->assertStringNotContainsString( $restricted->display_name . ' (' . $restricted->user_login . ') — unrestricted', $html );
        $this->assertStringContainsString( $staff->display_name . ' (' . $staff->user_login . ') — unrestricted', $html );
        $this->assertStringContainsString( $admin->display_name . ' (' . $admin->user_login . ') — unrestricted', $html );
        $this->assertStringContainsString( 'Add group', $html );
        $this->assertStringContainsString( 'Delete', $html );
    }

    public function test_edit_form_shows_roots_hierarchically_and_members(): void {
        wp_set_current_user( $this->users['admin'] );
        $_GET['edit'] = '1';
        $html = $this->render_groups_page();
        unset( $_GET['edit'] );
        $staff = get_userdata( $this->users['fb_staff'] );
        $this->assertStringContainsString( $staff->display_name . ' (' . $staff->user_login . ')', $html );
        $this->assertStringContainsString( 'football', $html );
        $this->assertStringContainsString( 'Add root', $html );
        $this->assertStringContainsString( 'remove_root', $html );
        $this->assertStringContainsString( '_wpnonce', $html );
    }

    public function test_group_form_validates_trimmed_and_case_insensitive_names(): void {
        wp_set_current_user( $this->users['admin'] );
        $this->submit_form( [
            'jp_permissions_save_group' => 'Save group',
            'name' => ' ',
            '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
        ] );
        $this->assertStringContainsString( 'Enter a name', $this->render_groups_page() );

        $this->submit_form( [
            'jp_permissions_save_group' => 'Save group',
            'name' => 'football editors',
            '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
        ] );
        $this->assertStringContainsString( 'already exists', $this->render_groups_page() );

        $this->submit_form( [
            'jp_permissions_save_group' => 'Save group',
            'name' => '  New group  ',
            '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
        ] );
        $groups = get_option( 'jp_permissions_groups' )['groups'];
        $new_group_id = $this->group_id_by_name( $groups, 'New group' );
        $this->assertSame( 'New group', $groups[ $new_group_id ]['name'] );
        $this->submit_delete_form( [
            'action' => 'jp_permissions_delete_group',
            'jp_permissions_delete_group' => 'Delete group',
            'group_id' => (string) $new_group_id,
            '_wpnonce' => wp_create_nonce( 'jp_permissions_delete_group_' . $new_group_id ),
        ] );
        $this->submit_form( [
            'jp_permissions_save_group' => 'Save group',
            'name' => 'Next group',
            '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
        ] );
        $groups = get_option( 'jp_permissions_groups' )['groups'];
        $next_group_id = $this->group_id_by_name( $groups, 'Next group' );
        $this->assertGreaterThan( $new_group_id, $next_group_id );
    }

    public function test_group_form_counts_and_folds_unicode_group_names(): void {
        wp_set_current_user( $this->users['admin'] );
        $name = str_repeat( 'é', 51 );
        $this->submit_form( [
            'jp_permissions_save_group' => 'Save group',
            'name' => $name,
            '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
        ] );
        $groups = get_option( 'jp_permissions_groups' )['groups'];
        $this->assertContains( $name, array_column( $groups, 'name' ) );
        $this->submit_form( [
            'jp_permissions_save_group' => 'Save group',
            'name' => str_repeat( 'É', 51 ),
            '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
        ] );
        $this->assertStringContainsString( 'already exists', $this->render_groups_page() );
        $this->submit_form( [
            'jp_permissions_save_group' => 'Save group',
            'name' => str_repeat( 'é', 101 ),
            '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
        ] );
        $this->assertStringContainsString( '1–100 characters', $this->render_groups_page() );
    }

    public function test_saving_deleted_group_id_does_not_recreate_it(): void {
        wp_set_current_user( $this->users['admin'] );
        $this->submit_form( [
            'jp_permissions_save_group' => 'Save group',
            'name' => 'Temporary',
            '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
        ] );
        $groups = get_option( 'jp_permissions_groups' )['groups'];
        $id = $this->group_id_by_name( $groups, 'Temporary' );
        $this->submit_delete_form( [
            'action' => 'jp_permissions_delete_group',
            'jp_permissions_delete_group' => 'Delete group',
            'group_id' => (string) $id,
            '_wpnonce' => wp_create_nonce( 'jp_permissions_delete_group_' . $id ),
        ] );
        $this->submit_form( [
            'jp_permissions_save_group' => 'Save group',
            'group_id' => (string) $id,
            'name' => 'Resurrected',
            '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
        ] );
        $this->assertNull( get_option( 'jp_permissions_groups' )['groups'][ $id ] ?? null );
        $this->assertStringContainsString( 'no longer exists', $this->render_groups_page() );
    }

    public function test_load_hook_re_renders_invalid_submitted_name(): void {
        wp_set_current_user( $this->users['admin'] );
        do_action( 'admin_menu' );
        $_POST = [
            'jp_permissions_save_group' => 'Save group',
            'name' => '',
            '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
        ];
        $_REQUEST = $_POST;
        do_action( 'load-users_page_jp-permissions-branches' );
        $html = $this->render_groups_page();
        unset( $_POST['jp_permissions_save_group'], $_POST['name'], $_POST['_wpnonce'] );
        $_REQUEST = [];
        $this->assertStringContainsString( 'notice-error', $html );
        $this->assertStringContainsString( 'Enter a name', $html );
    }
    public function test_invalid_form_preserves_pending_root_changes_for_resubmit(): void {
        wp_set_current_user( $this->users['admin'] );
        $this->submit_form(
            [
                'jp_permissions_save_group' => 'Save group',
                'group_id' => '1',
                'name' => '',
                'remove_root' => [ (string) $this->pages['football'] => '1' ],
                'add_root' => (string) $this->pages['tennis'],
                'members' => [ (string) $this->users['fb'] ],
                '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
            ],
            [ 'edit' => '1' ]
        );
        $html = $this->render_groups_page();
        $this->assertStringContainsString( 'name="roots[]" value="' . $this->pages['tennis'] . '"', $html );
        $this->assertStringContainsString( 'value="' . $this->users['fb'] . '"', $html );

        $this->submit_form(
            [
                'jp_permissions_save_group' => 'Save group',
                'group_id' => '1',
                'name' => 'Football Editors',
                'roots' => [ (string) $this->pages['tennis'] ],
                'members' => [ (string) $this->users['fb'] ],
                '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
            ],
            [ 'edit' => '1' ]
        );
        $this->assertSame( [ $this->pages['tennis'] ], get_option( 'jp_permissions_groups' )['groups'][1]['roots'] );
    }

    public function test_saving_drops_invalid_roots_and_unknown_members(): void {
        wp_set_current_user( $this->users['admin'] );
        $post = self::factory()->post->create( [ 'post_type' => 'post', 'post_status' => 'publish' ] );
        $auto_draft = self::factory()->post->create( [ 'post_type' => 'page', 'post_status' => 'auto-draft' ] );
        $trashed = self::factory()->post->create( [ 'post_type' => 'page', 'post_status' => 'trash' ] );
        $this->submit_form( [
            'jp_permissions_save_group' => 'Save group',
            'name' => 'Root validation',
            'roots' => [ $this->pages['football'], $post, $auto_draft, $trashed, 987654321 ],
            'members' => [ $this->users['fb'], 987654321 ],
            '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
        ] );
        $groups = get_option( 'jp_permissions_groups' )['groups'];
        $group_id = $this->group_id_by_name( $groups, 'Root validation' );
        $this->assertSame( [ $this->pages['football'] ], $groups[ $group_id ]['roots'] );
        $this->assertSame( [ $this->users['fb'] ], $groups[ $group_id ]['members'] );
        wp_delete_post( $post, true );
        wp_delete_post( $auto_draft, true );
        wp_delete_post( $trashed, true );
    }

    public function test_admin_load_hook_creates_renames_updates_roots_and_deletes_group(): void {
        wp_set_current_user( $this->users['admin'] );
        $group_id = $this->submit_form(
            [
                'jp_permissions_save_group' => 'Save group',
                'name' => 'New group',
                'add_root' => (string) $this->pages['football'],
                'members' => [ (string) $this->users['fb'] ],
                '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
            ]
        );
        $this->assertStringContainsString( 'updated=1', $group_id['redirect'] );
        $groups = get_option( 'jp_permissions_groups' )['groups'];
        $created_id = $this->group_id_by_name( $groups, 'New group' );
        $this->assertSame( [ $this->pages['football'] ], $groups[ $created_id ]['roots'] );

        $rename = $this->submit_form(
            [
                'jp_permissions_save_group' => 'Save group',
                'group_id' => (string) $created_id,
                'name' => 'Renamed group',
                'remove_root' => [ (string) $this->pages['football'] => '1' ],
                'add_root' => (string) $this->pages['tennis'],
                'members' => [ (string) $this->users['fb'] ],
                '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
            ]
        );
        $this->assertStringContainsString( 'updated=1', $rename['redirect'] );
        $groups = get_option( 'jp_permissions_groups' )['groups'];
        $this->assertSame( 'Renamed group', $groups[ $created_id ]['name'] );
        $this->assertSame( [ $this->pages['tennis'] ], $groups[ $created_id ]['roots'] );

        $delete = $this->submit_delete_form( [
            'action' => 'jp_permissions_delete_group',
            'jp_permissions_delete_group' => 'Delete group',
            'group_id' => (string) $created_id,
            '_wpnonce' => wp_create_nonce( 'jp_permissions_delete_group_' . $created_id ),
        ] );
        $this->assertStringContainsString( 'deleted=1', $delete['redirect'] );
        $this->assertArrayNotHasKey( $created_id, get_option( 'jp_permissions_groups' )['groups'] );
    }


    public function test_load_hook_denies_non_admin(): void {
        wp_set_current_user( $this->users['admin'] );
        $valid_save = [
            'jp_permissions_save_group' => 'Save group',
            'name' => 'Forbidden group',
            '_wpnonce' => wp_create_nonce( 'jp_permissions_save_group' ),
        ];
        wp_set_current_user( $this->users['sub'] );
        $this->expectException( WPDieException::class );
        $this->submit_form( $valid_save );
    }

    public function test_load_hook_rejects_invalid_and_missing_save_nonces(): void {
        wp_set_current_user( $this->users['admin'] );
        foreach ( [ 'invalid', '' ] as $nonce ) {
            $post = [ 'jp_permissions_save_group' => 'Save group', 'name' => 'No save', '_wpnonce' => $nonce ];
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
        foreach ( [ 'invalid', '' ] as $nonce ) {
            $post = [ 'action' => 'jp_permissions_delete_group', 'jp_permissions_delete_group' => 'Delete group', 'group_id' => (string) $group_id, '_wpnonce' => $nonce ];
            try {
                $this->submit_delete_form( $post );
                $this->fail( 'Invalid delete nonce was accepted.' );
            } catch ( WPDieException $exception ) {
                $this->assertNotEmpty( $exception->getMessage() );
            }
        }
    }

    private function submit_form( array $post, array $get = [] ): array {
        $hook = 'load-users_page_jp-permissions-branches';
        remove_all_actions( $hook );
        do_action( 'admin_menu' );
        $_POST = $post;
        $_GET = $get;
        $_REQUEST = array_merge( $get, $post );
        $redirect = static function ( string $location ): void {
            throw new Groups_Page_Redirect( $location );
        };
        add_filter( 'wp_redirect', $redirect );
        try {
            do_action( $hook );
        } catch ( Groups_Page_Redirect $exception ) {
            return [ 'redirect' => $exception->getMessage() ];
        } finally {
            remove_filter( 'wp_redirect', $redirect );
        }
        return [];
    }

    private function submit_delete_form( array $post ): array {
        $_POST = $post;
        $_REQUEST = $_POST;
        $_GET = [];
        $redirect = static function ( string $location ): void {
            throw new Groups_Page_Redirect( $location );
        };
        add_filter( 'wp_redirect', $redirect );
        try {
            do_action( 'admin_post_jp_permissions_delete_group' );
        } catch ( Groups_Page_Redirect $exception ) {
            return [ 'redirect' => $exception->getMessage() ];
        } finally {
            remove_filter( 'wp_redirect', $redirect );
        }
        return [];
    }


    private function group_id_by_name( array $groups, string $name ): int {
        foreach ( $groups as $id => $group ) {
            if ( $group['name'] === $name ) {
                return (int) $id;
            }
        }
        $this->fail( 'Could not find group ' . $name . '.' );
    }

    private function render_groups_page(): string {
        do_action( 'admin_menu' );
        $hook_name = get_plugin_page_hookname( 'jp-permissions-branches', 'users.php' );
        ob_start();
        do_action( $hook_name );
        return ob_get_clean();
    }
}
