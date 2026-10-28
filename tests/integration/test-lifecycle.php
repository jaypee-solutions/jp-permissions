<?php

declare( strict_types = 1 );

final class Lifecycle_Integration_Test extends WP_UnitTestCase {
    use Branch_Fixtures;

    public function test_deleted_pages_and_users_are_removed_from_all_group_assignments(): void {
        $page_id = $this->pages['football'];
        wp_delete_post( $page_id, true );
        $groups = get_option( 'jp_permissions_groups' )['groups'];
        foreach ( $groups as $group ) {
            $this->assertNotContains( $page_id, $group['roots'] );
        }
        $user_id = $this->users['fb'];
        wp_delete_user( $user_id );
        $groups = get_option( 'jp_permissions_groups' )['groups'];
        foreach ( $groups as $group ) {
            $this->assertNotContains( $user_id, $group['members'] );
        }
    }

    public function test_restricted_users_cannot_trash_or_delete_pages(): void {
        wp_set_current_user( $this->users['fb'] );
        $page_id = $this->pages['football'];
        $this->assertFalse( wp_trash_post( $page_id ) );
        $this->assertSame( 'publish', get_post_status( $page_id ) );
        $this->assertFalse( wp_delete_post( $page_id, true ) );
        $this->assertSame( 'publish', get_post_status( $page_id ) );
        $this->assertFalse( wp_untrash_post( $page_id ) );
        $this->assertSame( 'publish', get_post_status( $page_id ) );
    }

    public function test_trash_keeps_descendant_authorization_and_permanent_deletion_removes_it(): void {
        wp_set_current_user( $this->users['admin'] );
        wp_trash_post( $this->pages['football'] );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['football_training'] ) );
        wp_set_current_user( $this->users['admin'] );
        wp_delete_post( $this->pages['football'], true );
        wp_set_current_user( $this->users['fb'] );
        $this->assertFalse( current_user_can( 'edit_post', $this->pages['football_training'] ) );
    }

    public function test_auto_draft_cleanup_and_unrestricted_deletion_remain_available(): void {
        wp_set_current_user( $this->users['admin'] );
        $auto_draft = self::factory()->post->create( [ 'post_type' => 'page', 'post_status' => 'auto-draft' ] );
        wp_set_current_user( $this->users['fb'] );
        $this->assertNotFalse( wp_delete_post( $auto_draft, true ) );
        $this->assertNull( get_post( $auto_draft ) );
        foreach ( [ 'staff', 'admin' ] as $user ) {
            wp_set_current_user( $this->users[ $user ] );
            $page_id = self::factory()->post->create( [ 'post_type' => 'page', 'post_status' => 'publish' ] );
            $this->assertNotFalse( wp_trash_post( $page_id ) );
            $this->assertSame( 'trash', get_post_status( $page_id ) );
            $this->assertNotFalse( wp_untrash_post( $page_id ) );
            $this->assertSame( 'draft', get_post_status( $page_id ) );
            $this->assertNotFalse( wp_delete_post( $page_id, true ) );
        }
    }

    public function test_role_lifecycle_preserves_deactivation_and_repairs_upgrade(): void {
        $option = get_option( 'jp_permissions_groups' );
        $this->assertNotFalse( get_role( 'jp_branch_editor' ) );
        deactivate_plugins( plugin_basename( \JayPee\Permissions\PLUGIN_FILE ) );
        $this->assertNotFalse( get_role( 'jp_branch_editor' ) );
        $this->assertSame( $option, get_option( 'jp_permissions_groups' ) );
        delete_option( 'jp_permissions_groups' );
        remove_role( 'jp_branch_editor' );
        do_action( 'plugins_loaded' );
        $this->assertNotFalse( get_role( 'jp_branch_editor' ) );
    }

    public function test_uninstall_cleans_configuration_role_and_branch_editor_accounts(): void {
        uninstall_plugin( plugin_basename( \JayPee\Permissions\PLUGIN_FILE ) );
        $this->assertFalse( get_option( 'jp_permissions_groups', false ) );
        $this->assertNull( get_role( 'jp_branch_editor' ) );
        $this->assertSame( 'subscriber', get_userdata( $this->users['fb'] )->roles[0] );
    }
}
