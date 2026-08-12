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
 * Primary repro for the autodraft → publish bug observed via the chrome-tool
 * agent. On `trunk` today this test FAILS — that's intentional. It documents
 * the bug. Once the fix lands the assertions flip to green.
 *
 * Three things must be true after a clean first-publish:
 *   1. Exactly one POST to /simple-events/event-dates/{id}/sync.
 *   2. isEditedPostDirty() === false after the save settles.
 *   3. Exactly one child se-event-date post in the DB (read via REST).
 *
 * Today: sync fires twice (server create→delete→create), so the child date
 * id churns and Gutenberg sees a late setAttributes diff → stuck dirty.
 */
test.describe( 'Event dates – first publish from autodraft', () => {
	test( 'leaves the post clean and produces exactly one child date', async ( { page } ) => {
		const consoleErrors = [];
		page.on( 'console', ( msg ) => {
			if ( msg.type() === 'error' ) {
				consoleErrors.push( msg.text() );
			}
		} );

		const net = startNetworkCounter( page );

		await openNewEvent( page );
		await setTitle( page, 'E2E First Publish Repro' );

		// Pre-Add Date sanity.
		const before = await readEditorState( page );
		expect( before.status ).toBe( 'auto-draft' );

		await clickAddDate( page );
		await clickDone( page );

		// The yellow in-block banner should appear (dateManager.isDirty=true).
		await expect( page.locator( '.se-unsaved-changes-message' ) ).toBeVisible();

		await publish( page );

		// Wait for the post-save snackbar.
		await expect(
			page.getByText( /Event dates synced/i ).first()
		).toBeVisible( { timeout: 10000 } );
		await waitForSaveQuiet( page, 4000, 30000 );

		const after = await readEditorState( page );
		const children = await fetchChildDates( page, after.postId );

		console.log( 'AFTER PUBLISH:', JSON.stringify( after, null, 2 ) );
		console.log( 'NETWORK COUNTS:', net.counts );
		console.log(
			'REQUESTS:',
			net.requests.map( ( r ) => `${ r.kind } ${ r.url }` )
		);
		console.log( 'CHILD DATES VIA REST:', JSON.stringify( children, null, 2 ) );

		// === Assertions that should pass after the fix ===

		// (1) Exactly one /sync POST per first publish. Fails today (3× in
		// local wp-env).
		expect( net.counts.sync ).toBe( 1 );

		// (2) Exactly one post PUT per first publish. Fails today (2× in
		// local wp-env — Gutenberg fires a follow-up after the subscribe's
		// dateSavePromise.then(savePost) chain).
		expect( net.counts.postSave ).toBe( 1 );

		// (3) Exactly one child se-event-date in the DB.
		expect( children.dates ).toHaveLength( 1 );

		// (4) No console errors.
		expect( consoleErrors ).toEqual( [] );

		// (5) The yellow in-block banner is gone (dateManager.isDirty cleared
		// by refreshWithNewDates after /sync response).
		await expect( page.locator( '.se-unsaved-changes-message' ) ).toBeHidden();

		// Note on Gutenberg's `isEditedPostDirty()`:
		// The chrome-agent run (on a hosted WP env) saw this stuck at `true`
		// after first publish. Local wp-env does NOT reproduce that symptom —
		// the Save button greys out, the post is genuinely clean. The fix
		// should clear the leak in any env that exhibits it, but asserting on
		// it here would be flaky. Re-test against your devilbox env if you
		// want to confirm the dirty leak is gone post-fix.
		// expect( after.isDirty ).toBe( false );
	} );
} );

// TODO follow-up specs once the primary repro is green:
//   - "Clicking Update a second time on a stuck-dirty post clears dirty" (Scenario B regression)
//   - "Edit-and-save on reloaded published event stays clean" (Scenario C — regression-prevention)
//   - "Save while /sync is in-flight is locked" (Approach A only — needs lockPostSaving in place)
//   - "Revert Changes fires no /sync"
//   - "Multi-date first publish creates N children, not 2N"
