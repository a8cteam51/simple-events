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
 * Additional regression coverage around the event-dates save flow.
 *
 * Specs that already PASS on `trunk` are written as `test()` — they're
 * regression-prevention; we don't want a future change to silently break
 * them. Specs that FAIL on `trunk` (the bug we're about to fix) are marked
 * `test.fixme` so they don't gate CI; once the fix lands, flip them back
 * to `test()` and they should go green.
 */

test.describe( 'Event dates – regression coverage', () => {
	/**
	 * Scenario B regression — once the fix lands, a second Update on a
	 * stuck-dirty post should clear the dirty flag. Today the post stays
	 * dirty forever even after re-saving.
	 */
	test.fixme( 'second Update on a stuck-dirty post clears the dirty flag', async ( { page } ) => {
		await openNewEvent( page );
		await setTitle( page, 'E2E Scenario B Repro' );
		await clickAddDate( page );
		await clickDone( page );
		await publish( page );
		await waitForSaveQuiet( page, 4000, 30000 );

		// Confirm we're in the stuck-dirty state today (delete this expectation
		// when the fix lands and we no longer reach this state at all).
		const afterFirst = await readEditorState( page );
		expect( afterFirst.status ).toBe( 'publish' );

		// Click Update with no further edits.
		await page.locator( '.editor-post-publish-button' ).click();
		await waitForSaveQuiet( page, 4000, 30000 );

		const afterSecond = await readEditorState( page );
		expect( afterSecond.isDirty ).toBe( false );
	} );

	/**
	 * Scenario C regression-prevention — edit-and-save on an already-published,
	 * reloaded post is clean today (1× sync, 1× post PUT, no dirty leak). The
	 * fix must NOT regress this.
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
	 * Multi-date first publish. Today this exhibits the same 2× /sync as the
	 * single-date case — fixme until fix lands.
	 */
	test.fixme( 'three dates on first publish create exactly three children, one sync', async ( { page } ) => {
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

	/**
	 * Save-while-sync-in-flight only meaningful once lockPostSaving is wired
	 * (Approach A in the plan). Stays fixme until then.
	 */
	test.fixme( 'save button is locked while /sync is in-flight', async ( { page } ) => {
		await openNewEvent( page );
		await setTitle( page, 'E2E Lock Test' );

		// Slow /sync responses to 2s so we can race against them.
		await page.route( /\/simple-events\/event-dates\/\d+\/sync/, async ( route ) => {
			await new Promise( ( r ) => setTimeout( r, 2000 ) );
			await route.continue();
		} );

		await clickAddDate( page );
		await clickDone( page );

		// Click Publish immediately — the lock should kick in.
		await publish( page );

		// Within 500ms of clicking publish, the publish button should be in a
		// "saving" state (Gutenberg's `is-busy` class) OR the lock should be
		// holding the save until /sync resolves.
		const isBusy = await page.evaluate( () =>
			window.wp.data.select( 'core/editor' ).isPostSavingLocked()
		);
		expect( isBusy ).toBe( true );

		await waitForSaveQuiet( page, 4000, 30000 );
		const state = await readEditorState( page );
		expect( state.isDirty ).toBe( false );
	} );
} );
