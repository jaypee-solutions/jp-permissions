<?php
/**
 * php-scoper configuration for the release build.
 *
 * WordPress loads every active plugin into one PHP process. Two JayPee plugins
 * that bundle different Plugin Update Checker versions would otherwise race for
 * the same class names, and whichever loaded first would win. Prefixing the
 * bundled dependencies with the plugin's own namespace removes that race.
 *
 * Used by scripts/build-release.sh; never applied to the working tree.
 *
 * @package JayPee\Permissions
 */

declare( strict_types = 1 );

use Isolated\Symfony\Component\Finder\Finder;

/**
 * Classes the host provides — WordPress, WooCommerce, or another plugin. They
 * must keep their global names; everything else, including the bundle's own
 * global classes, gets prefixed.
 */
$host_classes = [
    '/^WP_.*/',
    '/^wpdb$/',
    '/^WooCommerce$/',
    '/^Automattic\\\\.*/',
    // Optional integration: the Debug Bar plugin, if the site has it.
    '/^Debug_Bar.*/',
];

return [
    'prefix' => 'JayPee\Permissions' . '\\Vendor',

    // Only the bundled dependencies are scoped; the plugin's own code resolves
    // the prefixed class names at runtime (see includes/class-updates.php).
    'finders' => [
        Finder::create()
            ->files()
            ->ignoreVCS( true )
            ->notName( '/.*\\.(neon|dist|md|dist\\.php|txt|xsd|yml|yaml)$/' )
            ->exclude(
                [
                    'doc',
                    'docs',
                    'test',
                    'tests',
                    'Tests',
                    'test_old',
                    'vendor-bin',
                ]
            )
            ->in( 'vendor' ),
        Finder::create()->append( [ 'composer.json' ] ),
    ],

    // The plugin's own namespace must survive untouched: build-release.sh copies
    // those files in unscoped, and the two halves have to agree on the names.
    'exclude-namespaces' => [
        'JayPee\Permissions',
    ],

    /*
     * Global functions and constants belong to the WordPress host, not to the
     * bundle: WordPress declares them, the bundle only calls them. Prefixing
     * them buys nothing and actively breaks the `function_exists( 'x' )` guards
     * WordPress libraries are built on — php-scoper rewrites the string literal
     * too, so the guard starts asking about a name that can never exist and the
     * guarded branch is silently skipped forever.
     *
     * This is safe as long as no bundled package declares a *global* function or
     * constant of its own; Plugin Update Checker declares none (its readme
     * parser's user_sanitize()/encodeit()/decodeit() are class methods). Revisit
     * when adding a dependency that does — those declarations do want prefixing.
     *
     * Classes are deliberately not excluded wholesale. Prefixing them is the
     * entire point of this build step.
     */
    'exclude-functions' => [ '/.*/' ],
    'exclude-constants' => [ '/.*/' ],
    'exclude-classes'   => $host_classes,

    /*
     * Do not alias the prefixed symbols back into the global namespace. The
     * default would re-export Parsedown and PucReadmeParser under their original
     * names — putting back the exact collision this build step removes, since
     * the first plugin to load would own the global alias for everyone.
     */
    'expose-global-classes'   => false,
    'expose-global-functions' => false,
    'expose-global-constants' => false,

    'patchers' => [
        /**
         * php-scoper rewrites the class name in `class_exists('X')` but leaves
         * the two-argument form `class_exists('X', false)` alone. Plugin Update
         * Checker guards its bundled Parsedown and PucReadmeParser exactly that
         * way, so without this patch another plugin's *global* Parsedown
         * satisfies the guard, our prefixed copy is never loaded, and the
         * following Parsedown::instance() call fatals on a site that has both
         * plugins — the collision this whole build step exists to prevent.
         *
         * Namespaced arguments are left alone: they are already prefixed, or
         * excluded, by php-scoper itself.
         */
        static function ( string $file_path, string $prefix, string $contents ) use ( $host_classes ): string {
            return preg_replace_callback(
                '/\\\\?\b(class_exists|interface_exists|trait_exists)\(\s*\'([A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)\'\s*,/',
                static function ( array $matches ) use ( $prefix, $host_classes ): string {
                    foreach ( $host_classes as $pattern ) {
                        if ( preg_match( $pattern, $matches[2] ) ) {
                            return $matches[0];
                        }
                    }

                    return '\\' . $matches[1] . '(\'' . $prefix . '\\' . $matches[2] . '\',';
                },
                $contents
            );
        },

        /**
         * Plugin Update Checker keys its version registry by *general* class
         * name — 'Vcs\PluginUpdateChecker', 'GitHubApi' — and
         * PucFactory::buildUpdateChecker() rebuilds those keys at runtime by
         * concatenation. php-scoper cannot follow that concatenation, so its
         * unprefixed lookup disagrees with the prefixed registration literal in
         * load-v5pX.php and PUC terminates with E_USER_ERROR.
         *
         * The keys are identifiers, not class references, and must stay
         * unprefixed. The corresponding ::class values remain scoped.
         */
        static function ( string $file_path, string $prefix, string $contents ): string {
            if ( ! preg_match( '#/load-v\d+p\d+\.php$#', $file_path ) ) {
                return $contents;
            }

            return str_replace( "'" . $prefix . '\\', "'", $contents );
        },

        /**
         * PUC's autoloader assumes a custom namespace moves bundled global
         * classes alongside the library. php-scoper instead lifts those classes
         * to its prefix root, so Parsedown and PucReadmeParser are otherwise not
         * loadable when the update-details screen renders a changelog.
         */
        static function ( string $file_path, string $prefix, string $contents ): string {
            if ( ! preg_match( '#/Puc/v\d+p\d+/Autoloader\.php$#', $file_path ) ) {
                return $contents;
            }

            // Single-quoted PHP literal: every backslash doubled, so the
            // trailing separator cannot escape the closing quote.
            $literal = "'" . str_replace( '\\', '\\\\', $prefix . '\\' ) . "'";

            return preg_replace_callback(
                '/\$libraryPrefix\s*=\s*\$isCustomNamespace\s*\?\s*\$namespaceWithSlash\s*:\s*\'\';/',
                static function () use ( $literal ): string {
                    return '$libraryPrefix = ' . $literal . ';';
                },
                $contents
            );
        },
    ],
];
