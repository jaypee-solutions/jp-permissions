<?php
/** Shared Page Branches integration fixtures. */

declare( strict_types = 1 );

trait Branch_Fixtures {
    protected array $pages = array();
    protected array $users = array();

    protected function setUp(): void {
        parent::setUp();
        $this->create_pages();
        $this->create_users();
        $this->write_group_configuration();
    }

    protected function tearDown(): void {
        foreach ( $this->users as $user_id ) {
            wp_delete_user( $user_id );
        }
        foreach ( $this->pages as $page_id ) {
            wp_delete_post( $page_id, true );
        }
        delete_option( 'jp_permissions_groups' );
        parent::tearDown();
    }

    protected function create_pages(): void {
        $tree = array(
            'home' => array( 'Home' ), 'membership' => array( 'Membership' ),
            'football' => array( 'Football' ), 'teams' => array( 'Teams', 'football' ),
            'youth' => array( 'Youth', 'teams' ), 'adults' => array( 'Adults', 'teams' ),
            'football_training' => array( 'Training', 'football' ), 'tennis' => array( 'Tennis' ),
            'courts' => array( 'Courts', 'tennis' ), 'tennis_training' => array( 'Training', 'tennis' ),
            'swimming' => array( 'Swimming' ), 'courses' => array( 'Courses', 'swimming' ),
        );
        foreach ( $tree as $key => $entry ) {
            $this->pages[ $key ] = self::factory()->post->create( array(
                'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $entry[0],
                'post_parent' => isset( $entry[1] ) ? $this->pages[ $entry[1] ] : 0,
            ) );
        }
    }

    /** Assign one or more roles; use jp_branch_editor in tickets that register it. */
    protected function create_fixture_user( string $key, array $roles ): int {
        foreach ( $roles as &$role ) {
            if ( 'jp_branch_editor' === $role && ! get_role( $role ) ) {
                $role = 'subscriber';
            }
        }
        unset( $role );
        $id = self::factory()->user->create( array( 'role' => reset( $roles ) ) );
        $user = new WP_User( $id );
        foreach ( array_slice( $roles, 1 ) as $role ) {
            $user->add_role( $role );
        }
        $this->users[ $key ] = $id;
        return $id;
    }

    protected function create_users(): void {
        $this->create_fixture_user( 'admin', array( 'administrator' ) );
        $this->create_fixture_user( 'staff', array( 'editor' ) );
        $this->create_fixture_user( 'fb', array( 'jp_branch_editor' ) );
        $this->create_fixture_user( 'multi', array( 'jp_branch_editor' ) );
        $this->create_fixture_user( 'youth', array( 'jp_branch_editor' ) );
        $this->create_fixture_user( 'orphan', array( 'jp_branch_editor' ) );
        $this->create_fixture_user( 'fb_staff', array( 'editor' ) );
        $this->create_fixture_user( 'sub', array( 'subscriber' ) );
    }

    protected function write_group_configuration(): void {
        update_option( 'jp_permissions_groups', array(
            'schema' => 1, 'next_id' => 5,
            'groups' => array(
                1 => array( 'name' => 'Football Editors', 'roots' => array( $this->pages['football'] ), 'members' => array( $this->users['fb'], $this->users['fb_staff'] ) ),
                2 => array( 'name' => 'Tennis Editors', 'roots' => array( $this->pages['tennis'] ), 'members' => array( $this->users['multi'] ) ),
                3 => array( 'name' => 'Swimming Editors', 'roots' => array( $this->pages['swimming'] ), 'members' => array( $this->users['multi'] ) ),
                4 => array( 'name' => 'Youth Coaches', 'roots' => array( $this->pages['youth'] ), 'members' => array( $this->users['youth'] ) ),
            ),
        ) );
    }
}
