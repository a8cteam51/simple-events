const { test, expect } = require( '@playwright/test' );
const { execSync } = require( 'child_process' );

/**
 * When the event-dates GET fails, the edit view must say so — not show an empty
 * date list with dead controls.
 *
 * Observed on an event with one saved date, route forced to 500:
 *   - view mode showed the date correctly (it is server-rendered, so the failed
 *     client fetch never touches it)
 *   - clicking Edit showed no dates at all
 *   - Add Date did nothing, twice, with no error anywhere on screen
 *   - console: "Error initializing date manager: TypeError: Cannot read properties
 *     of undefined (reading 'forEach')"
 *
 * The chain: getEventDatePosts() returns `[]` on error (index.js:82), then
 * dateManager() does `initialDates.dates.forEach` on it (event-manager.js:58).
 * initializeDateManager() swallows the TypeError and returns null, so
 * dateManagerState stays null and every control is a no-op behind `?.`.
 *
 * Required behaviour, per Glynn: on failure write nothing — leave the eventDates
 * attribute alone, do not touch the dirty flag, and do not retry. Plus the edit
 * view has to be honest about not knowing.
 *
 * The failure is injected server-side by the se-e2e-fail-dates mu-plugin fixture,
 * gated on a cookie. It must be server-side: the block fetches while mounting,
 * before any in-page script could patch apiFetch.
 */

test.describe( 'Event info – event-dates GET failure', () => {
	let eventId;

	test.beforeAll( () => {
		const out = execSync(
			"npx wp-env run cli --env-cwd='wp-content/plugins/simple-events' -- wp eval-file tests/e2e/fixtures/seed-revert.php",
			{ encoding: 'utf8' }
		);
		const m = out.match( /(\d+)\s*$/m );
		if ( ! m ) {
			throw new Error( 'Seeder returned no event ID. Output:\n' + out );
		}
		eventId = m[ 1 ];
	} );

	test( 'says the dates could not be loaded instead of showing none', async ( {
		page,
		context,
	} ) => {
		const syncRequests = [];
		page.on( 'request', ( req ) => {
			if (
				req.method() === 'POST' &&
				/event-dates\/\d+\/sync/.test( decodeURIComponent( req.url() ) )
			) {
				syncRequests.push( req.url() );
			}
		} );

		await context.addCookies( [
			{
				name: 'se_e2e_fail_dates',
				value: '1',
				domain: 'localhost',
				path: '/',
			},
		] );

		await page.goto( `/wp-admin/post.php?post=${ eventId }&action=edit` );
		await page.waitForFunction(
			() =>
				window.wp &&
				window.wp.data &&
				window.wp.data.select( 'core/editor' ) &&
				window.wp.data.select( 'core/editor' ).getCurrentPostId() > 0
		);
		await page.evaluate( () => {
			const prefs = window.wp.data.dispatch( 'core/preferences' );
			if ( prefs && prefs.set ) {
				prefs.set( 'core/edit-post', 'welcomeGuide', false );
				prefs.set( 'core', 'welcomeGuide', false );
			}
		} );
		await page
			.locator( '.components-modal__screen-overlay' )
			.waitFor( { state: 'detached', timeout: 5000 } )
			.catch( () => {} );
		await page.waitForFunction( () =>
			window.wp.data
				.select( 'core/block-editor' )
				.getBlocks()
				.some( ( b ) => b.name === 'simple-events/event-info' )
		);

		// Enter edit mode the way a user does — via the block toolbar's Edit button.
		// Setting the editMode attribute directly reaches a state the UI cannot
		// actually produce, which is how this bug got mis-described the first time.
		await page.evaluate( () => {
			const block = window.wp.data
				.select( 'core/block-editor' )
				.getBlocks()
				.find( ( b ) => b.name === 'simple-events/event-info' );
			window.wp.data
				.dispatch( 'core/block-editor' )
				.selectBlock( block.clientId );
		} );
		await page.getByRole( 'button', { name: 'Edit', exact: true } ).click();

		// The block must state that the dates could not be read.
		await expect(
			page.locator( '.se-dates-load-error' ),
			'a failed dates load must be reported in the block'
		).toBeVisible();

		// And must not offer controls that cannot work.
		await expect(
			page.locator( '.se-add-date-button' ),
			'Add Date must not be offered when the dates are unknown'
		).toBeHidden();

		// Nothing may be written back over dates we failed to read.
		const attrs = await page.evaluate( () => {
			const block = window.wp.data
				.select( 'core/block-editor' )
				.getBlocks()
				.find( ( b ) => b.name === 'simple-events/event-info' );
			return block.attributes.eventDates ?? null;
		} );
		expect(
			attrs,
			'eventDates must not be overwritten when the fetch failed'
		).toBeNull();

		expect(
			syncRequests,
			'no sync may be sent while the dates are unknown'
		).toEqual( [] );
	} );
} );
