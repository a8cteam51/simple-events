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
} = require( '../../fixtures' );

/**
 * User's manual repro path:
 *   1. Open new event.
 *   2. Add a date via the GUI (Add Date button).
 *   3. Save (Publish).
 *   4. Wait for the save + child-date creation to finish.
 *   5. Save again.
 *   6. Reload the page.
 *
 * After step 6, the browser should NOT show a "Leave site? Changes you made
 * may not be saved" dialog. That dialog is driven by `isEditedPostDirty()`
 * being true at unload time.
 */
test( 'publish → save again → reload: no unsaved-changes prompt fires', async ( { page } ) => {
	// Capture every dialog that fires during the run.
	const dialogs = [];
	page.on( 'dialog', async ( dialog ) => {
		dialogs.push( { type: dialog.type(), message: dialog.message() } );
		console.log( `[dialog] type=${ dialog.type() } message="${ dialog.message() }"` );
		await dialog.accept();
	} );

	const net = startNetworkCounter( page );

	console.log( '— Step 1: open new event' );
	await openNewEvent( page );
	await setTitle( page, 'E2E Reload Repro' );

	console.log( '— Step 2: add a date via GUI' );
	await clickAddDate( page );
	await clickDone( page );

	console.log( '— Step 3: publish (first save)' );
	await publish( page );

	console.log( '— Step 4: wait for snackbar + save to settle' );
	await expect(
		page.getByText( /Event dates synced/i ).first()
	).toBeVisible( { timeout: 10000 } );
	await waitForSaveQuiet( page, 4000, 30000 );

	const afterFirst = await readEditorState( page );
	console.log( 'After first save:', {
		isDirty: afterFirst.isDirty,
		status: afterFirst.status,
		counts: { ...net.counts },
	} );

	console.log( '— Step 5: save again' );
	const saveBtn = page.getByRole( 'button', { name: /^(update|save)$/i } ).first();
	const disabledAttr = await saveBtn.getAttribute( 'aria-disabled' );
	if ( disabledAttr === 'true' ) {
		console.log(
			'  Save button is aria-disabled — wp-env env reports post is clean.\n' +
			'  Falling back to programmatic savePost() so the flow still exercises\n' +
			'  the subscribe path.'
		);
		await page.evaluate( () => window.wp.data.dispatch( 'core/editor' ).savePost() );
	} else {
		console.log( '  Save button is enabled — clicking it.' );
		await saveBtn.click();
	}
	await waitForSaveQuiet( page, 4000, 30000 );

	const afterSecond = await readEditorState( page );
	console.log( 'After second save:', {
		isDirty: afterSecond.isDirty,
		counts: { ...net.counts },
	} );

	console.log( '— Step 6: reload the page' );
	await page.reload();
	await page.waitForFunction( () =>
		window.wp &&
		window.wp.data &&
		window.wp.data.select( 'core/editor' ) &&
		window.wp.data.select( 'core/editor' ).getCurrentPostId() > 0
	);

	const afterReload = await readEditorState( page );
	console.log( 'After reload:', {
		isDirty: afterReload.isDirty,
		status: afterReload.status,
	} );
	console.log( 'Dialogs captured during run:', dialogs );

	// Assertions
	const beforeUnload = dialogs.find( ( d ) => d.type === 'beforeunload' );
	expect( beforeUnload, '"Leave site?" beforeunload should NOT fire' ).toBeUndefined();
	expect( afterReload.isDirty, 'post should be clean after reload' ).toBe( false );
} );
