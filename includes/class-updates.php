<?php
/**
 * Wires up the Plugin Update Checker against https://github.com/jaypee-solutions/jp-permissions/, stores the
 * GitHub token used for private auto-updates, and provides a small "Updates"
 * admin page to manage and test that token.
 *
 * @package JayPee\Permissions
 */

declare( strict_types = 1 );

namespace JayPee\Permissions;

defined( 'ABSPATH' ) || exit;

final class Updates {

    private const GITHUB_TOKEN_OPTION = 'jp_permissions_github_token';

    private const PAGE_SLUG = 'jp-permissions-updates';

    /**
     * scripts/build-release.sh scopes the bundled dependencies under this
     * namespace so two JayPee plugins with different Plugin Update Checker
     * versions cannot collide on the same WordPress install. In development
     * the unscoped Composer install is used instead.
     */
    private const PUC_FACTORIES = [
        __NAMESPACE__ . '\\Vendor\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory',
        'YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory',
    ];

    public static function init(): void {
        self::boot_update_checker();

        if ( ! is_admin() ) {
            return;
        }

        add_action( 'admin_menu', [ __CLASS__, 'register_admin_menu' ] );

        // Replace the update checker's generic "Could not determine if updates
        // are available" line with what actually went wrong.
        add_filter( 'puc_manual_check_message-jp-permissions', [ __CLASS__, 'explain_check_failure' ], 10, 2 );
    }

    /**
     * @return class-string|null The available PucFactory, scoped or not.
     */
    private static function puc_factory(): ?string {
        foreach ( self::PUC_FACTORIES as $factory ) {
            if ( class_exists( $factory ) ) {
                return $factory;
            }
        }

        return null;
    }

    private static function boot_update_checker(): void {
        $factory = self::puc_factory();
        if ( null === $factory ) {
            return;
        }

        $checker = $factory::buildUpdateChecker( UPDATE_URL, PLUGIN_FILE, 'jp-permissions' );

        // A metadata-URL checker has no VCS API; only the repository-backed one has.
        if ( ! method_exists( $checker, 'getVcsApi' ) ) {
            return;
        }

        // The update checker defaults to "master"; JayPee repositories use "main".
        $checker->setBranch( 'main' );

        $api = $checker->getVcsApi();
        if ( null === $api ) {
            return;
        }

        // Releases ship the built ZIP as an asset, not the repository tarball.
        $api->enableReleaseAssets( '/^jp-permissions-v.*\.zip$/' );

        $token = self::get_github_token();
        if ( '' !== $token ) {
            $api->setAuthentication( $token );
            self::force_bearer_authentication();
        }
    }

    public static function register_admin_menu(): void {
        add_options_page(
            __( 'JP Permissions for WordPress Updates', 'jp-permissions' ),
            __( 'JP Permissions for WordPress Updates', 'jp-permissions' ),
            'manage_options',
            self::PAGE_SLUG,
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $nonce = isset( $_POST['jp_permissions_github_token_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['jp_permissions_github_token_nonce'] ) ) : '';
        $saved = false;
        $test  = null;
        if ( '' !== $nonce && wp_verify_nonce( $nonce, 'jp_permissions_save_github_token' ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified directly above.
            $testing = isset( $_POST['jp_permissions_test_token'] );
            $posted  = self::posted_token();

            // An empty field on "Save" keeps the stored token, so there is
            // nothing to confirm — saying "Saved." would claim otherwise.
            if ( ! $testing ) {
                $saved = self::save_github_token( $posted );
            }

            // Test what the user just pasted; fall back to the stored token so
            // the button also works on its own, without re-pasting.
            $test = self::test_token( '' !== $posted ? $posted : self::get_github_token() );
        }

        $has_token = '' !== self::get_github_token();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'JP Permissions for WordPress – Updates', 'jp-permissions' ); ?></h1>
            <?php if ( $saved ) : ?>
                <div class="notice notice-success"><p><?php esc_html_e( 'Saved.', 'jp-permissions' ); ?></p></div>
            <?php endif; ?>
            <?php if ( null !== $test ) : ?>
                <div class="notice <?php echo $test['ok'] ? 'notice-success' : 'notice-error'; ?>">
                    <p><?php echo esc_html( $test['message'] ); ?></p>
                </div>
            <?php endif; ?>
            <form method="post">
                <?php wp_nonce_field( 'jp_permissions_save_github_token', 'jp_permissions_github_token_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="jp_permissions_github_token"><?php esc_html_e( 'GitHub Token', 'jp-permissions' ); ?></label>
                        </th>
                        <td>
                            <input
                                type="password"
                                id="jp_permissions_github_token"
                                name="jp_permissions_github_token"
                                class="regular-text"
                                autocomplete="new-password"
                                <?php // Suppress browser/password-manager autofill: otherwise submitting *another* field on this page can overwrite the stored token with an unintentionally filled value (e.g. the WP-admin password). Readonly until focused, plus opt-out attributes recognised by 1Password, LastPass, and Bitwarden. ?>
                                readonly="readonly"
                                onfocus="this.removeAttribute('readonly');"
                                data-lpignore="true"
                                data-1p-ignore="true"
                                data-bwignore="true"
                                data-form-type="other"
                                placeholder="<?php echo esc_attr( $has_token ? __( 'Saved — overwrite to change', 'jp-permissions' ) : __( 'Paste token', 'jp-permissions' ) ); ?>"
                            />
                            <p class="description">
                                <?php esc_html_e( 'Read-only GitHub token used for private plugin auto-updates. Not shown again after saving.', 'jp-permissions' ); ?>
                                <br />
                                <?php esc_html_e( '"Test token" checks the token against the repository without saving it — a classic token needs the "repo" scope, a fine-grained one needs this repository with "Contents: Read-only".', 'jp-permissions' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <?php submit_button( __( 'Save', 'jp-permissions' ), 'primary', 'submit', false ); ?>
                    <button type="submit" name="jp_permissions_test_token" value="1" class="button">
                        <?php esc_html_e( 'Test token', 'jp-permissions' ); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Called only after render_page() has already verified the nonce.
     */
    private static function posted_token(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified by the caller (render_page); sanitize_text_field() below validates/sanitizes.
        return sanitize_text_field( trim( (string) wp_unslash( $_POST['jp_permissions_github_token'] ?? '' ) ) );
    }

    /**
     * @return bool Whether a token was actually written.
     */
    private static function save_github_token( string $token ): bool {
        if ( '' === $token ) {
            return false; // Empty field leaves an already-saved token untouched.
        }

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- intentional: token stored encoded so it is not plaintext-readable in the DB.
        update_option( self::GITHUB_TOKEN_OPTION, base64_encode( $token ), false );

        return true;
    }

    public static function get_github_token(): string {
        $stored = (string) get_option( self::GITHUB_TOKEN_OPTION, '' );
        if ( '' === $stored ) {
            return '';
        }

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- intentional: decoding the token stored encoded above.
        $decoded = base64_decode( $stored, true );
        return false !== $decoded ? $decoded : '';
    }

    /**
     * Plugin Update Checker 5.x authenticates with HTTP Basic auth. GitHub
     * documents "Authorization: Bearer" for both classic and fine-grained
     * tokens, and it is what self::test_token() sends — so the update check and
     * the "Test token" button cannot disagree about whether a token works.
     *
     * Covers the API requests and the release asset download, both of which go
     * through wp_remote_get() against the repository API.
     */
    public static function force_bearer_authentication(): void {
        add_filter(
            'http_request_args',
            static function ( $args, $url = '' ) {
                if ( ! is_array( $args ) || 0 !== strpos( (string) $url, self::api_url() ) ) {
                    return $args;
                }

                $token = self::get_github_token();
                if ( '' === $token ) {
                    return $args;
                }

                $headers                  = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : [];
                $headers['Authorization'] = 'Bearer ' . $token;
                $args['headers']          = $headers;

                return $args;
            },
            // After Plugin Update Checker has added its own Authorization header.
            11,
            2
        );
    }

    private static function api_url(): string {
        return 'https://api.github.com/repos/' . GITHUB_REPO . '/';
    }

    /**
     * Asks GitHub whether the token can actually see the repository, so a
     * failing update check can name the reason instead of three raw 404s.
     *
     * @return array{ok:bool,message:string}
     */
    public static function test_token( string $token ): array {
        if ( '' === $token ) {
            return [
                'ok'      => false,
                'message' => __( 'No GitHub token is saved. Paste one into the field above, then save or test it.', 'jp-permissions' ),
            ];
        }

        // Ask about the repository itself, not about a release: /releases/latest
        // answers 404 both when the token has no access and when the repository
        // simply has no releases yet, which are very different problems.
        $response = self::api_request( '', $token );

        if ( is_wp_error( $response ) ) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    /* translators: %s: error message from the HTTP request. */
                    __( 'Could not reach api.github.com: %s', 'jp-permissions' ),
                    $response->get_error_message()
                ),
            ];
        }

        $code = (int) wp_remote_retrieve_response_code( $response );

        if ( 200 === $code ) {
            return [
                'ok'      => true,
                'message' => self::describe_latest_release( $token ),
            ];
        }

        if ( 401 === $code ) {
            return [
                'ok'      => false,
                'message' => __( 'GitHub rejected the token (401): it is invalid, expired, or was revoked. Create a new token and save it here.', 'jp-permissions' ),
            ];
        }

        if ( 403 === $code ) {
            // WordPress hands back an array when a header appears more than once.
            $remaining = wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' );
            $remaining = is_array( $remaining ) ? (string) reset( $remaining ) : (string) $remaining;

            return [
                'ok'      => false,
                'message' => '0' === $remaining
                    ? __( 'GitHub rate limit reached (403). Try again later; the token itself may be fine.', 'jp-permissions' )
                    : __( 'GitHub refused the request (403). The token exists but is not allowed to read this repository.', 'jp-permissions' ),
            ];
        }

        if ( 404 === $code ) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    /* translators: %s: repository in owner/name form. */
                    __( 'The token cannot see %s (404). GitHub returns this for private repositories when the token has no access: a classic token needs the full "repo" scope, a fine-grained token needs this repository selected with "Contents: Read-only".', 'jp-permissions' ),
                    GITHUB_REPO
                ),
            ];
        }

        return [
            'ok'      => false,
            'message' => sprintf(
                /* translators: %d: HTTP status code. */
                __( 'Unexpected answer from GitHub (HTTP %d).', 'jp-permissions' ),
                $code
            ),
        ];
    }

    /**
     * Only called once the token is known to reach the repository, so a 404 here
     * means "no releases yet" rather than a permission problem.
     */
    private static function describe_latest_release( string $token ): string {
        $response = self::api_request( 'releases/latest', $token );
        $tag      = '';

        if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
            $release = json_decode( (string) wp_remote_retrieve_body( $response ) );
            $tag     = is_object( $release ) && isset( $release->tag_name ) ? (string) $release->tag_name : '';
        }

        if ( '' === $tag ) {
            return sprintf(
                /* translators: 1: repository, 2: installed plugin version. */
                __( 'The token works and can read %1$s, but the repository has no release to update to yet. This site runs %2$s.', 'jp-permissions' ),
                GITHUB_REPO,
                'v' . VERSION
            );
        }

        return sprintf(
            /* translators: 1: repository, 2: latest release tag on GitHub, 3: installed plugin version. */
            __( 'The token works. %1$s reports %2$s as the latest release; this site runs %3$s.', 'jp-permissions' ),
            GITHUB_REPO,
            $tag,
            'v' . VERSION
        );
    }

    /**
     * @param string $path Appended to the repository API URL; empty asks about
     *                     the repository itself.
     * @return array<string,mixed>|\WP_Error
     */
    private static function api_request( string $path, string $token ) {
        // The repository endpoint answers 404 when called with a trailing slash.
        $url = '' === $path ? rtrim( self::api_url(), '/' ) : self::api_url() . $path;

        return wp_remote_get(
            $url,
            [
                'timeout' => 15,
                'headers' => [
                    'Accept'               => 'application/vnd.github+json',
                    'Authorization'        => 'Bearer ' . $token,
                    'X-GitHub-Api-Version' => '2022-11-28',
                ],
            ]
        );
    }

    /**
     * Filters the update checker's manual-check notice: its generic wording plus
     * a dump of raw API errors says nothing about the actual cause, which is
     * almost always the token.
     *
     * @param string $message Already escaped; the filter accepts HTML.
     * @param string $status  One of "no_update", "update_available", "error".
     */
    public static function explain_check_failure( $message, $status ): string {
        if ( 'error' !== $status ) {
            return (string) $message;
        }

        $test = self::test_token( self::get_github_token() );

        return (string) $message . ' ' . esc_html( $test['message'] ) . ' ' . sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
            esc_html__( 'Open the Updates settings', 'jp-permissions' )
        );
    }
}
