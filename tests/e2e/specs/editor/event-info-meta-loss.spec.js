const { test, expect } = require( '@playwright/test' );
const { canvas, openNewEvent, setTitle } = require( '../../fixtures' );

/**
 * Changing the event timezone must touch `se_event_timezone` and nothing else.
 *
 * `initializeDateManager()` (src/blocks/event-info/index.js) builds its metaSync
 * object from the post meta as it stood at mount, and event-manager.js
 * destructures `{ meta, setMeta }` once. `updateTimezone()` wrote
 * `{ ...meta, se_event_timezone }`, spreading that frozen snapshot — so every meta
 * key edited since mount was reverted to its mount-time value. `revertDates()`
 * had the same spread.
 */

/**
 * Every editor-visible meta key on `se-event` except `se_event_timezone`, which
 * updateTimezone legitimately owns.
 *
 * Each value differs from the key's registered default (see register_meta() in
 * class-se-event-post-type.php) — a value equal to the default would be restored
 * by the stale spread without showing up as a change, and would prove nothing.
 */
const SEEDED_META = {
	se_event_location: 'SEEDED LOCATION',
	se_event_venue: 'SEEDED VENUE',
	se_event_date_start: '1800000000',
	se_event_date_end: '1800003600',
	se_event_external_link: 'https://example.com/seeded',
	se_event_external_link_label: 'SEEDED LABEL',
	// Defaults false — seed true.
	se_event_display_timezone: true,
	se_event_hide_end_time: true,
	se_event_hide_start_time: true,
	se_event_add_calendar_links: true,
	se_event_open_in_new_window: true,
	se_open_external_link: true,
	// Defaults true — seed false.
	se_event_display_grouped: false,
	se_event_modal_access: false,
	se_show_modal_title: false,
	se_show_modal_excerpt: false,
	se_event_show_on_frontend: false,
};

/**
 * Read the edited (not yet saved) post meta via wp.data.
 *
 * @param {import('@playwright/test').Page} page
 */
async function readMeta( page ) {
	return page.evaluate(
		() =>
			window.wp.data.select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {}
	);
}

/**
 * Keys whose value differs between two meta snapshots.
 *
 * @param {Object} before
 * @param {Object} after
 */
function changedKeys( before, after ) {
	return [ ...new Set( [ ...Object.keys( before ), ...Object.keys( after ) ] ) ]
		.filter(
			( key ) =>
				JSON.stringify( before[ key ] ) !== JSON.stringify( after[ key ] )
		)
		.sort();
}

test( 'changing the timezone changes only se_event_timezone', async ( { page } ) => {
	await openNewEvent( page );
	await setTitle( page, 'E2E Timezone Meta Diff' );

	// The block renders its controls only once the dateManager has resolved —
	// which is also the point the stale meta snapshot is taken.
	await canvas( page ).locator( '.se-add-date-button' ).waitFor();

	// Select the block and open the Block inspector; the Time Zone combobox lives
	// in InspectorControls and renders only for the selected block.
	await page.evaluate( () => {
		const blocks = window.wp.data.select( 'core/block-editor' ).getBlocks();
		const block = blocks.find( ( b ) => b.name === 'simple-events/event-info' );
		window.wp.data.dispatch( 'core/block-editor' ).selectBlock( block.clientId );
		window.wp.data
			.dispatch( 'core/edit-post' )
			.openGeneralSidebar( 'edit-post/block' );
	} );

	const atMount = await readMeta( page );

	// Write the seed *after* the snapshot was captured. editPost merges, so this is
	// the same store write the block's own controls perform.
	await page.evaluate( ( meta ) => {
		window.wp.data.dispatch( 'core/editor' ).editPost( { meta } );
	}, SEEDED_META );

	await expect
		.poll( async () => ( await readMeta( page ) ).se_event_venue )
		.toBe( SEEDED_META.se_event_venue );

	const before = await readMeta( page );

	// Every seeded key must have actually moved off its mount-time value, or that
	// key contributes nothing. Catches a default changing under the test.
	Object.keys( SEEDED_META ).forEach( ( key ) => {
		expect(
			before[ key ],
			`${ key } must differ from its mount-time value to be meaningful`
		).not.toEqual( atMount[ key ] );
	} );

	// Fire the trigger through the real control. Take whichever suggestion the
	// combobox offers first rather than naming a zone, so the test does not depend
	// on the contents of the TIMEZONES list.
	const timezone = page.locator( '.se-timezone-label input' );
	await timezone.click();
	await timezone.press( 'ArrowDown' );
	await timezone.press( 'Enter' );

	// Guard against a false green: if the combobox silently failed to fire
	// updateTimezone, nothing would be overwritten and the diff would be empty.
	// updateTimezone is the only writer of se_event_timezone.
	await expect
		.poll( async () => ( await readMeta( page ) ).se_event_timezone )
		.not.toBe( before.se_event_timezone );

	const after = await readMeta( page );

	expect(
		changedKeys( before, after ),
		'updateTimezone must not write meta keys it does not own'
	).toEqual( [ 'se_event_timezone' ] );
} );
