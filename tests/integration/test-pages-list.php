<?php

declare( strict_types = 1 );

final class Pages_List_Integration_Test extends WP_UnitTestCase {
    use Branch_Fixtures;

    private int $foreign_draft;

    public function test_restricted_and_unrestricted_users_see_expected_pages_and_pagination(): void {
        $this->create_foreign_draft();
        wp_set_current_user( $this->users['fb'] );
        $football = $this->pages_list_query( 2 );
        $this->assertSame( 5, $football->found_posts );
        $this->assertSame( 2, $football->post_count );
        $this->assertSame( 3, $football->max_num_pages );
        $authorized_football = [ $this->pages['football'], $this->pages['teams'], $this->pages['football_training'], $this->pages['youth'], $this->pages['adults'] ];
        $this->assertSame( [], array_diff( $this->page_ids( $football ), $authorized_football ) );
        $page_two = $this->pages_list_query( 2, 2 );
        $this->assertSame( 5, $page_two->found_posts );
        $this->assertSame( 2, $page_two->post_count );
        $this->assertSame( [], array_diff( $this->page_ids( $page_two ), $authorized_football ) );
        $all_football = $this->pages_list_query( 100 );
        $this->assertEqualsCanonicalizing( $authorized_football, $this->page_ids( $all_football ) );

        wp_set_current_user( $this->users['multi'] );
        $this->assertEqualsCanonicalizing(
            [ $this->pages['tennis'], $this->pages['courts'], $this->pages['tennis_training'], $this->pages['swimming'], $this->pages['courses'] ],
            $this->page_ids( $this->pages_list_query( 100 ) )
        );
        wp_set_current_user( $this->users['staff'] );
        $staff_ids = $this->page_ids( $this->pages_list_query( 100 ) );
        $this->assertContains( $this->pages['home'], $staff_ids );
        $this->assertContains( $this->foreign_draft, $staff_ids );
        $this->assertCount( 13, $staff_ids );
    }

    public function test_restricted_user_with_no_existing_roots_sees_no_pages(): void {
        update_option( 'jp_permissions_groups', [
            'schema' => 1,
            'next_id' => 2,
            'groups' => [
                1 => [ 'name' => 'Missing Branch', 'roots' => [ 987654321 ], 'members' => [ $this->users['fb'] ] ],
            ],
        ] );
        wp_set_current_user( $this->users['fb'] );
        $query = $this->pages_list_query( 100 );
        $this->assertSame( 0, $query->found_posts );
        $this->assertSame( [], $this->page_ids( $query ) );
    }

    private function create_foreign_draft(): void {
        $this->foreign_draft = self::factory()->post->create( [
            'post_type' => 'page',
            'post_status' => 'draft',
            'post_title' => 'Hidden Draft',
            'post_parent' => 0,
        ] );
        $this->pages['foreign_draft'] = $this->foreign_draft;
    }

    private function page_ids( WP_Query $query ): array {
        return array_map( static fn( WP_Post $page ): int => $page->ID, $query->posts );
    }

    private function pages_list_query( int $per_page, int $paged = 1 ): WP_Query {
        set_current_screen( 'edit-page' );
        $per_page_filter = static fn(): int => $per_page;
        add_filter( 'edit_page_per_page', $per_page_filter );
        $previous_paged = $_GET['paged'] ?? null;
        $_GET['paged'] = $paged;
        wp_edit_posts_query( [
            'post_type' => 'page',
            'orderby' => 'title',
        ] );
        remove_filter( 'edit_page_per_page', $per_page_filter );
        if ( null === $previous_paged ) {
            unset( $_GET['paged'] );
        } else {
            $_GET['paged'] = $previous_paged;
        }
        $query = $GLOBALS['wp_query'];
        set_current_screen( 'front' );
        return $query;
    }
}
