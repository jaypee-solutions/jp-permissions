<?php
/**
 * PHPUnit tests for the GitHub token diagnostics behind the "Test token"
 * button on the Updates page.
 *
 * @package JayPee\Permissions
 */

declare( strict_types = 1 );

namespace JayPee\Permissions\Tests;

use JayPee\Permissions\Updates;
use PHPUnit\Framework\TestCase;

/**
 * Stands in for WP_Error; tests/stubs.php recognises it via get_error_message().
 */
final class FakeWpError {

    public function __construct( private string $message ) {}

    public function get_error_message(): string {
        return $this->message;
    }
}

/**
 * @covers \JayPee\Permissions\Updates
 */
final class UpdatesTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['jp_permissions_test_options']        = [];
        $GLOBALS['jp_permissions_test_http_response']  = [ 'response' => [ 'code' => 200 ] ];
        $GLOBALS['jp_permissions_test_http_responses'] = [];
        $GLOBALS['jp_permissions_test_http_requests']  = [];
    }

    public function test_reports_a_missing_token_without_calling_github(): void {
        $result = Updates::test_token( '' );

        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'No GitHub token is saved', $result['message'] );
        $this->assertSame( [], $GLOBALS['jp_permissions_test_http_requests'] );
    }

    public function test_names_the_token_as_the_cause_of_a_401(): void {
        $GLOBALS['jp_permissions_test_http_response'] = [ 'response' => [ 'code' => 401 ] ];

        $result = Updates::test_token( 'ghp_invalid' );

        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( '401', $result['message'] );
    }

    public function test_explains_that_a_404_means_no_access_to_the_repository(): void {
        $GLOBALS['jp_permissions_test_http_response'] = [ 'response' => [ 'code' => 404 ] ];

        $result = Updates::test_token( 'ghp_no_access' );

        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'jaypee-solutions/jp-permissions', $result['message'] );
    }

    public function test_distinguishes_a_rate_limit_from_a_permission_problem(): void {
        $GLOBALS['jp_permissions_test_http_response'] = [
            'response' => [ 'code' => 403 ],
            'headers'  => [ 'x-ratelimit-remaining' => '0' ],
        ];

        $result = Updates::test_token( 'ghp_rate_limited' );

        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'rate limit', $result['message'] );
    }

    public function test_reports_the_latest_release_when_the_token_works(): void {
        $GLOBALS['jp_permissions_test_http_responses'] = [
            'releases/latest' => [
                'response' => [ 'code' => 200 ],
                'body'     => '{"tag_name":"v9.9.9"}',
            ],
        ];

        $result = Updates::test_token( 'ghp_valid' );

        $this->assertTrue( $result['ok'] );
        $this->assertStringContainsString( 'v9.9.9', $result['message'] );
    }

    public function test_says_so_when_the_repository_has_no_release_yet(): void {
        $GLOBALS['jp_permissions_test_http_responses'] = [
            'releases/latest' => [ 'response' => [ 'code' => 404 ] ],
        ];

        $result = Updates::test_token( 'ghp_valid' );

        $this->assertTrue( $result['ok'] );
        $this->assertStringContainsString( 'no release', $result['message'] );
    }

    public function test_surfaces_a_transport_error(): void {
        $GLOBALS['jp_permissions_test_http_response'] = new FakeWpError( 'cURL error 6' );

        $result = Updates::test_token( 'ghp_valid' );

        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'cURL error 6', $result['message'] );
    }

    public function test_stores_the_token_so_it_is_not_plaintext_in_the_database(): void {
        $GLOBALS['jp_permissions_test_options']['jp_permissions_github_token'] = base64_encode( 'ghp_secret' );

        $this->assertSame( 'ghp_secret', Updates::get_github_token() );
    }

    public function test_returns_an_empty_token_when_none_is_stored(): void {
        $this->assertSame( '', Updates::get_github_token() );
    }

    public function test_authenticates_with_a_bearer_token(): void {
        Updates::test_token( 'ghp_valid' );

        $request = $GLOBALS['jp_permissions_test_http_requests'][0];
        $this->assertSame( 'Bearer ghp_valid', $request['args']['headers']['Authorization'] );
    }
}
