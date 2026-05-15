const { test, expect } = require( '@playwright/test' );
const {
	openNewEvent,
	setTitle,
	clickAddDate,
	clickDone,
	publish,
	waitForSaveQuiet,
	readEditorState,
	startNetworkCounter,
	fetchChildDates,
} = require( '../../fixtures' );

/**
 * Regression coverage around the event-dates save flow. All specs here run
 * and must stay green.
 */

test.describe( 'Event dates – regression coverage', () => {
	/**
	 * Edit-and-save on an already-published, reloaded post must stay clean
	 * (no /sync, one post PUT, no dirty leak).
	 */
	test( 'edit-and-save on a reloaded published post stays clean', async ( { page } ) => {
		const net = startNetworkCounter( page );

		await openNewEvent( page );
		await setTitle( page, 'E2E Scenario C Repro' );
		await clickAddDate( page );
		await clickDone( page );
		await publish( page );
		await waitForSaveQuiet( page, 4000, 30000 );

		const { postId } = await readEditorState( page );
		expect( postId ).toBeGreaterThan( 0 );

		// Hard reload — drops the in-memory dirty leak.
		await page.goto( `/wp-admin/post.php?post=${ postId }&action=edit` );
		await page.waitForFunction( () =>
			window.wp.data &&
			window.wp.data.select( 'core/block-editor' )
				.getBlocks()
				.some( ( b ) => b.name === 'simple-events/event-info' )
		);

		// Confirm reload itself leaves the post clean (this is the load-time
		// part of the dirty-leak that the fix addresses).
		const onReload = await readEditorState( page );
		expect( onReload.isDirty, 'post should be clean immediately after reload' ).toBe( false );

		// Reset counters for the edit phase only.
		net.counts.sync = 0;
		net.counts.postSave = 0;
		net.counts.autosave = 0;
		net.requests.length = 0;

		// Make a non-date edit (title change) so the Save button is enabled,
		// then save. Since the dateManager isn't dirty, the click interceptor
		// in the block does NOT fire /sync — we should see exactly one post
		// PUT and zero /sync calls.
		await setTitle( page, 'E2E Scenario C Repro — edited' );
		await page.getByRole( 'button', { name: /^(update|save)$/i } ).first().click();
		await waitForSaveQuiet( page, 4000, 30000 );

		const state = await readEditorState( page );
		expect( state.isDirty ).toBe( false );
		expect( net.counts.sync ).toBe( 0 );
		expect( net.counts.postSave ).toBe( 1 );
	} );

	/**
	 * Revert Changes must not fire a /sync — there's nothing to send.
	 * Works today and must keep working after the fix.
	 */
	test( 'Revert Changes does not fire a /sync', async ( { page } ) => {
		const net = startNetworkCounter( page );

		await openNewEvent( page );
		await setTitle( page, 'E2E Revert Test' );
		await clickAddDate( page );

		const revertButton = page.locator( '.se-revert-changes-button' );
		await expect( revertButton ).toBeEnabled();
		await revertButton.click();

		// Brief settle to allow any inadvertent debounce to fire.
		await page.waitForTimeout( 1500 );

		expect( net.counts.sync ).toBe( 0 );
	} );

	/**
	 * Multi-date first publish: one /sync, one child per date, no dirty leak.
	 */
	test( 'three dates on first publish create exactly three children, one sync', async ( { page } ) => {
		const net = startNetworkCounter( page );

		await openNewEvent( page );
		await setTitle( page, 'E2E Multi-date Publish' );

		await clickAddDate( page );
		await clickAddDate( page );
		await clickAddDate( page );
		await clickDone( page );

		await publish( page );
		await waitForSaveQuiet( page, 4000, 30000 );

		const state = await readEditorState( page );
		const children = await fetchChildDates( page, state.postId );

		expect( state.isDirty ).toBe( false );
		expect( net.counts.sync ).toBe( 1 );
		expect( children.dates ).toHaveLength( 3 );
	} );
} );
