const { test, expect } = require( '@playwright/test' );
const { execSync } = require( 'child_process' );

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';
const ARCHIVE_URL = `${ BASE_URL }/?post_type=se-event`;

/**
 * GH-87 — a block must render the same on the events archive as it does on an
 * ordinary page.
 *
 * pre_get_posts() attaches posts_where, the_posts and posts_orderby
 * (class-se-event-post-type.php:642-652) and never detaches them, so a block
 * rendering later in that request inherits them. modify_event_posts() then
 * remaps the block's results onto their parent events, and the block shows the
 * wrong thing.
 *
 * The countdown case is the one with a visible symptom: on the archive it
 * counted to an event in the past and rendered all zeroes.
 *
 * Each block gets its own page (one block per page, so one block's filter
 * changes cannot carry into the next) and is rendered on the archive by the
 * probe mu-plugin via ?se-probe=<block>.
 */
test.describe( 'archive block parity', () => {
	let pages;

	test.beforeAll( () => {
		const out = execSync(
			"npx wp-env run cli --env-cwd='wp-content/plugins/simple-events' -- wp eval-file tests/e2e/fixtures/seed-archive-blocks.php",
			{ encoding: 'utf8' }
		);
		const m = out.match( /\{.*\}/s );
		if ( ! m ) {
			throw new Error( 'Seeder did not return a page map. Output:\n' + out );
		}
		pages = JSON.parse( m[ 0 ] );
	} );

	/**
	 * The event titles a block rendered, in order.
	 *
	 * @param {object} page  Playwright page.
	 * @param {string} scope Selector to read within.
	 * @return {Promise<string[]>} Titles.
	 */
	async function titles( page, scope ) {
		// Event links point at ?se-event=<slug>; the theme's navigation links at
		// ?page_id=, so this ignores the nav entirely. Each event also has a
		// second, textless link for its thumbnail, dropped by the empty filter.
		return (
			await page
				.locator( `${ scope } a[href*="se-event="]` )
				.allInnerTexts()
		)
			.map( ( t ) => t.trim() )
			// Archive entries also carry a dated permalink pointing at the same
			// event, so match the seeded titles rather than every event link.
			.filter( ( t ) => t.startsWith( 'E2EBLOCK' ) );
	}

	test( 'countdown counts to the same event on the archive as on a page', async ( {
		page,
	} ) => {
		await page.goto( `${ BASE_URL }/?page_id=${ pages.countdown }` );
		const onPage = await page
			.locator( '#event-timer' )
			.getAttribute( 'data-event-start-date' );

		// A real, future timestamp — otherwise both sides could be wrong alike.
		expect( Number( onPage ) ).toBeGreaterThan( Date.now() );

		await page.goto( `${ ARCHIVE_URL }&se-probe=countdown` );
		const onArchive = await page
			.locator( '#se-probe #event-timer' )
			.getAttribute( 'data-event-start-date' );

		expect( onArchive ).toBe( onPage );
	} );

	test( 'the countdown on the archive does not read 00 00 00 00', async ( {
		page,
	} ) => {
		await page.goto( `${ ARCHIVE_URL }&se-probe=countdown` );

		const scope = '#se-probe .event-timer__time-';
		const parts = {};

		for ( const unit of [ 'days', 'hours', 'minutes', 'seconds' ] ) {
			parts[ unit ] = (
				await page.locator( `${ scope }${ unit }` ).innerText()
			).trim();
		}

		// A countdown to an event in the past renders every field as 00.
		expect(
			Object.values( parts ).join( ' ' ),
			'countdown is fully expired'
		).not.toBe( '00 00 00 00' );

		expect( Number( parts.days ) ).toBeGreaterThan( 0 );
	} );

	test( 'the archive lists the past events', async ( { page } ) => {
		await page.goto( ARCHIVE_URL );
		const listed = await titles( page, 'body' );

		expect( listed ).toContain( 'E2EBLOCK PAST A' );
		expect( listed ).toContain( 'E2EBLOCK PAST B' );
	} );

	test( 'the archive lists the future events', async ( { page } ) => {
		await page.goto( ARCHIVE_URL );
		const listed = await titles( page, 'body' );

		expect( listed ).toContain( 'E2EBLOCK FUTURE A' );
		expect( listed ).toContain( 'E2EBLOCK FUTURE B' );
	} );

	test( 'the archive lists every event once, oldest first', async ( {
		page,
	} ) => {
		await page.goto( ARCHIVE_URL );
		const listed = await titles( page, 'body' );

		// One entry per event: the multi-date event must not repeat, and the
		// order follows each event's next unfinished date.
		expect( listed ).toEqual( [
			'E2EBLOCK PAST A',
			'E2EBLOCK PAST B',
			'E2EBLOCK MULTI',
			'E2EBLOCK FUTURE A',
			'E2EBLOCK FUTURE B',
		] );
	} );

	test( 'the calendar lists the same events on the archive as on a page', async ( {
		page,
	} ) => {
		await page.goto( `${ BASE_URL }/?page_id=${ pages.calendar }` );
		const onPage = await titles( page, 'body' );

		expect( onPage.length ).toBeGreaterThan( 0 );

		await page.goto( `${ ARCHIVE_URL }&se-probe=calendar` );
		const onArchive = await titles( page, '#se-probe' );

		expect( onArchive ).toEqual( onPage );
	} );

	/**
	 * A query loop renders its titles through wp:post-title, which is a plain
	 * heading with no link, so titles() cannot read it.
	 *
	 * @param {object} page Playwright page.
	 * @return {Promise<string[]>} Titles.
	 */
	async function queryLoopTitles( page ) {
		return (
			await page.locator( '#se-probe .wp-block-post-title' ).allInnerTexts()
		)
			.map( ( t ) => t.trim() )
			.filter( ( t ) => t.startsWith( 'E2EBLOCK' ) );
	}

	test( 'the query loop on the archive lists every event', async ( {
		page,
	} ) => {
		await page.goto( `${ ARCHIVE_URL }&se-probe=query-loop` );

		expect( await queryLoopTitles( page ) ).toEqual( [
			'E2EBLOCK PAST A',
			'E2EBLOCK PAST B',
			'E2EBLOCK MULTI',
			'E2EBLOCK FUTURE A',
			'E2EBLOCK FUTURE B',
		] );
	} );

	test( 'upcoming events on the archive lists only events still to come', async ( {
		page,
	} ) => {
		await page.goto( `${ ARCHIVE_URL }&se-probe=upcoming-events` );
		const listed = await titles( page, '#se-probe' );

		// MULTI still has dates in 2035, so it belongs here; the two 2025-only
		// events do not.
		expect( listed ).toEqual( [
			'E2EBLOCK MULTI',
			'E2EBLOCK FUTURE A',
			'E2EBLOCK FUTURE B',
		] );

		expect( listed ).not.toContain( 'E2EBLOCK PAST A' );
		expect( listed ).not.toContain( 'E2EBLOCK PAST B' );
	} );

	test( 'upcoming events lists the same events on the archive as on a page', async ( {
		page,
	} ) => {
		await page.goto( `${ BASE_URL }/?page_id=${ pages[ 'upcoming-events' ] }` );
		const onPage = await titles( page, 'body' );

		expect( onPage.length ).toBeGreaterThan( 0 );

		await page.goto( `${ ARCHIVE_URL }&se-probe=upcoming-events` );
		const onArchive = await titles( page, '#se-probe' );

		expect( onArchive ).toEqual( onPage );
	} );
} );
