<?php

declare( strict_types = 1 );

final class User_Profile_Integration_Test extends WP_UnitTestCase {
    use Branch_Fixtures;

    public function test_admin_sees_a_members_groups_and_branch_roots(): void {
        wp_set_current_user( $this->users['admin'] );
        $user = get_userdata( $this->users['multi'] );
        ob_start();
        do_action( 'edit_user_profile', $user );
        $html = ob_get_clean();

        $this->assertStringContainsString( 'Tennis Editors', $html );
        $this->assertStringContainsString( 'Tennis', $html );
        $this->assertStringContainsString( 'Swimming Editors', $html );
        $this->assertStringContainsString( 'Swimming', $html );
        $this->assertStringNotContainsString( '<form', $html );
        $this->assertStringNotContainsString( '<input', $html );
    }

    public function test_member_sees_own_group_and_root(): void {
        wp_set_current_user( $this->users['fb'] );
        $user = get_userdata( $this->users['fb'] );
        ob_start();
        do_action( 'show_user_profile', $user );
        $html = ob_get_clean();

        $this->assertStringContainsString( 'Football Editors', $html );
        $this->assertStringContainsString( 'Football', $html );
    }

    public function test_nonmember_sees_no_profile_section(): void {
        wp_set_current_user( $this->users['admin'] );
        ob_start();
        do_action( 'edit_user_profile', get_userdata( $this->users['orphan'] ) );
        $html = ob_get_clean();
        $this->assertSame( '', $html );
    }

    public function test_unrestricted_member_is_marked_unrestricted(): void {
        wp_set_current_user( $this->users['admin'] );
        ob_start();
        do_action( 'edit_user_profile', get_userdata( $this->users['fb_staff'] ) );
        $html = ob_get_clean();

        $this->assertStringContainsString( 'Football Editors', $html );
        $this->assertStringContainsString( 'unrestricted', $html );
    }

    public function test_profile_owner_cannot_view_another_users_section(): void {
        wp_set_current_user( $this->users['fb'] );
        ob_start();
        do_action( 'edit_user_profile', get_userdata( $this->users['multi'] ) );
        $html = ob_get_clean();
        $this->assertSame( '', $html );
    }
}
