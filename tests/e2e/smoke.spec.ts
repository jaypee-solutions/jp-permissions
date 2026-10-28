import { test, expect, type Page } from '@playwright/test';

const ADMIN_USER = process.env.WP_ADMIN_USER ?? 'admin';
const ADMIN_PASS = process.env.WP_ADMIN_PASS ?? 'password';
const FB_USER = 'fb';

async function loginAs( page: Page, username: string, password = 'password' ): Promise<void> {
	await page.goto( '/wp-login.php' );
	const userField = page.locator( '#user_login' );
	const passwordField = page.locator( '#user_pass' );
	await passwordField.fill( password );
	await expect( passwordField ).toHaveValue( password );
	await userField.fill( username );
	await expect( userField ).toHaveValue( username );
	await page.click( '#wp-submit' );
	await expect( page ).toHaveURL( /wp-admin/ );
}

async function closePatternDialog( page: Page ): Promise<void> {
	const dialog = page.getByRole( 'dialog', { name: 'Choose a pattern' } );
	try {
		await dialog.waitFor( { state: 'visible', timeout: 2000 } );
	} catch {
		return;
	}
	await dialog.getByRole( 'button', { name: 'Close' } ).click();
	await expect( dialog ).toBeHidden();
}

async function addSubpageFromTraining( page: Page ): Promise<void> {
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr' ).filter( { hasText: 'Training' } ).first();
	await row.hover();
	await row.getByRole( 'link', { name: 'Add subpage' } ).click();
}

test.describe( 'Page Branches browser flows', () => {
	test( 'E2E-1: Branch Editor creates and publishes a subpage under Training', async ( { page } ) => {
		await loginAs( page, FB_USER );
		await addSubpageFromTraining( page );
		await closePatternDialog( page );
		await expect( page.locator( '.editor-post-title__input' ) ).toBeVisible( { timeout: 30_000 } );
		await page.locator( '.editor-post-title__input' ).fill( 'E2E Football Page' );
		await page.getByRole( 'button', { name: 'Publish', exact: true } ).first().click();
		await page.getByRole( 'button', { name: 'Publish', exact: true } ).last().click();
		await expect( page.getByText( 'Published' ) ).toBeVisible();
		await page.goto( '/wp-admin/edit.php?post_type=page' );
		const createdPage = page.locator( 'tr' ).filter( { hasText: 'E2E Football Page' } );
		await expect( createdPage ).toBeVisible();
		await expect( createdPage ).toHaveClass( /level-2/ );
	} );

	test( 'E2E-2: Branch Editor cannot edit Tennis', async ( { page } ) => {
		await loginAs( page, ADMIN_USER, ADMIN_PASS );
		await page.goto( '/wp-admin/edit.php?post_type=page' );
		const editUrl = await page.getByRole( 'link', { name: 'Edit “Tennis”' } ).getAttribute( 'href' );
		await loginAs( page, FB_USER );
		await page.goto( editUrl! );
		await expect( page.getByText( 'Sorry, you are not allowed to edit this item.' ) ).toBeVisible();
	} );

	test( 'E2E-3: Branch Editor cannot access Page Attributes controls available to administrators', async ( { page } ) => {
		await loginAs( page, ADMIN_USER, ADMIN_PASS );
		await page.goto( '/wp-admin/edit.php?post_type=page' );
		const branchEditUrl = await page.getByRole( 'link', { name: 'Edit “Training”' } ).first().getAttribute( 'href' );
		const adminEditUrl = await page.getByRole( 'link', { name: 'Edit “Football”' } ).getAttribute( 'href' );
		await loginAs( page, FB_USER );
		await page.goto( branchEditUrl! );
		await closePatternDialog( page );
		await expect( page.getByText( 'Parent', { exact: true } ) ).toHaveCount( 0 );
		await loginAs( page, ADMIN_USER, ADMIN_PASS );
		await page.goto( adminEditUrl! );
		await closePatternDialog( page );
		await expect( page.getByText( 'Parent', { exact: true } ) ).toBeVisible();
	} );

	test( 'Branch Editor uploads an image with resized versions', async ( { page } ) => {
		await loginAs( page, FB_USER );
		await addSubpageFromTraining( page );
		await closePatternDialog( page );
		await page.locator( '.editor-post-title__input' ).fill( 'E2E Image Upload' );
		await page.getByRole( 'button', { name: 'Add block' } ).click();
		await page.getByRole( 'option', { name: /Image/ } ).click();
		const png = await page.evaluate( () => {
			const canvas = document.createElement( 'canvas' );
			canvas.width = 1200;
			canvas.height = 800;
			const context = canvas.getContext( '2d' );
			if ( ! context ) {
				throw new Error( 'Canvas context unavailable' );
			}
			context.fillStyle = '#245';
			context.fillRect( 0, 0, canvas.width, canvas.height );
			return canvas.toDataURL( 'image/png' ).split( ',' )[1];
		} );
		await page.locator( 'input[type="file"]' ).first().setInputFiles( {
			name: 'branch-image.png', mimeType: 'image/png', buffer: Buffer.from( png, 'base64' ),
		} );
		await expect( page.locator( '.wp-block-image img' ) ).toBeVisible();
		await expect( page.locator( '.wp-block-image img' ) ).toHaveAttribute( 'srcset', /branch-image/ );
	} );
} );

test.describe( 'JP Permissions for WordPress — smoke', () => {
	test( 'the plugin is active and its Updates page loads', async ( { page } ) => {
		await loginAs( page, ADMIN_USER, ADMIN_PASS );
		await page.goto( '/wp-admin/plugins.php' );
		await expect( page.locator( 'tr.active', { hasText: 'JP Permissions for WordPress' } ) ).toBeVisible();
		await page.goto( '/wp-admin/options-general.php?page=jp-permissions-updates' );
		await expect( page.getByLabel( 'GitHub Token' ) ).toBeVisible();
	} );

	test( 'the front page renders without a PHP error', async ( { page } ) => {
		const response = await page.goto( '/' );
		expect( response?.status() ).toBe( 200 );
		await expect( page.locator( 'body' ) ).not.toContainText( 'Fatal error' );
	} );
} );
