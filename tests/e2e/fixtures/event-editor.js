/**
 * Helpers for interacting with the Simple Events `se-event` editor in Playwright.
 *
 * Selectors target stable Gutenberg landmarks and the `simple-events/event-info`
 * block's own class names (.se-add-date-button, .se__button-done, etc.).
 */

const NEW_EVENT_PATH = '/wp-admin/post-new.php?post_type=se-event';

/**
 * Open a fresh autodraft `se-event` editor and wait until the event-info block
 * has been auto-inserted and the dateManager initialised.
 *
 * @param {import('@playwright/test').Page} page
 */
async function openNewEvent( page ) {
	await page.goto( NEW_EVENT_PATH );

	// Wait for the editor to finish booting.
	await page.waitForFunction( () =>
		window.wp &&
		window.wp.data &&
		window.wp.data.select( 'core/editor' ) &&
		window.wp.data.select( 'core/editor' ).getCurrentPostId() > 0
	);

	// Dismiss the Gutenberg welcome guide modal — it intercepts clicks on the
	// event-info block. Setting the preference is more reliable than clicking
	// a close button across Gutenberg versions.
	await page.evaluate( () => {
		const prefs = window.wp.data.dispatch( 'core/preferences' );
		if ( prefs && prefs.set ) {
			prefs.set( 'core/edit-post', 'welcomeGuide', false );
			prefs.set( 'core', 'welcomeGuide', false );
			prefs.set( 'core/edit-post', 'welcomeGuideTemplate', false );
		}
	} );

	// If the modal is already rendered, wait for it to detach.
	await page.locator( '.components-modal__screen-overlay' )
		.waitFor( { state: 'detached', timeout: 5000 } )
		.catch( () => {} );

	// Wait for the auto-inserted event-info block.
	await page.waitForFunction( () => {
		const blocks = window.wp.data.select( 'core/block-editor' ).getBlocks();
		return blocks && blocks.some( ( b ) => b.name === 'simple-events/event-info' );
	} );
}

/**
 * Set the post title via wp.data. UI-driven title setting is fragile across
 * Gutenberg versions (the title textbox often has no accessible name), so we
 * dispatch the edit directly. Title doesn't matter for the bug repro — only
 * dirty state and child-date persistence do.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} title
 */
async function setTitle( page, title ) {
	await page.evaluate( ( t ) => {
		window.wp.data.dispatch( 'core/editor' ).editPost( { title: t } );
	}, title );
}

/**
 * Click the "Add Date" button inside the event-info block.
 *
 * @param {import('@playwright/test').Page} page
 */
async function clickAddDate( page ) {
	await page.locator( '.se-add-date-button' ).click();
}

/**
 * Click the "Done" button inside the event-info block (exits edit mode).
 *
 * @param {import('@playwright/test').Page} page
 */
async function clickDone( page ) {
	await page.locator( '.se__button-done' ).click();
}

/**
 * Click Publish + confirm Publish (Gutenberg's two-step publish flow).
 *
 * @param {import('@playwright/test').Page} page
 */
async function publish( page ) {
	// First click opens the publish sidebar.
	await page.getByRole( 'button', { name: /^publish$/i } ).first().click();
	// Wait for the panel to render — name-then-confirm flow.
	await page.waitForTimeout( 500 );
	// Second click confirms — there should now be a second Publish button in the panel.
	const confirmPublish = page.getByRole( 'button', { name: /^publish$/i } ).nth( 1 );
	if ( await confirmPublish.isVisible().catch( () => false ) ) {
		await confirmPublish.click();
	}
}

/**
 * Wait until `isSavingPost()` has been false for `quietMs` continuous milliseconds.
 * Catches the case where the plugin's subscribe handler triggers a second savePost()
 * after the first one completes.
 *
 * @param {import('@playwright/test').Page} page
 * @param {number} quietMs
 * @param {number} timeoutMs
 */
async function waitForSaveQuiet( page, quietMs = 3000, timeoutMs = 30000 ) {
	const start = Date.now();
	let lastBusyAt = Date.now();
	while ( Date.now() - start < timeoutMs ) {
		const busy = await page.evaluate( () => {
			const editor = window.wp.data.select( 'core/editor' );
			return editor.isSavingPost() || editor.isAutosavingPost();
		} );
		if ( busy ) {
			lastBusyAt = Date.now();
		} else if ( Date.now() - lastBusyAt >= quietMs ) {
			return;
		}
		await page.waitForTimeout( 250 );
	}
	throw new Error( `Editor never settled (>${ timeoutMs }ms with no ${ quietMs }ms quiet window)` );
}

/**
 * Read selected editor state via wp.data.
 *
 * @param {import('@playwright/test').Page} page
 */
async function readEditorState( page ) {
	return page.evaluate( () => {
		const editor = window.wp.data.select( 'core/editor' );
		const blockEditor = window.wp.data.select( 'core/block-editor' );
		const eventInfo = blockEditor.getBlocks().find( ( b ) => b.name === 'simple-events/event-info' );
		return {
			postId: editor.getCurrentPostId(),
			status: editor.getEditedPostAttribute( 'status' ),
			isDirty: editor.isEditedPostDirty(),
			isSaving: editor.isSavingPost(),
			isAutosaving: editor.isAutosavingPost(),
			dirtyRecords: editor.__experimentalGetDirtyEntityRecords
				? editor.__experimentalGetDirtyEntityRecords()
				: null,
			eventDates: eventInfo ? eventInfo.attributes.eventDates : null,
		};
	} );
}

/**
 * Start a network counter that tallies relevant POSTs.
 * Returns a `counts` object that mutates as requests arrive, plus a `requests`
 * array of {method, url, status} entries.
 *
 * @param {import('@playwright/test').Page} page
 */
function startNetworkCounter( page ) {
	const counts = { sync: 0, postSave: 0, autosave: 0 };
	const requests = [];

	page.on( 'request', ( req ) => {
		if ( req.method() !== 'POST' ) {
			return;
		}
		// wp-env uses plain permalinks by default → REST routes come through
		// /?rest_route=%2F...%2F (URL-encoded). Decode before matching so the
		// same regex works for both pretty-permalink and plain-permalink envs.
		const url = decodeURIComponent( req.url() );
		if ( /\/simple-events\/event-dates\/\d+\/sync/.test( url ) ) {
			counts.sync++;
			requests.push( { url, kind: 'sync' } );
		} else if ( /\/wp\/v2\/se-event\/\d+\/autosaves/.test( url ) ) {
			counts.autosave++;
			requests.push( { url, kind: 'autosave' } );
		} else if ( /\/wp\/v2\/se-event\/\d+(?:[?&]|$)/.test( url ) ) {
			counts.postSave++;
			requests.push( { url, kind: 'postSave' } );
		}
	} );

	return { counts, requests };
}

/**
 * Fetch the live list of child `se-event-date` posts for an event id via the
 * plugin's REST endpoint. Uses the page's auth cookies so it works without
 * setting up an application password.
 *
 * @param {import('@playwright/test').Page} page
 * @param {number} eventId
 */
async function fetchChildDates( page, eventId ) {
	return page.evaluate( async ( id ) => {
		return await window.wp.apiFetch( {
			path: `/simple-events/event-dates/${ id }`,
		} );
	}, eventId );
}

module.exports = {
	openNewEvent,
	setTitle,
	clickAddDate,
	clickDone,
	publish,
	waitForSaveQuiet,
	readEditorState,
	startNetworkCounter,
	fetchChildDates,
};
