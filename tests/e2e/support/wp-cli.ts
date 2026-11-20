import { execFileSync } from 'node:child_process';

/**
 * Run WP-CLI in the wp-env `cli` container and return its stdout verbatim,
 * minus the single trailing newline WP-CLI appends, so multi-line values with
 * blank lines and leading/trailing spaces round-trip exactly.
 *
 * wp-env writes its `ℹ`/`✔` status lines and PHP `Deprecated:`/`Warning:`
 * notices to stderr, never stdout, so stdout needs no filtering. stderr is
 * piped instead of inherited to keep test output quiet; on failure it is part
 * of the thrown error.
 */
export function wpCli( ...args: string[] ): string {
	const output = execFileSync( 'npx', [ '--no-install', 'wp-env', 'run', 'cli', '--', 'wp', ...args ], {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'pipe' ],
		env: { ...process.env, WP_ENV_HOME: process.env.WP_ENV_HOME ?? './.wp-env' },
	} );
	return output.replace( /\r?\n$/, '' );
}
