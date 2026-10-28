<?php
/** Stores branch group configuration. */

declare( strict_types = 1 );

namespace JayPee\Permissions;

use WP_Error;

final class GroupRepository {
    public const OPTION = 'jp_permissions_groups';
    public const SCHEMA = 1;

    public static function all(): array {
        $config = get_option( self::OPTION, array() );
        if ( ! is_array( $config ) || ! isset( $config['schema'], $config['groups'] ) || self::SCHEMA !== $config['schema'] || ! is_array( $config['groups'] ) ) {
            return array();
        }
        $groups = array();
        foreach ( $config['groups'] as $raw_id => $group ) {
            $id = absint( $raw_id );
            if ( 0 === $id || isset( $groups[ $id ] ) || ! is_array( $group ) || ! isset( $group['name'], $group['roots'], $group['members'] ) || ! is_string( $group['name'] ) || ! is_array( $group['roots'] ) || ! is_array( $group['members'] ) ) {
                continue;
            }
            $groups[ $id ] = array(
                'name' => $group['name'],
                'roots' => self::ids( $group['roots'] ),
                'members' => self::ids( $group['members'] ),
            );
        }
        return $groups;
    }

    public static function get( int $id ): ?array {
        $groups = self::all();
        return $groups[ $id ] ?? null;
    }

    public static function save( ?int $id, string $name, array $roots, array $members ): int|\WP_Error {
        $name = trim( $name );
        if ( '' === $name || strlen( $name ) > 100 ) {
            return new \WP_Error( 'jp_permissions_invalid_name' );
        }
        foreach ( self::all() as $other_id => $group ) {
            if ( $id !== $other_id && 0 === strcasecmp( $name, $group['name'] ) ) {
                return new \WP_Error( 'jp_permissions_duplicate_name' );
            }
        }
        $config = get_option( self::OPTION, array() );
        $groups = self::all();
        if ( null === $id ) {
            $id = is_array( $config ) && isset( $config['next_id'] ) ? absint( $config['next_id'] ) : 1;
            $id = max( 1, $id );
        }
        $groups[ $id ] = array( 'name' => $name, 'roots' => self::valid_roots( $roots ), 'members' => self::valid_members( $members ) );
        update_option( self::OPTION, array(
            'schema' => self::SCHEMA,
            'next_id' => max( $id + 1, is_array( $config ) && isset( $config['next_id'] ) ? absint( $config['next_id'] ) : 1 ),
            'groups' => $groups,
        ) );
        return $id;
    }

    public static function delete( int $id ): void {
        $groups = self::all();
        unset( $groups[ $id ] );
        $config = get_option( self::OPTION, array() );
        update_option( self::OPTION, array( 'schema' => self::SCHEMA, 'next_id' => is_array( $config ) && isset( $config['next_id'] ) ? absint( $config['next_id'] ) : 1, 'groups' => $groups ) );
    }

    public static function remove_root( int $page_id ): void {
        $groups = self::all();
        foreach ( $groups as &$group ) {
            $group['roots'] = array_values( array_diff( $group['roots'], array( $page_id ) ) );
        }
        unset( $group );
        self::persist( $groups );
    }

    public static function remove_member( int $user_id ): void {
        $groups = self::all();
        foreach ( $groups as &$group ) {
            $group['members'] = array_values( array_diff( $group['members'], array( $user_id ) ) );
        }
        unset( $group );
        self::persist( $groups );
    }

    private static function persist( array $groups ): void {
        $config = get_option( self::OPTION, array() );
        $next_id = is_array( $config ) && isset( $config['next_id'] ) ? absint( $config['next_id'] ) : 1;
        update_option( self::OPTION, array( 'schema' => self::SCHEMA, 'next_id' => $next_id, 'groups' => $groups ) );
    }

    public static function groups_for_user( int $user_id ): array {
        return array_filter( self::all(), static fn ( array $group ): bool => in_array( $user_id, $group['members'], true ) );
    }

    private static function ids( array $values ): array {

        $ids = array();
        foreach ( $values as $value ) {
            $id = absint( $value );
            if ( 0 !== $id && ! in_array( $id, $ids, true ) ) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    private static function valid_roots( array $values ): array {
        return array_values( array_filter( self::ids( $values ), static function ( int $id ): bool {
            $page = get_post( $id );
            return $page && 'page' === $page->post_type && ! in_array( $page->post_status, array( 'auto-draft', 'trash' ), true );
        } ) );
    }

    private static function valid_members( array $values ): array {
        return array_values( array_filter( self::ids( $values ), static fn( int $id ): bool => (bool) get_userdata( $id ) ) );
    }
}
