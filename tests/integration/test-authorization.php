<?php

declare( strict_types = 1 );

final class Authorization_Integration_Test extends WP_UnitTestCase {
    use Branch_Fixtures;

    public function test_branch_editor_role_and_capability_matrix(): void {
        $this->assertNotFalse( get_role( 'jp_branch_editor' ) );
        $this->assertSame( array( 'read', 'upload_files' ), array_keys( get_role( 'jp_branch_editor' )->capabilities ) );
        foreach ( array( 'football_training', 'youth', 'tennis_training', 'courses' ) as $key ) {
            wp_set_current_user( $this->users['fb'] );
            $expected = in_array( $key, array( 'football_training', 'youth' ), true );
            $this->assertSame( $expected, current_user_can( 'edit_post', $this->pages[ $key ] ), $key );
            $this->assertSame( $expected, current_user_can( 'publish_post', $this->pages[ $key ] ), $key . ' publish' );
            $this->assertFalse( current_user_can( 'delete_post', $this->pages[ $key ] ), $key . ' delete' );
        }
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['football_training'] ) );
    }

    public function test_nested_additive_branches_and_unrestricted_members(): void {
        wp_set_current_user( $this->users['fb'] );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['youth'] ) );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['football_training'] ) );
        wp_set_current_user( $this->users['youth'] );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['youth'] ) );
        $this->assertFalse( current_user_can( 'edit_post', $this->pages['football_training'] ) );
        wp_set_current_user( $this->users['multi'] );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['courses'] ) );
        $this->assertFalse( current_user_can( 'edit_post', $this->pages['home'] ) );
        wp_set_current_user( $this->users['fb_staff'] );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['tennis'] ) );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['home'] ) );
        wp_set_current_user( $this->users['orphan'] );
        $this->assertFalse( current_user_can( 'edit_post', $this->pages['football'] ) );
        wp_set_current_user( $this->users['admin'] );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['home'] ) );
    }

    public function test_repository_normalizes_missing_and_corrupt_configuration(): void {
        delete_option( 'jp_permissions_groups' );
        $this->assertSame( array(), \JayPee\Permissions\GroupRepository::all() );
        update_option( 'jp_permissions_groups', 'corrupt' );
        $this->assertSame( array(), \JayPee\Permissions\GroupRepository::all() );
        wp_set_current_user( $this->users['fb'] );
        $this->assertFalse( current_user_can( 'edit_post', $this->pages['football'] ) );
        wp_set_current_user( $this->users['fb_staff'] );
        $this->assertTrue( current_user_can( 'edit_post', $this->pages['home'] ) );
    }
}
