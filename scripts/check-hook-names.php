<?php
/**
 * Fails when the plugin hooks a name that no WordPress/WooCommerce source defines.
 *
 * Usage: php check-hook-names.php <plugin-root> <wp-core-dir> <woocommerce-dir|''> <own-prefix> <allowlist-file>
 *
 * Normally run through check-hook-names.sh, which locates the sources. This file
 * is not covered by PHPCS (phpcs.xml only lints the plugin file, includes/ and tests/).
 *
 * A hook name passes when it starts with the plugin's own prefix, matches an
 * allowlist entry, or matches a hook the sources define. Dynamic parts of a
 * name are written as `*`. A source definition whose only dynamic part is
 * trailing (do_action( 'woocommerce_product_' . $x ) => `woocommerce_product_*`)
 * never counts: it would accept any suffix, including typos. Such families must
 * be allowlisted explicitly. Definitions with a leading or inner dynamic part
 * (`{$type}_template`, `manage_{$screen->id}_columns`) do count.
 */

$script = array_shift( $argv );
if ( count( $argv ) !== 5 ) {
	fwrite( STDERR, "usage: php $script <plugin-root> <wp-core-dir> <woocommerce-dir|''> <own-prefix> <allowlist-file>\n" );
	exit( 2 );
}
list( $root, $core, $woocommerce, $ownPrefix, $allowlistFile ) = $argv;

// Directories never scanned in the plugin, wherever they sit in the tree.
const SKIPPED_PLUGIN_DIRECTORIES = array( 'vendor', 'node_modules', 'build', 'tests' );
const DEFINITION_FUNCTIONS       = array(
	'do_action',
	'apply_filters',
	'do_action_ref_array',
	'apply_filters_ref_array',
	'do_action_deprecated',
	'apply_filters_deprecated',
);
const REGISTRATION_FUNCTIONS     = array( 'add_action', 'add_filter' );

function tokenId( $token ): ?int {
	return is_array( $token ) ? $token[0] : null;
}

function tokenText( $token ): string {
	return is_array( $token ) ? $token[1] : $token;
}

function nextSignificantToken( array $tokens, int $index ): int {
	while ( isset( $tokens[ $index ] ) && in_array( tokenId( $tokens[ $index ] ), array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
		++$index;
	}
	return $index;
}

function decodeLiteral( string $literal ): string {
	$quote = $literal[0];
	$body  = substr( $literal, 1, -1 );
	if ( "'" === $quote ) {
		return str_replace( array( '\\\\', "\\'" ), array( '\\', "'" ), $body );
	}
	return stripcslashes( $body );
}

/**
 * First call argument as a name pattern: literal text kept, dynamic parts `*`.
 */
function firstArgumentPattern( array $tokens, int $openParen ): string {
	$pattern           = '';
	$depth             = array(
		'(' => 0,
		'[' => 0,
		'{' => 0,
	);
	$openingDelimiters = array(
		')' => '(',
		']' => '[',
		'}' => '{',
	);
	for ( $index = $openParen + 1, $count = count( $tokens ); $index < $count; ++$index ) {
		$token = $tokens[ $index ];
		$id    = tokenId( $token );
		$text  = tokenText( $token );
		if ( null !== $id && ( $depth['('] > 0 || $depth['['] > 0 || $depth['{'] > 0 ) ) {
			continue;
		}
		if ( T_CONSTANT_ENCAPSED_STRING === $id ) {
			$pattern .= decodeLiteral( $text );
			continue;
		}
		if ( T_ENCAPSED_AND_WHITESPACE === $id ) {
			$pattern .= $text;
			continue;
		}
		if ( T_CURLY_OPEN === $id || T_DOLLAR_OPEN_CURLY_BRACES === $id ) {
			$pattern .= '*';
			$nested   = 1;
			while ( $nested > 0 && ++$index < $count ) {
				$part = tokenText( $tokens[ $index ] );
				if ( '{' === $part ) {
					++$nested;
				} elseif ( '}' === $part ) {
					--$nested;
				}
			}
			continue;
		}
		if ( T_VARIABLE === $id ) {
			$pattern .= '*';
			continue;
		}
		if ( T_STRING === $id || T_NAME_QUALIFIED === $id || T_NAME_FULLY_QUALIFIED === $id ) {
			$pattern .= '*';
			continue;
		}
		if ( T_LNUMBER === $id ) {
			if ( 0 === $depth['('] && 0 === $depth['['] && 0 === $depth['{'] ) {
				$pattern .= $text;
			}
			continue;
		}
		if ( null !== $id ) {
			continue;
		}
		if ( ',' === $text && 0 === $depth['('] && 0 === $depth['['] && 0 === $depth['{'] ) {
			break;
		}
		if ( ')' === $text && 0 === $depth['('] ) {
			break;
		}
		if ( '(' === $text || '[' === $text || '{' === $text ) {
			++$depth[ $text ];
		} elseif ( isset( $openingDelimiters[ $text ] ) && $depth[ $openingDelimiters[ $text ] ] > 0 ) {
			--$depth[ $openingDelimiters[ $text ] ];
		}
	}
	return $pattern;
}

/**
 * Name patterns passed as first argument to any of the given functions.
 *
 * @param string[] $functionNames Lower-case function names.
 * @return string[]
 */
function hookCallPatterns( string $contents, array $functionNames ): array {
	$tokens   = token_get_all( $contents );
	$patterns = array();
	foreach ( $tokens as $index => $token ) {
		$tokenId      = tokenId( $token );
		$functionName = ltrim( strtolower( tokenText( $token ) ), '\\' );
		if ( ! in_array( $tokenId, array( T_STRING, T_NAME_FULLY_QUALIFIED ), true ) || ! in_array( $functionName, $functionNames, true ) ) {
			continue;
		}
		$openParen = nextSignificantToken( $tokens, $index + 1 );
		if ( ! isset( $tokens[ $openParen ] ) || '(' !== tokenText( $tokens[ $openParen ] ) ) {
			continue;
		}
		$pattern = firstArgumentPattern( $tokens, $openParen );
		if ( '' !== $pattern ) {
			$patterns[] = $pattern;
		}
	}
	return $patterns;
}

/**
 * Whether a source definition is specific enough to vouch for plugin hooks.
 */
function usefulDefinitionPattern( string $pattern ): bool {
	if ( '' === $pattern || '*' === $pattern ) {
		return false;
	}
	if ( 0 === substr_count( $pattern, '*' ) ) {
		return true;
	}
	// Only a trailing wildcard: accepts every suffix, so it never counts.
	return strrpos( $pattern, '*' ) < strlen( $pattern ) - 1;
}

/**
 * @return string[] Non-empty, non-comment lines.
 */
function readList( string $file ): array {
	$entries = array();
	foreach ( file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) ?: array() as $line ) {
		$line = trim( $line );
		if ( '' !== $line && '#' !== $line[0] ) {
			$entries[] = $line;
		}
	}
	return $entries;
}

/**
 * Every PHP file below $root except the skipped plugin directories and dot-directories.
 *
 * @return Generator<string>
 */
function pluginPhpFiles( string $root ): Generator {
	$directory = new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS );
	$filter    = new RecursiveCallbackFilterIterator(
		$directory,
		static function ( SplFileInfo $entry ): bool {
			if ( $entry->isDir() ) {
				$name = $entry->getFilename();
				return '.' !== $name[0] && ! in_array( $name, SKIPPED_PLUGIN_DIRECTORIES, true );
			}
			return 'php' === $entry->getExtension();
		}
	);
	foreach ( new RecursiveIteratorIterator( $filter ) as $file ) {
		yield $file->getPathname();
	}
}

$allowed     = readList( $allowlistFile );
$sourceRoots = array( $core );
if ( '' !== $woocommerce ) {
	$sourceRoots[] = $woocommerce;
}

$sourcePatterns = array();
foreach ( $sourceRoots as $sourceRoot ) {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $sourceRoot, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $sourceFile ) {
		if ( ! $sourceFile->isFile() || 'php' !== $sourceFile->getExtension() ) {
			continue;
		}
		foreach ( hookCallPatterns( file_get_contents( $sourceFile->getPathname() ), DEFINITION_FUNCTIONS ) as $pattern ) {
			if ( usefulDefinitionPattern( $pattern ) ) {
				$sourcePatterns[ $pattern ] = true;
			}
		}
	}
}
$sourcePatterns = array_keys( $sourcePatterns );

$failed = false;
foreach ( pluginPhpFiles( $root ) as $file ) {
	foreach ( hookCallPatterns( file_get_contents( $file ), REGISTRATION_FUNCTIONS ) as $name ) {
		if ( '' === trim( $name, '*' ) || str_starts_with( $name, $ownPrefix ) ) {
			continue;
		}
		foreach ( $allowed as $pattern ) {
			if ( fnmatch( $pattern, $name ) ) {
				continue 2;
			}
		}
		foreach ( $sourcePatterns as $pattern ) {
			if ( strlen( $pattern ) < 1024 && fnmatch( $pattern, $name ) ) {
				continue 2;
			}
		}
		fprintf( STDERR, "check-hook-names: %s: unknown hook '%s'\n", substr( $file, strlen( $root ) + 1 ), $name );
		$failed = true;
	}
}
exit( $failed ? 1 : 0 );
