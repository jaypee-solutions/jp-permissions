<?php
/** Shared Page Branches integration fixtures. */

declare( strict_types = 1 );

trait Branch_Fixtures {
    protected array $pages = [];
    protected array $users = [];

    public function set_up(): void {
        parent::set_up();
        $this->create_pages();
        $this->create_users();
        $this->write_group_configuration();
    }

    public function tear_down(): void {
        wp_set_current_user( $this->users['admin'] );
        foreach ( $this->users as $user_id ) {
            wp_delete_user( $user_id );
        }
        foreach ( $this->pages as $page_id ) {
            wp_delete_post( $page_id, true );
        }
        delete_option( 'jp_permissions_groups' );
        parent::tear_down();
    }

    protected function create_pages(): void {
        $tree = [
            'home' => [ 'Home' ], 'membership' => [ 'Membership' ],
            'football' => [ 'Football' ], 'teams' => [ 'Teams', 'football' ],
            'youth' => [ 'Youth', 'teams' ], 'adults' => [ 'Adults', 'teams' ],
            'football_training' => [ 'Training', 'football' ], 'tennis' => [ 'Tennis' ],
            'courts' => [ 'Courts', 'tennis' ], 'tennis_training' => [ 'Training', 'tennis' ],
            'swimming' => [ 'Swimming' ], 'courses' => [ 'Courses', 'swimming' ],
        ];
        foreach ( $tree as $key => $entry ) {
            $this->pages[ $key ] = self::factory()->post->create( [
                'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $entry[0],
                'post_parent' => isset( $entry[1] ) ? $this->pages[ $entry[1] ] : 0,
            ] );
        }
    }

    /** Assign one or more roles; use jp_branch_editor in tickets that register it. */
    protected function create_fixture_user( string $key, array $roles ): int {
        $id = self::factory()->user->create( [ 'role' => reset( $roles ) ] );
        $user = new WP_User( $id );
        foreach ( array_slice( $roles, 1 ) as $role ) {
            $user->add_role( $role );
        }
        $this->users[ $key ] = $id;
        return $id;
    }

    protected function create_users(): void {
        $this->create_fixture_user( 'admin', [ 'administrator' ] );
        $this->create_fixture_user( 'staff', [ 'editor' ] );
        $this->create_fixture_user( 'fb', [ 'jp_branch_editor' ] );
        $this->create_fixture_user( 'multi', [ 'jp_branch_editor' ] );
        $this->create_fixture_user( 'youth', [ 'jp_branch_editor' ] );
        $this->create_fixture_user( 'orphan', [ 'jp_branch_editor' ] );
        $this->create_fixture_user( 'fb_staff', [ 'editor' ] );
        $this->create_fixture_user( 'sub', [ 'subscriber' ] );
    }

    protected function write_group_configuration(): void {
        update_option( 'jp_permissions_groups', [
            'schema' => 1, 'next_id' => 5,
            'groups' => [
                1 => [ 'name' => 'Football Editors', 'roots' => [ $this->pages['football'] ], 'members' => [ $this->users['fb'], $this->users['fb_staff'] ] ],
                2 => [ 'name' => 'Tennis Editors', 'roots' => [ $this->pages['tennis'] ], 'members' => [ $this->users['multi'] ] ],
                3 => [ 'name' => 'Swimming Editors', 'roots' => [ $this->pages['swimming'] ], 'members' => [ $this->users['multi'] ] ],
                4 => [ 'name' => 'Youth Coaches', 'roots' => [ $this->pages['youth'] ], 'members' => [ $this->users['youth'] ] ],
            ],
        ] );
    }
}
