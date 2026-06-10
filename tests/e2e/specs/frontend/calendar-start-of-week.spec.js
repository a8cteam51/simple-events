const { test, expect } = require( '@playwright/test' );
const { execSync } = require( 'child_process' );
const path = require( 'path' );

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';

const WEEKDAYS = [
	'Sunday',
	'Monday',
	'Tuesday',
	'Wednesday',
	'Thursday',
	'Friday',
	'Saturday',
];

// Regression cases for the calendar day-of-week fix (PR #76). The old
// Monday-only offset maths broke Sunday- and Saturday-start weeks, so those are
// the representative values exercised here alongside the Monday default.
const START_OF_WEEKS = [ 0, 1, 6 ]; // Sunday, Monday, Saturday.

/**
 * Seed the calendar page for a given start_of_week and return its page ID.
 *
 * @param {number} startOfWeek 0 (Sunday) through 6 (Saturday).
 * @return {string} The seeded page ID.
 */
function seedCalendar( startOfWeek ) {
	const out = execSync(
		`npx wp-env run cli --env-cwd='wp-content/plugins/simple-events' -- wp eval-file tests/e2e/fixtures/seed-calendar.php ${ startOfWeek }`,
		{ encoding: 'utf8' }
	);
	const m = out.match( /(\d+)\s*$/m );
	if ( ! m ) {
		throw new Error( 'Seeder did not return a page ID. Output:\n' + out );
	}
	return m[ 1 ];
}

/**
 * Weekday (0=Sun..6=Sat) for a yyyy-mm-dd datetime attribute, read in UTC so
 * the test machine's timezone can't shift the day.
 *
 * @param {string} datetime A `Y-m-d` string from a <time datetime> attribute.
 * @return {number} Weekday index.
 */
function weekdayOf( datetime ) {
	return new Date( `${ datetime }T00:00:00Z` ).getUTCDay();
}

test.describe( 'calendar grid respects start_of_week', () => {
	for ( const startOfWeek of START_OF_WEEKS ) {
		test( `start_of_week=${ startOfWeek } (${ WEEKDAYS[ startOfWeek ] }) aligns header and grid`, async ( {
			page,
		} ) => {
			const pageId = seedCalendar( startOfWeek );
			await page.goto( `${ BASE_URL }/?page_id=${ pageId }` );

			// Wait for the server-rendered grid (event seeded today ⇒ weeks render).
			await page
				.locator( '.simple-events-calendar-month__week' )
				.first()
				.waitFor();

			// --- Header columns: 7 columns, rotated to start on start_of_week. ---
			const headerLabels = await page
				.locator( '.simple-events-calendar-month__header-column' )
				.evaluateAll( ( cols ) =>
					cols.map( ( c ) => c.getAttribute( 'aria-label' ) )
				);

			expect( headerLabels, 'header should have 7 columns' ).toHaveLength(
				7
			);
			const expectedHeader = Array.from(
				{ length: 7 },
				( _, i ) => WEEKDAYS[ ( startOfWeek + i ) % 7 ]
			);
			expect(
				headerLabels,
				'header columns should be rotated to start_of_week'
			).toEqual( expectedHeader );

			// --- Grid rows: each week has exactly 7 day cells. ---
			const weekCellCounts = await page
				.locator( '.simple-events-calendar-month__week' )
				.evaluateAll( ( weeks ) =>
					weeks.map(
						( w ) =>
							w.querySelectorAll(
								'[data-js="simple-events-calendar-day"]'
							).length
					)
				);
			expect( weekCellCounts.length ).toBeGreaterThan( 0 );
			for ( const count of weekCellCounts ) {
				expect( count, 'each week should have 7 cells' ).toBe( 7 );
			}

			// --- Day cells in DOM order, each with its datetime + other-month flag. ---
			const cells = await page
				.locator(
					'.simple-events-calendar-month__body [data-js="simple-events-calendar-day"]'
				)
				.evaluateAll( ( els ) =>
					els.map( ( el ) => ( {
						datetime: el
							.querySelector( 'time' )
							.getAttribute( 'datetime' ),
						otherMonth: el.classList.contains(
							'simple-events-calendar-month__day--other-month'
						),
					} ) )
				);

			// Whole number of weeks.
			expect(
				cells.length % 7,
				'grid should be a whole number of weeks'
			).toBe( 0 );

			// First cell sits in the start_of_week column.
			expect(
				weekdayOf( cells[ 0 ].datetime ),
				'first grid cell weekday should equal start_of_week'
			).toBe( startOfWeek );

			// Last cell closes the week.
			expect(
				weekdayOf( cells[ cells.length - 1 ].datetime ),
				'last grid cell weekday should be start_of_week + 6'
			).toBe( ( startOfWeek + 6 ) % 7 );

			// First in-month day (the 1st of the displayed month) lands in the
			// column predicted by the fix: (weekday - start_of_week + 7) % 7.
			const firstInMonthIndex = cells.findIndex( ( c ) => ! c.otherMonth );
			expect(
				firstInMonthIndex,
				'grid should contain in-month days'
			).toBeGreaterThanOrEqual( 0 );

			const firstInMonth = cells[ firstInMonthIndex ];
			expect(
				firstInMonthIndex % 7,
				`1st of month (${ firstInMonth.datetime }) should sit in its expected column`
			).toBe(
				( weekdayOf( firstInMonth.datetime ) - startOfWeek + 7 ) % 7
			);
		} );
	}
} );
