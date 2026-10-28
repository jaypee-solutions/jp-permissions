import { test, expect, type Page } from '@playwright/test';

const ADMIN_USER = process.env.WP_ADMIN_USER ?? 'admin';
const ADMIN_PASS = process.env.WP_ADMIN_PASS ?? 'password';

async function login( page: Page ): Promise<void> {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', ADMIN_USER );
	await page.fill( '#user_pass', ADMIN_PASS );
	await page.click( '#wp-submit' );
	await expect( page ).toHaveURL( /wp-admin/ );
}

test.describe( 'JP Permissions for WordPress — smoke', () => {
	test( 'the plugin is active and its Updates page loads', async ( { page } ) => {
		await login( page );

		await page.goto( '/wp-admin/plugins.php' );
		await expect(
			page.locator( 'tr.active', { hasText: 'JP Permissions for WordPress' } )
		).toBeVisible();

		await page.goto( '/wp-admin/options-general.php?page=jp-permissions-updates' );
		await expect( page.getByLabel( 'GitHub Token' ) ).toBeVisible();
	} );

	test( 'the front page renders without a PHP error', async ( { page } ) => {
		const response = await page.goto( '/' );

		expect( response?.status() ).toBe( 200 );
		await expect( page.locator( 'body' ) ).not.toContainText( 'Fatal error' );
	} );
} );
