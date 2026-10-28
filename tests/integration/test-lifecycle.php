<?php

declare( strict_types = 1 );

final class Lifecycle_Integration_Test extends WP_UnitTestCase {
    use Branch_Fixtures;

    public function test_deleted_pages_and_users_are_removed_from_all_group_assignments(): void {
        $page_id = $this->pages['football'];
        wp_delete_post( $page_id, true );
        $groups = \JayPee\Permissions\GroupRepository::all();
        foreach ( $groups as $group ) {
            $this->assertNotContains( $page_id, $group['roots'] );
        }
        $user_id = $this->users['fb'];
        wp_delete_user( $user_id );
        foreach ( \JayPee\Permissions\GroupRepository::all() as $group ) {
            $this->assertNotContains( $user_id, $group['members'] );
        }
    }

    public function test_trash_keeps_descendant_authorization_and_permanent_deletion_removes_it(): void {
        wp_set_current_user( $this->users['fb'] );
        wp_trash_post( $this->pages['football'] );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['football_training'] ) );

        wp_set_current_user( $this->users['admin'] );
        wp_delete_post( $this->pages['football'], true );
        wp_set_current_user( $this->users['fb'] );
        $this->assertFalse( current_user_can( 'edit_post', $this->pages['football_training'] ) );
    }

    public function test_role_lifecycle_preserves_deactivation_and_repairs_upgrade(): void {
        $option = get_option( 'jp_permissions_groups' );
        $this->assertNotFalse( get_role( 'jp_branch_editor' ) );
        deactivate_plugins( plugin_basename( \JayPee\Permissions\PLUGIN_FILE ) );
        $this->assertNotFalse( get_role( 'jp_branch_editor' ) );
        $this->assertSame( $option, get_option( 'jp_permissions_groups' ) );
        remove_role( 'jp_branch_editor' );
        \JayPee\Permissions\Lifecycle::maybe_upgrade();
        $this->assertNotFalse( get_role( 'jp_branch_editor' ) );
    }

    public function test_uninstall_cleans_configuration_role_and_branch_editor_accounts(): void {
        \JayPee\Permissions\Lifecycle::uninstall();
        $this->assertFalse( get_option( 'jp_permissions_groups', false ) );
        $this->assertNull( get_role( 'jp_branch_editor' ) );
        $this->assertSame( 'subscriber', get_userdata( $this->users['fb'] )->roles[0] );
    }
}
