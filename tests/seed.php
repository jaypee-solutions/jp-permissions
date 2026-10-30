<?php
/**
 * Test seed: creates the fixtures the E2E suite expects.
 *
 * Call: npm run env:seed  (wp eval-file tests/seed.php)
 *
 * No declare(strict_types=1): wp eval-file does not evaluate the contents as the
 * first statement of a script, which would be a fatal error.
 *
 * @package JayPee\Permissions
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    fwrite( STDERR, "Only callable via WP-CLI.\n" );
    exit( 1 );
}

// Pretty permalinks — without them every fixture URL 404s.
global $wp_rewrite;
update_option( 'permalink_structure', '/%postname%/' );
$wp_rewrite->init();
$wp_rewrite->flush_rules();
require_once ABSPATH . 'wp-admin/includes/misc.php';
save_mod_rewrite_rules();

// Keep this fixture seed idempotent: the runner invokes it before every E2E run.
function jp_e2e_page( string $slug, string $title, int $parent_id = 0 ): int {
    $matches = get_posts( [
        'post_type' => 'page',
        'post_status' => 'any',
        'title' => $title,
        'numberposts' => -1,
    ] );
    $existing = null;
    foreach ( $matches as $match ) {
        if ( $slug === $match->post_name && $parent_id === (int) $match->post_parent ) {
            $existing = $match;
        } elseif ( $slug === $match->post_name || str_starts_with( $match->post_name, $slug . '-' ) ) {
            wp_delete_post( $match->ID, true );
        }
    }
    $page = [
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => $slug,
        'post_parent' => $parent_id,
    ];
    if ( $existing instanceof WP_Post ) {
        $page['ID'] = $existing->ID;
        return (int) wp_update_post( $page, true );
    }
    return (int) wp_insert_post( $page, true );
}

function jp_e2e_user( string $login, string $role ): int {
    $user = get_user_by( 'login', $login );
    if ( ! $user ) {
        $id = wp_create_user( $login, 'password', $login . '@example.test' );
        if ( is_wp_error( $id ) ) {
            WP_CLI::error( $id->get_error_message() );
        }
        $user = get_user_by( 'id', $id );
    }
    wp_set_password( 'password', $user->ID );
    $user->set_role( $role );
    return (int) $user->ID;
}

$pages = [];
$tree = [
    'home' => [ 'Home', '' ], 'membership' => [ 'Membership', '' ],
    'football' => [ 'Football', '' ], 'teams' => [ 'Teams', 'football' ],
    'youth' => [ 'Youth', 'teams' ], 'adults' => [ 'Adults', 'teams' ],
    'football-training' => [ 'Training', 'football' ], 'tennis' => [ 'Tennis', '' ],
    'courts' => [ 'Courts', 'tennis' ], 'tennis-training' => [ 'Training', 'tennis' ],
    'swimming' => [ 'Swimming', '' ], 'courses' => [ 'Courses', 'swimming' ],
];
foreach ( $tree as $slug => $entry ) {
    $pages[ $slug ] = jp_e2e_page( $slug, $entry[0], '' === $entry[1] ? 0 : $pages[ $entry[1] ] );
}

$users = [
    'admin' => jp_e2e_user( 'admin', 'administrator' ),
    'staff' => jp_e2e_user( 'staff', 'editor' ),
    'fb' => jp_e2e_user( 'fb', 'jp_branch_editor' ),
    'multi' => jp_e2e_user( 'multi', 'jp_branch_editor' ),
    'youth' => jp_e2e_user( 'youth', 'jp_branch_editor' ),
    'orphan' => jp_e2e_user( 'orphan', 'jp_branch_editor' ),
    'fb_staff' => jp_e2e_user( 'fb_staff', 'editor' ),
    'sub' => jp_e2e_user( 'sub', 'subscriber' ),
];
// Fresh users otherwise get the welcome guide and the starter-pattern chooser, whose modals
// cover the editor at unpredictable times (the chooser opens once patterns finish loading).
global $wpdb;
foreach ( $users as $user_id ) {
    update_user_meta( $user_id, $wpdb->get_blog_prefix() . 'persisted_preferences', [
        'core/edit-post' => [ 'welcomeGuide' => false ],
        'core'           => [ 'enableChoosePatternModal' => false ],
        '_modified'      => gmdate( 'c' ),
    ] );
}
// Fixtures have no pages below Football → Training; everything there was created by earlier E2E runs.
foreach ( get_posts( [ 'post_type' => 'page', 'post_status' => array_keys( get_post_stati() ), 'post_parent' => $pages['football-training'], 'numberposts' => -1, 'fields' => 'ids' ] ) as $post_id ) {
    wp_delete_post( $post_id, true );
}
foreach ( get_posts( [ 'post_type' => 'attachment', 'post_status' => 'inherit', 'author' => $users['fb'], 'title' => 'branch-image', 'numberposts' => -1 ] ) as $attachment ) {
    wp_delete_attachment( $attachment->ID, true );
}
update_option( 'jp_permissions_groups', [
    'schema' => 1,
    'next_id' => 5,
    'groups' => [
        1 => [ 'name' => 'Football Editors', 'roots' => [ $pages['football'] ], 'members' => [ $users['fb'], $users['fb_staff'] ] ],
        2 => [ 'name' => 'Tennis Editors', 'roots' => [ $pages['tennis'] ], 'members' => [ $users['multi'] ] ],
        3 => [ 'name' => 'Swimming Editors', 'roots' => [ $pages['swimming'] ], 'members' => [ $users['multi'] ] ],
        4 => [ 'name' => 'Youth Coaches', 'roots' => [ $pages['youth'] ], 'members' => [ $users['youth'] ] ],
    ],
] );


WP_CLI::success( 'Seed complete.' );
