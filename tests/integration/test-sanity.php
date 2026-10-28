<?php

declare( strict_types = 1 );


final class Integration_Sanity_Test extends WP_UnitTestCase {
    use Branch_Fixtures;

    public function test_plugin_and_shared_fixtures_are_available(): void {
        $this->assertTrue( defined( 'JayPee\\Permissions\\VERSION' ) );
        $this->assertCount( 12, $this->pages );
        $this->assertCount( 8, $this->users );
        $this->assertSame( 'page', get_post_type( $this->pages['youth'] ) );
        $this->assertSame( $this->pages['teams'], (int) get_post_field( 'post_parent', $this->pages['youth'] ) );
        $this->assertSame( 4, count( get_option( 'jp_permissions_groups' )['groups'] ) );
    }
}
