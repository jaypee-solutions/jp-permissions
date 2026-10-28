<?php
/**
 * Minimal namespace-local stubs for the handful of WordPress functions the
 * pure-logic methods under test call. PHP resolves unqualified calls inside
 * JayPee\Permissions to these before falling back to the global namespace.
 *
 * @package JayPee\Permissions
 */

declare( strict_types = 1 );

namespace JayPee\Permissions;

/**
 * @var array<string,mixed> Backing store for the get_option() stub.
 */
$GLOBALS['jp_permissions_test_options'] = [];

/**
 * @var array<string,mixed> Per-endpoint answers for the wp_remote_get() stub,
 *                          keyed by a substring of the requested URL; the first
 *                          match wins. Values are either a response array
 *                          ( 'response' => [ 'code' => int ], 'body', 'headers' )
 *                          or an object with get_error_message().
 */
$GLOBALS['jp_permissions_test_http_responses'] = [];

/**
 * @var mixed Fallback answer when no per-endpoint entry matches.
 */
$GLOBALS['jp_permissions_test_http_response'] = [ 'response' => [ 'code' => 200 ] ];

/**
 * @var array<int,array{url:string,args:array<string,mixed>}> Every request the
 *                          wp_remote_get() stub has seen, in order.
 */
$GLOBALS['jp_permissions_test_http_requests'] = [];

// Constants the plugin file defines; the tests do not load it (it bootstraps WP).
const VERSION     = '0.0.0-test';
const PLUGIN_FILE = __FILE__;
const GITHUB_REPO = 'jaypee-solutions/jp-permissions';
const UPDATE_URL  = 'https://github.com/jaypee-solutions/jp-permissions/';

/**
 * @param mixed $default_value
 * @return mixed
 */
function get_option( string $option, $default_value = false ) {
    return $GLOBALS['jp_permissions_test_options'][ $option ] ?? $default_value;
}

/**
 * @param mixed $value
 * @param mixed $autoload
 */
function update_option( string $option, $value, $autoload = null ): bool {
    $GLOBALS['jp_permissions_test_options'][ $option ] = $value;
    return true;
}

/**
 * @param array<string,mixed> $args
 * @return mixed
 */
function wp_remote_get( string $url, array $args = [] ) {
    $GLOBALS['jp_permissions_test_http_requests'][] = [
        'url'  => $url,
        'args' => $args,
    ];

    foreach ( $GLOBALS['jp_permissions_test_http_responses'] as $needle => $response ) {
        if ( false !== strpos( $url, (string) $needle ) ) {
            return $response;
        }
    }

    return $GLOBALS['jp_permissions_test_http_response'];
}

/**
 * @param mixed $thing
 */
function is_wp_error( $thing ): bool {
    return is_object( $thing ) && method_exists( $thing, 'get_error_message' );
}

/**
 * @param mixed $response
 */
function wp_remote_retrieve_response_code( $response ): int {
    return is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0;
}

/**
 * @param mixed $response
 */
function wp_remote_retrieve_body( $response ): string {
    return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : '';
}

/**
 * @param mixed $response
 * @return mixed
 */
function wp_remote_retrieve_header( $response, string $header ) {
    return is_array( $response ) ? ( $response['headers'][ $header ] ?? '' ) : '';
}

function __( string $text, string $domain = 'default' ): string { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionDoubleUnderscore
    return $text;
}

function esc_html( string $text ): string {
    // Explicit charset: WordPress escapes as UTF-8 regardless of default_charset.
    return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html__( string $text, string $domain = 'default' ): string {
    return esc_html( $text );
}

function esc_url( string $url ): string {
    return $url;
}

function admin_url( string $path = '' ): string {
    return 'https://example.test/wp-admin/' . $path;
}

/**
 * @param mixed $callback
 */
function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    return true;
}

/**
 * @param mixed $callback
 */
function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    return true;
}

function is_admin(): bool {
    return false;
}
