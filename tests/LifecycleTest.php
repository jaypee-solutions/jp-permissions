<?php

declare( strict_types = 1 );

namespace JayPee\Permissions\Tests;

use JayPee\Permissions\Lifecycle;
use PHPUnit\Framework\TestCase;

use JayPee\Permissions\WPDieException;

final class LifecycleTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['jp_permissions_test_multisite'] = false;
        $GLOBALS['jp_permissions_test_roles'] = [];
        $GLOBALS['jp_permissions_test_options'] = [];
    }

    public function test_multisite_activation_is_refused_before_creating_role_or_option(): void {
        $GLOBALS['jp_permissions_test_multisite'] = true;
        try {
            Lifecycle::activate();
            $this->fail( 'Activation should stop on multisite.' );
        } catch ( WPDieException $error ) {
            $this->assertSame( 'JP Permissions does not support multisite.', $error->getMessage() );
        }
        $this->assertArrayNotHasKey( 'jp_branch_editor', $GLOBALS['jp_permissions_test_roles'] );
        $this->assertArrayNotHasKey( 'jp_permissions_groups', $GLOBALS['jp_permissions_test_options'] );
    }
}
