<?php
/** Provides the branch group administration screen. */

declare( strict_types = 1 );

namespace JayPee\Permissions;

final class GroupsPage {
    private const SLUG = 'jp-permissions-branches';
    private static array $errors = array();
    private static ?array $submitted = null;

    public static function init(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
        add_action( 'admin_post_jp_permissions_save_group', array( self::class, 'save' ) );
        add_action( 'admin_post_jp_permissions_delete_group', array( self::class, 'delete' ) );
    }

    public static function menu(): void {
        add_users_page( __( 'Page Branches', 'jp-permissions' ), __( 'Page Branches', 'jp-permissions' ), 'manage_options', self::SLUG, array( self::class, 'render' ) );
        add_action( 'load-users_page_' . self::SLUG, array( self::class, 'handle' ) );
    }

    public static function handle(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage branch groups.', 'jp-permissions' ), '', array( 'response' => 403 ) );
        }
        if ( isset( $_POST['jp_permissions_save_group'] ) ) {
            check_admin_referer( 'jp_permissions_save_group' );
            self::save();
        }
        if ( isset( $_GET['delete'] ) && isset( $_POST['jp_permissions_delete_group'] ) ) {
            $id = absint( $_POST['group_id'] ?? 0 );
            check_admin_referer( 'jp_permissions_delete_group_' . $id );
            GroupRepository::delete( $id );
            wp_safe_redirect( add_query_arg( 'deleted', '1', self::url() ) );
            exit;
        }
    }

    public static function save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage branch groups.', 'jp-permissions' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'jp_permissions_save_group' );
        $raw_name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $name = trim( $raw_name );
        $id = isset( $_POST['group_id'] ) && absint( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : null;
        $roots = array();
        $current = $id ? GroupRepository::get( $id ) : null;
        if ( $current ) {
            foreach ( $current['roots'] as $root ) {
                if ( empty( $_POST['remove_root'][ $root ] ) ) {
                    $roots[] = $root;
                }
            }
        }
        $new_root = absint( $_POST['add_root'] ?? 0 );
        if ( $new_root ) {
            $roots[] = $new_root;
        }
        $members = array_map( 'absint', (array) ( $_POST['members'] ?? array() ) );
        $members = array_values( array_filter( array_unique( $members ), static fn( int $user_id ): bool => (bool) get_userdata( $user_id ) ) );
        self::$submitted = array( 'name' => $raw_name, 'roots' => $roots, 'members' => $members, 'id' => $id );
        $validation = self::validate_name( $name, $id );
        if ( is_wp_error( $validation ) ) {
            self::$errors = array( $validation->get_error_code() );
            return;
        }
        $roots = array_values( array_filter( array_unique( $roots ), static function ( int $root ): bool {
            $page = get_post( $root );
            return $page && 'page' === $page->post_type && ! in_array( $page->post_status, array( 'auto-draft', 'trash' ), true );
        } ) );
        $result = GroupRepository::save( $id, $name, $roots, $members );
        if ( is_wp_error( $result ) ) {
            self::$errors = array( $result->get_error_code() );
            return;
        }
        wp_safe_redirect( add_query_arg( 'updated', '1', self::url() ) );
        exit;
    }

    private static function validate_name( string $name, ?int $id ): \WP_Error|bool {
        if ( '' === $name || strlen( $name ) > 100 ) {
            return new \WP_Error( 'jp_permissions_invalid_name' );
        }
        foreach ( GroupRepository::all() as $other_id => $group ) {
            if ( $id !== $other_id && 0 === strcasecmp( $name, $group['name'] ) ) {
                return new \WP_Error( 'jp_permissions_duplicate_name' );
            }
        }
        return true;
    }

    public static function delete(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage branch groups.', 'jp-permissions' ), '', array( 'response' => 403 ) );
        }
        $id = absint( $_POST['group_id'] ?? 0 );
        check_admin_referer( 'jp_permissions_delete_group_' . $id );
        GroupRepository::delete( $id );
        wp_safe_redirect( add_query_arg( 'deleted', '1', self::url() ) );
        exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage branch groups.', 'jp-permissions' ), '', array( 'response' => 403 ) );
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These query parameters select read-only views; all mutations use action nonces.
        $id = absint( $_GET['edit'] ?? $_GET['delete'] ?? 0 );
        $group = $id ? GroupRepository::get( $id ) : null;
        $submitted = self::$submitted;
        $name = $submitted['name'] ?? ( $group['name'] ?? '' );
        $roots = $submitted['roots'] ?? ( $group['roots'] ?? array() );
        $members = $submitted['members'] ?? ( $group['members'] ?? array() );
        echo '<div class="wrap"><h1>' . esc_html__( 'Page Branches', 'jp-permissions' ) . '</h1>';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These query parameters only indicate a completed redirect.
        if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Branch group saved.', 'jp-permissions' ) . '</p></div>'; }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These query parameters only indicate a completed redirect.
        if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Branch group deleted.', 'jp-permissions' ) . '</p></div>'; }
        foreach ( self::$errors as $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html( 'jp_permissions_invalid_name' === $error ? __( 'Enter a name of 1–100 characters.', 'jp-permissions' ) : __( 'A branch group with that name already exists.', 'jp-permissions' ) ) . '</p></div>'; }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This query parameter selects the confirmation screen; deletion requires a nonce.
        if ( isset( $_GET['delete'] ) && $group ) {
            echo '<h2>' . esc_html__( 'Delete branch group?', 'jp-permissions' ) . '</h2><p>' . esc_html( $group['name'] ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            echo '<input type="hidden" name="action" value="jp_permissions_delete_group"><input type="hidden" name="group_id" value="' . esc_attr( (string) $id ) . '">';
            wp_nonce_field( 'jp_permissions_delete_group_' . $id );
            submit_button( __( 'Delete group', 'jp-permissions' ), 'delete', 'jp_permissions_delete_group' );
            echo ' <a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Cancel', 'jp-permissions' ) . '</a></form></div>';
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These query parameters select read-only views; all mutations use action nonces.
        if ( isset( $_GET['edit'] ) || isset( $_GET['add'] ) || self::$submitted ) {
            self::edit_form( $id, $name, $roots, $members );
        } else {
            self::list_table();
        }
        echo '</div>';
    }

    private static function list_table(): void {
        echo '<p><a class="button button-primary" href="' . esc_url( add_query_arg( 'add', '1', self::url() ) ) . '">' . esc_html__( 'Add group', 'jp-permissions' ) . '</a></p><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Name', 'jp-permissions' ) . '</th><th>' . esc_html__( 'Branch roots', 'jp-permissions' ) . '</th><th>' . esc_html__( 'Members', 'jp-permissions' ) . '</th></tr></thead><tbody>';
        foreach ( GroupRepository::all() as $id => $group ) {
            echo '<tr><td><strong>' . esc_html( $group['name'] ) . '</strong><div class="row-actions"><a href="' . esc_url( add_query_arg( 'edit', (string) $id, self::url() ) ) . '">' . esc_html__( 'Edit', 'jp-permissions' ) . '</a> | <a href="' . esc_url( add_query_arg( 'delete', (string) $id, self::url() ) ) . '">' . esc_html__( 'Delete', 'jp-permissions' ) . '</a></div></td><td>';
            foreach ( $group['roots'] as $root ) {
				$page = get_post( $root );
				if ( $page ) {
					echo '<a href="' . esc_url( get_edit_post_link( $root ) ) . '">' . esc_html( get_page_uri( $root ) ) . '</a>' . ( 'publish' !== $page->post_status ? ' (' . esc_html( get_post_status_object( $page->post_status )->label ?? $page->post_status ) . ')' : '' ) . '<br>'; }
			}
            echo '</td><td>';
            foreach ( $group['members'] as $user_id ) {
				$user = get_userdata( $user_id );
				if ( $user ) {
					echo esc_html( $user->display_name . ' (' . $user->user_login . ')' );
					if ( user_can( $user, 'edit_others_pages' ) ) {
						echo ' — ' . esc_html__( 'unrestricted', 'jp-permissions' );
					} echo '<br>'; }
			}
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function edit_form( int $id, string $name, array $roots, array $members ): void {
        echo '<h2>' . esc_html( $id ? __( 'Edit branch group', 'jp-permissions' ) : __( 'Add branch group', 'jp-permissions' ) ) . '</h2><form method="post"><table class="form-table"><tr><th><label for="group-name">' . esc_html__( 'Name', 'jp-permissions' ) . '</label></th><td><input id="group-name" name="name" type="text" class="regular-text" value="' . esc_attr( $name ) . '" maxlength="100" required></td></tr><tr><th>' . esc_html__( 'Branch roots', 'jp-permissions' ) . '</th><td>';
        foreach ( $roots as $root ) {
			$page = get_post( $root );
			if ( $page ) {
				echo '<label><input type="checkbox" name="remove_root[' . esc_attr( (string) $root ) . ']" value="1"> ' . esc_html( get_page_uri( $root ) ) . ( 'publish' !== $page->post_status ? ' (' . esc_html( get_post_status_object( $page->post_status )->label ?? $page->post_status ) . ')' : '' ) . ' — ' . esc_html__( 'Remove', 'jp-permissions' ) . '</label><br>'; }
		}
        echo '<label for="add-root">' . esc_html__( 'Add root', 'jp-permissions' ) . '</label> ';
        wp_dropdown_pages( array( 'name' => 'add_root', 'id' => 'add-root', 'show_option_none' => esc_html__( '— None —', 'jp-permissions' ), 'option_none_value' => '0', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'sort_column' => 'menu_order,post_title', 'hierarchical' => true, 'selected' => 0 ) );
        echo '</td></tr><tr><th>' . esc_html__( 'Members', 'jp-permissions' ) . '</th><td>';
        $users = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC' ) );
        foreach ( $users as $user ) {
			$roles = array_map( static fn( string $role ): string => wp_roles()->roles[ $role ]['name'] ?? $role, $user->roles );
			echo '<label><input type="checkbox" name="members[]" value="' . esc_attr( (string) $user->ID ) . '" ' . checked( in_array( (int) $user->ID, $members, true ), true, false ) . '> ' . esc_html( $user->display_name . ' (' . $user->user_login . ') — ' . implode( ', ', $roles ) ) . '</label><br>'; }
        echo '</td></tr></table><input type="hidden" name="group_id" value="' . esc_attr( (string) $id ) . '">';
        wp_nonce_field( 'jp_permissions_save_group' );
        submit_button( __( 'Save group', 'jp-permissions' ), 'primary', 'jp_permissions_save_group' );
        echo '</form>';
    }

    private static function url(): string {
		return add_query_arg( 'page', self::SLUG, admin_url( 'users.php' ) ); }
}
