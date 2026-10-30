<?php
/** Restricts page editor controls to branch policy. */

declare( strict_types = 1 );

namespace JayPee\Permissions;

defined( 'ABSPATH' ) || exit;

final class Editor {
    public static function init(): void {
        add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue' ] );
    }

    public static function enqueue(): void {
        global $post;
        if ( ! BranchPolicy::is_restricted_context() || ! $post instanceof \WP_Post || 'page' !== $post->post_type ) {
            return;
        }
        wp_enqueue_script(
            'jp-permissions-editor',
            plugins_url( 'assets/js/editor.js', PLUGIN_FILE ),
            [ 'wp-data', 'wp-dom-ready', 'wp-editor' ],
            VERSION,
            true
        );
    }
}
