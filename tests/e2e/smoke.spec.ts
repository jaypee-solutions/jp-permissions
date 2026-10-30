import { test, expect, type APIRequestContext, type Page } from '@playwright/test';

const ADMIN_USER = process.env.WP_ADMIN_USER ?? 'admin';
const ADMIN_PASS = process.env.WP_ADMIN_PASS ?? 'password';
const FB_USER = 'fb';

// Typing into wp-login.php races its autofocus script, which can clear the
// password field; post the form through the browser context's cookie jar instead.
async function loginAs( page: Page, username: string, password = 'password' ): Promise<void> {
	await page.context().clearCookies();
	await page.request.get( '/wp-login.php' );
	const response = await page.request.post( '/wp-login.php', {
		form: { log: username, pwd: password, 'wp-submit': 'Log In', testcookie: '1' },
		maxRedirects: 0,
	} );
	expect( response.status(), `login as ${ username }` ).toBe( 302 );
	await page.goto( '/wp-admin/' );
	await expect( page ).toHaveURL( /wp-admin/ );
}

type RestPage = { id: number; parent: number; title: { rendered: string } };

// Resolve fixture pages through the public REST API so lookups never depend on Pages-list pagination.
async function publishedPage( request: APIRequestContext, title: string, parent = 0 ): Promise<RestPage> {
	const response = await request.get( '/wp-json/wp/v2/pages', {
		params: { search: title, parent, per_page: 100, _fields: 'id,parent,title' },
	} );
	expect( response.ok(), `REST lookup of ${ title }` ).toBeTruthy();
	const match = ( await response.json() as RestPage[] ).find( ( candidate ) => candidate.title.rendered === title );
	expect( match, `published page ${ title } under ${ parent }` ).toBeDefined();
	return match!;
}

const editUrl = ( id: number ): string => `/wp-admin/post.php?post=${ id }&action=edit`;

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
		const title = page.frameLocator( 'iframe' ).locator( '.wp-block-post-title' );
		await expect( title ).toBeVisible();
		const pageTitle = `E2E Football Page ${ Date.now() }`;
		await title.fill( pageTitle );
		const publishButton = page.getByRole( 'button', { name: 'Publish', exact: true } );
		await expect( publishButton.first() ).toBeEnabled();
		await publishButton.first().click();
		const confirmPublishButton = publishButton.last();
		await expect( confirmPublishButton ).toBeEnabled();
		await confirmPublishButton.click();
		await expect( page.getByText( 'Published', { exact: true } ) ).toBeVisible();
		const football = await publishedPage( page.request, 'Football' );
		const training = await publishedPage( page.request, 'Training', football.id );
		await expect.poll( async () => ( await publishedPage( page.request, pageTitle, training.id ) ).parent ).toBe( training.id );
	} );

	test( 'E2E-2: Branch Editor cannot edit Tennis', async ( { page } ) => {
		await loginAs( page, ADMIN_USER, ADMIN_PASS );
		const tennis = await publishedPage( page.request, 'Tennis' );
		await loginAs( page, FB_USER );
		await page.goto( editUrl( tennis.id ) );
		await expect( page.getByText( 'Sorry, you are not allowed to edit this item.' ) ).toBeVisible();
	} );

	test( 'E2E-3: Branch Editor cannot access Page Attributes controls available to administrators', async ( { page } ) => {
		const football = await publishedPage( page.request, 'Football' );
		const training = await publishedPage( page.request, 'Training', football.id );
		await loginAs( page, FB_USER );
		await page.goto( editUrl( training.id ) );
		// Absence only means something once the editor has rendered.
		await expect( page.frameLocator( 'iframe' ).locator( '.wp-block-post-title' ) ).toBeVisible();
		await expect( page.getByText( 'Parent', { exact: true } ) ).toHaveCount( 0 );
		await loginAs( page, ADMIN_USER, ADMIN_PASS );
		await page.goto( editUrl( football.id ) );
		await expect( page.getByText( 'Parent', { exact: true } ) ).toBeVisible();
	} );

	test( 'Branch Editor uploads an image with resized versions', async ( { page } ) => {
		await loginAs( page, FB_USER );
		await addSubpageFromTraining( page );
		const title = page.frameLocator( 'iframe' ).locator( '.wp-block-post-title' );
		await expect( title ).toBeVisible();
		await title.fill( `E2E Image Upload ${ Date.now() }` );
		await page.getByRole( 'button', { name: 'Block Inserter' } ).click();
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
		const editor = page.frameLocator( 'iframe' );
		await editor.getByRole( 'button', { name: 'Upload' } ).click();
		await editor.locator( 'input[type="file"]' ).setInputFiles( {
			name: 'branch-image.png', mimeType: 'image/png', buffer: Buffer.from( png, 'base64' ),
		} );
		const image = editor.locator( '.wp-block-image img' );
		await expect( image ).toBeVisible();
		await expect( image ).toHaveAttribute( 'src', /-\d+x\d+\.png$/ );
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
