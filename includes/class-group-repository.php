<?php
/** Stores branch group configuration. */

declare( strict_types = 1 );

namespace JayPee\Permissions;

defined( 'ABSPATH' ) || exit;


final class GroupRepository {
    public const OPTION = 'jp_permissions_groups';
    public const SCHEMA = 1;

    public static function all(): array {
        $config = get_option( self::OPTION, [] );
        if ( ! is_array( $config ) || ! isset( $config['schema'], $config['groups'] ) || self::SCHEMA !== $config['schema'] || ! is_array( $config['groups'] ) ) {
            return [];
        }
        $groups = [];
        foreach ( $config['groups'] as $raw_id => $group ) {
            $id = absint( $raw_id );
            if ( 0 === $id || isset( $groups[ $id ] ) || ! is_array( $group ) || ! isset( $group['name'], $group['roots'], $group['members'] ) || ! is_string( $group['name'] ) || ! is_array( $group['roots'] ) || ! is_array( $group['members'] ) ) {
                continue;
            }
            $groups[ $id ] = [
                'name' => $group['name'],
                'roots' => self::ids( $group['roots'] ),
                'members' => self::ids( $group['members'] ),
            ];
        }
        return $groups;
    }

    public static function get( int $id ): ?array {
        $groups = self::all();
        return $groups[ $id ] ?? null;
    }

    public static function save( ?int $id, string $name, array $roots, array $members ): int|\WP_Error {
        $name = trim( $name );
        if ( '' === $name || mb_strlen( $name, 'UTF-8' ) > 100 ) {
            return new \WP_Error( 'jp_permissions_invalid_name', __( 'Enter a name of 1–100 characters.', 'jp-permissions' ) );
        }
        foreach ( self::all() as $other_id => $group ) {
            if ( $id !== $other_id && mb_strtolower( $name, 'UTF-8' ) === mb_strtolower( $group['name'], 'UTF-8' ) ) {
                return new \WP_Error( 'jp_permissions_duplicate_name', __( 'A branch group with that name already exists.', 'jp-permissions' ) );
            }
        }
        $groups = self::all();
        if ( null === $id ) {
            $id = self::next_id();
        } elseif ( ! isset( $groups[ $id ] ) ) {
            return new \WP_Error( 'jp_permissions_missing_group', __( 'This branch group no longer exists.', 'jp-permissions' ) );
        }
        $groups[ $id ] = [
            'name' => $name,
            'roots' => self::valid_roots( $roots ),
            'members' => self::valid_members( $members ),
        ];
        self::persist( $groups, $id + 1 );
        return $id;
    }

    public static function delete( int $id ): void {
        $groups = self::all();
        unset( $groups[ $id ] );
        self::persist( $groups );
    }

    public static function remove_root( int $page_id ): void {
        $groups = self::all();
        foreach ( $groups as &$group ) {
            $group['roots'] = array_values( array_diff( $group['roots'], [ $page_id ] ) );
        }
        unset( $group );
        self::persist( $groups );
    }

    public static function remove_member( int $user_id ): void {
        $groups = self::all();
        foreach ( $groups as &$group ) {
            $group['members'] = array_values( array_diff( $group['members'], [ $user_id ] ) );
        }
        unset( $group );
        self::persist( $groups );
    }

    private static function next_id(): int {
        $config = get_option( self::OPTION, [] );
        return max( 1, is_array( $config ) && isset( $config['next_id'] ) ? absint( $config['next_id'] ) : 1 );
    }

    private static function persist( array $groups, int $minimum_next_id = 1 ): void {
        update_option(
            self::OPTION,
            [
                'schema' => self::SCHEMA,
                'next_id' => max( self::next_id(), $minimum_next_id ),
                'groups' => $groups,
            ]
        );
    }

    public static function groups_for_user( int $user_id ): array {
        return array_filter( self::all(), static fn ( array $group ): bool => in_array( $user_id, $group['members'], true ) );
    }

    private static function ids( array $values ): array {

        $ids = [];
        foreach ( $values as $value ) {
            $id = absint( $value );
            if ( 0 !== $id && ! in_array( $id, $ids, true ) ) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    private static function valid_roots( array $values ): array {
        return array_values(
            array_filter(
                self::ids( $values ),
                static function ( int $page_id ): bool {
                    $page = get_post( $page_id );
                    return $page instanceof \WP_Post && BranchPolicy::is_valid_branch_page( $page );
                }
            )
        );
    }

    private static function valid_members( array $values ): array {
        return array_values( array_filter( self::ids( $values ), static fn( int $id ): bool => (bool) get_userdata( $id ) ) );
    }
}
