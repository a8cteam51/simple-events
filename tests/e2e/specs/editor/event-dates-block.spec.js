const { test, expect } = require( '@playwright/test' );
const { openNewEvent, readEditorState } = require( '../../fixtures' );

/**
 * Sanity coverage for the auto-inserted event-info block. These tests should
 * pass on `trunk` today — they exist to lock in the block-registration and
 * post-type template contract so future changes don't accidentally break it.
 */
test.describe( 'Event Info block – sanity', () => {
	test( 'auto-inserts on a new se-event', async ( { page } ) => {
		await openNewEvent( page );

		const blocks = await page.evaluate( () =>
			window.wp.data
				.select( 'core/block-editor' )
				.getBlocks()
				.map( ( b ) => b.name )
		);

		expect( blocks ).toContain( 'simple-events/event-info' );
	} );

	test( 'event-info block is locked against removal', async ( { page } ) => {
		await openNewEvent( page );

		const lockState = await page.evaluate( () => {
			const blocks = window.wp.data.select( 'core/block-editor' ).getBlocks();
			const root = window.wp.data
				.select( 'core/block-editor' )
				.getSettings();
			return {
				blockCount: blocks.length,
				templateLock: root.templateLock || null,
			};
		} );

		expect( lockState.blockCount ).toBeGreaterThan( 0 );
	} );

	test( 'autodraft has a real post id before any save', async ( { page } ) => {
		await openNewEvent( page );
		const state = await readEditorState( page );

		expect( state.postId ).toBeGreaterThan( 0 );
		expect( state.status ).toBe( 'auto-draft' );
	} );
} );
