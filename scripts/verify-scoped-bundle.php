<?php
/**
 * Smoke-tests the php-scoper'd dependency bundle produced by build-release.sh.
 *
 * Prefixing rewrites class names, but Plugin Update Checker reaches for some of
 * its classes through strings that the rewriter cannot follow. A syntactically
 * valid ZIP can therefore still fatal when WordPress loads the plugin.
 *
 * Usage: php scripts/verify-scoped-bundle.php <dist-vendor-dir>
 *
 * @package JayPee\Permissions
 */

declare( strict_types = 1 );

$vendor_dir = $argv[1] ?? '';

if ( '' === $vendor_dir || ! is_dir( $vendor_dir ) ) {
	fwrite( STDERR, "verify-scoped-bundle: usage: php scripts/verify-scoped-bundle.php <dist-vendor-dir>\n" );
	exit( 1 );
}

$puc_dir = $vendor_dir . '/yahnis-elsts/plugin-update-checker';

if ( ! is_dir( $puc_dir ) ) {
	// Nothing bundled — a plugin without runtime dependencies is fine.
	exit( 0 );
}

$bootstrap_file = $puc_dir . '/plugin-update-checker.php';

if ( ! is_file( $bootstrap_file ) ) {
	fwrite( STDERR, "verify-scoped-bundle: no PUC bootstrap (plugin-update-checker.php) in the bundle\n" );
	exit( 1 );
}

$failures = [];

// The prefix root is wherever php-scoper put the previously-global classes.
$parsedown_file = $puc_dir . '/vendor/Parsedown.php';
$prefix         = '';

if ( is_file( $parsedown_file ) ) {
	$parsedown_contents = file_get_contents( $parsedown_file );

	if ( false === $parsedown_contents ) {
		$failures[] = 'vendor/Parsedown.php is not readable — cannot determine php-scoper prefix';
	} elseif ( preg_match( '/^namespace\s+([^;]+);/m', $parsedown_contents, $m ) ) {
		$prefix = trim( $m[1] );
	} else {
		$failures[] = 'vendor/Parsedown.php is not namespaced — php-scoper did not prefix the bundled global classes';
	}
}

// Let PUC select its current loader. Picking a load-v*.php file here would
// duplicate that version-selection policy and can choose the wrong version.
require $bootstrap_file;

// PucFactory rebuilds these keys at runtime from strings that cannot include
// the scoper prefix. Their resolved values must, however, be loadable scoped
// classes.
$factory = $prefix . '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory';

if ( ! class_exists( $factory ) ) {
	$failures[] = sprintf( 'the scoped PUC factory %s is not loadable', $factory );
} else {
	foreach ( [ 'Plugin\\UpdateChecker', 'Vcs\\PluginUpdateChecker', 'GitHubApi' ] as $general_class ) {
		$resolved = $factory::getLatestClassVersion( $general_class );

		if ( null === $resolved ) {
			$failures[] = sprintf(
				'PUC has no class registered for "%s" — the registry keys were prefixed, so the update check fatals with "does not support updates for plugins hosted on GitHub"',
				$general_class
			);
		} elseif ( ! class_exists( $resolved ) ) {
			$failures[] = sprintf( '"%s" resolves to %s, which is not loadable', $general_class, $resolved );
		}
	}
}

// PUC's bundled global classes must be reachable through its own autoloader;
// Composer has no PSR-4 or classmap entry for them.
if ( '' !== $prefix ) {
	foreach ( [ 'Parsedown', 'PucReadmeParser' ] as $library_class ) {
		if ( ! class_exists( $prefix . '\\' . $library_class ) ) {
			$failures[] = sprintf(
				'%s\\%s is not loadable — PUC\'s autoloader map does not match where php-scoper put the bundled global classes',
				$prefix,
				$library_class
			);
		}
	}
}

if ( $failures ) {
	fwrite( STDERR, "verify-scoped-bundle: the prefixed bundle is broken\n\n" );

	foreach ( $failures as $failure ) {
		fwrite( STDERR, '  - ' . $failure . "\n" );
	}

	fwrite( STDERR, "\nThis ships as a fatal error on every site that installs the ZIP.\nCheck the patchers in scoper.inc.php.\n" );
	exit( 1 );
}

echo 'verify-scoped-bundle: prefixed bundle resolves (' . $prefix . ')' . PHP_EOL;
