const { test, expect, request } = require( '@playwright/test' );
const { execSync } = require( 'child_process' );

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';
const AJAX_URL = `${ BASE_URL }/wp-admin/admin-ajax.php`;
const SETTINGS_URL = `${ BASE_URL }/wp-admin/edit.php?post_type=se-event&page=settings`;

const WP_CLI = "npx wp-env run cli --env-cwd='wp-content/plugins/simple-events' --";

/**
 * Seed one orphaned se-event-date and return its ID.
 *
 * @return {string} The orphan post ID.
 */
function seedOrphan() {
	const out = execSync( `${ WP_CLI } wp eval-file tests/e2e/fixtures/seed-orphan.php`, {
		encoding: 'utf8',
	} );
	const m = out.match( /(\d+)\s*$/m );
	if ( ! m ) {
		throw new Error( 'Seeder did not return an orphan ID. Output:\n' + out );
	}
	return m[ 1 ];
}

/**
 * Does the post still exist? `wp post get` exits non-zero when it does not,
 * which avoids parsing wp-env's chatter.
 *
 * @param {string} id The post ID.
 *
 * @return {boolean} True if the post still exists.
 */
function orphanExists( id ) {
	try {
		execSync( `${ WP_CLI } wp post get ${ id } --field=ID`, { stdio: 'ignore' } );
		return true;
	} catch ( e ) {
		return false;
	}
}

/**
 * S-02 end-to-end: the settings-page ajax buttons.
 *
 * The handlers (SE_Settings::clear_orphaned_events / mark_existing_orders_as_completed)
 * had no capability or nonce check, so any authenticated user could permanently
 * delete event-date posts, and an admin could be made to do it by CSRF.
 *
 * They now require manage_options plus a valid se_admin_ajax nonce, and admin.js
 * sends that nonce. These specs cover both halves against a real site: the guard
 * actually refuses, and the button still works in the browser.
 */
test.describe( 'settings admin-ajax authorisation', () => {
	test( 'a logged out request cannot delete orphaned event dates', async () => {
		const orphanId = seedOrphan();
		expect( orphanExists( orphanId ), 'orphan should exist before the request' ).toBe( true );

		// Fresh context with no storage state — genuinely unauthenticated.
		const anon = await request.newContext( { baseURL: BASE_URL } );
		const response = await anon.post( AJAX_URL, {
			form: { action: 'se_clear_orphaned_events' },
		} );
		await anon.dispose();

		expect(
			orphanExists( orphanId ),
			`orphan must survive a logged out request (HTTP ${ response.status() })`
		).toBe( true );
	} );

	test( 'an authenticated admin request with no nonce is refused', async ( { request: adminRequest } ) => {
		const orphanId = seedOrphan();
		expect( orphanExists( orphanId ), 'orphan should exist before the request' ).toBe( true );

		// Real admin cookies, no nonce: the CSRF shape.
		const response = await adminRequest.post( AJAX_URL, {
			form: { action: 'se_clear_orphaned_events' },
		} );

		expect(
			orphanExists( orphanId ),
			`orphan must survive a nonce-less admin request (HTTP ${ response.status() })`
		).toBe( true );
	} );

	test( 'an admin clicking the button still clears orphaned event dates', async ( { page } ) => {
		const orphanId = seedOrphan();
		expect( orphanExists( orphanId ), 'orphan should exist before the click' ).toBe( true );

		await page.goto( SETTINGS_URL );

		const button = page.locator( '#se_clear_orphaned_btn' );
		await expect( button ).toBeVisible();
		await button.click();

		// The handler responds with "N orphaned events deleted successfully",
		// then the markup is cleared again after 2s (admin.js:242-247).
		await expect( page.locator( '#se_clear_orphaned_response' ) ).toContainText(
			/deleted successfully/,
			{ timeout: 10000 }
		);

		expect(
			orphanExists( orphanId ),
			'orphan should be gone after an admin clicks the button'
		).toBe( false );
	} );
} );
