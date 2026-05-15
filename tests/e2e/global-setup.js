const { test: setup, expect } = require( '@playwright/test' );
const fs = require( 'fs' );
const path = require( 'path' );

const STORAGE_PATH = path.join( __dirname, 'artifacts/storage-states/admin.json' );
const USERNAME = process.env.WP_USERNAME || 'admin';
const PASSWORD = process.env.WP_PASSWORD || 'password';

setup( 'authenticate as admin', async ( { page, baseURL } ) => {
	await page.goto( `${ baseURL }/wp-login.php` );
	await page.fill( '#user_login', USERNAME );
	await page.fill( '#user_pass', PASSWORD );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();

	fs.mkdirSync( path.dirname( STORAGE_PATH ), { recursive: true } );
	await page.context().storageState( { path: STORAGE_PATH } );
} );
