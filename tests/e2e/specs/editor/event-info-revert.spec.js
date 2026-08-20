const { test, expect } = require( '@playwright/test' );
const { canvas } = require( '../../fixtures' );
const { execSync } = require( 'child_process' );

/**
 * "Revert Changes" must restore the last saved times and timezone.
 *
 * Changing the timezone is *meant* to move the timestamps — it keeps the same
 * local time in the new zone. Revert then has to put them back.
 *
 * It doesn't. event-manager.js seeds `originalDates` and `currentDates` with
 * lodash `clone`, which is shallow: two arrays holding the *same* date objects.
 * `updateTimezone` assigns into `eventDateTime[ key ]` (:192), mutating those
 * shared objects, so `originalDates` moves with `currentDates`. `revertDates`
 * then clones the already-mutated objects and restores nothing.
 *
 * Observed by hand before this was written: a date at 1787350086 became
 * 1787317686 after switching to Asia/Tokyo and stayed there through Revert,
 * while the timezone label *did* go back to site default — leaving the event at
 * the wrong time and claiming the wrong zone.
 *
 * The date must exist before the block mounts. A date added in the editor is
 * legitimately discarded by Revert, which is a different code path.
 */

// Far enough from the wp-env site zone (UTC+0) that the timestamps must move.
const SHIFT_TZ = 'Asia/Tokyo';

test.describe( 'Event info – Revert Changes', () => {
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

	/**
	 * Open an existing event in the block editor and wait for the block to mount.
	 *
	 * @param {import('@playwright/test').Page} page
	 */
	async function openEvent( page ) {
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
	}

	/**
	 * Put the block into edit mode with its inspector available.
	 *
	 * @param {import('@playwright/test').Page} page
	 */
	async function enterEditMode( page ) {
		await page.evaluate( () => {
			const block = window.wp.data
				.select( 'core/block-editor' )
				.getBlocks()
				.find( ( b ) => b.name === 'simple-events/event-info' );
			window.wp.data
				.dispatch( 'core/block-editor' )
				.updateBlockAttributes( block.clientId, { editMode: true } );
			window.wp.data
				.dispatch( 'core/block-editor' )
				.selectBlock( block.clientId );
			window.wp.data
				.dispatch( 'core/edit-post' )
				.openGeneralSidebar( 'edit-post/block' );
		} );
		await canvas( page ).locator( '.se-add-date-button' ).waitFor();
	}

	/**
	 * Click Done, which writes the date manager's current dates into the block's
	 * eventDates attribute — the only place they are externally observable.
	 *
	 * @param {import('@playwright/test').Page} page
	 */
	async function doneAndReadDates( page ) {
		await canvas( page ).locator( '.se__button-done' ).click();
		return page.evaluate( () => {
			const block = window.wp.data
				.select( 'core/block-editor' )
				.getBlocks()
				.find( ( b ) => b.name === 'simple-events/event-info' );
			return ( block.attributes.eventDates || [] ).map(
				( d ) => `${ d.start_date }/${ d.end_date }`
			);
		} );
	}

	/**
	 * @param {import('@playwright/test').Page} page
	 */
	async function readTimezone( page ) {
		return page.evaluate(
			() =>
				window.wp.data.select( 'core/editor' ).getEditedPostAttribute( 'meta' )
					.se_event_timezone
		);
	}

	test( 'restores the original timestamps after a timezone change', async ( {
		page,
	} ) => {
		await openEvent( page );

		await enterEditMode( page );
		const original = await doneAndReadDates( page );
		const originalTimezone = await readTimezone( page );
		expect( original.length, 'the seeded date should be present' ).toBe( 1 );

		// Change the timezone. This is *supposed* to move the timestamps.
		await enterEditMode( page );
		const timezone = page.locator( '.se-timezone-label input' );
		await timezone.click();
		await timezone.pressSequentially( SHIFT_TZ );
		await page.getByRole( 'option', { name: SHIFT_TZ, exact: true } ).click();
		const shifted = await doneAndReadDates( page );

		// Guard against a vacuous pass: with no shift, a broken revert would look
		// identical to a working one.
		expect(
			shifted,
			'the timezone change must actually move the timestamps'
		).not.toEqual( original );

		// Revert must put both the times and the timezone back.
		await enterEditMode( page );
		const revert = canvas( page ).locator( '.se-revert-changes-button' );
		await expect( revert ).toBeEnabled();
		await revert.click();
		const reverted = await doneAndReadDates( page );

		expect(
			await readTimezone( page ),
			'revert must restore the timezone'
		).toBe( originalTimezone );
		expect(
			reverted,
			'revert must restore the original timestamps'
		).toEqual( original );
	} );
} );
