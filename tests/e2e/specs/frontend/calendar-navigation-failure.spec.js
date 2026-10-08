const { test, expect } = require( '@playwright/test' );
const { execSync } = require( 'child_process' );

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';

/**
 * GH-102: a failed month-navigation request must not re-bind the calendar's
 * click listeners.
 *
 * sendCalendarRequest() calls initListeners() from .finally(), so it also runs
 * when the request fails. A failure leaves the navigation buttons and day
 * cells in the DOM, and each of them gained one more click listener per failed
 * request: the next click sent two requests, then four, then eight. On mobile
 * the doubled day listener toggled a day twice, so a tap appeared to do nothing.
 *
 * The "This Month" buttons are used because they are always enabled, whatever
 * events surround the seeded month.
 */

/**
 * Seed a calendar page and return its ID.
 *
 * @return {string} The seeded page ID.
 */
function seedCalendar() {
	const out = execSync(
		`npx wp-env run cli --env-cwd='wp-content/plugins/simple-events' -- wp eval-file tests/e2e/fixtures/seed-calendar.php 1`,
		{ encoding: 'utf8' }
	);
	const m = out.match( /(\d+)\s*$/m );
	if ( ! m ) {
		throw new Error( 'Seeder did not return a page ID. Output:\n' + out );
	}
	return m[ 1 ];
}

test.describe( 'calendar navigation after a failed request', () => {
	let pageId;
	let requests;

	test.beforeAll( () => {
		pageId = seedCalendar();
	} );

	test.beforeEach( async ( { page } ) => {
		requests = 0;

		// Matches the route under pretty and plain (?rest_route=) permalinks.
		await page.route( /simple-events(\/|%2F)calendar/, ( route ) => {
			requests++;
			return route.abort();
		} );

		await page.goto( `${ BASE_URL }/?page_id=${ pageId }` );
	} );

	test( 'each click sends one request, however many have failed', async ( {
		page,
	} ) => {
		const thisMonth = page
			.locator( '.simple-events-top-bar__today-button' )
			.first();
		const content = page
			.locator( '[data-js="simple-events-calendar-content"]' )
			.first();

		for ( let clicks = 1; clicks <= 4; clicks++ ) {
			await thisMonth.click();

			// The content is shown again once the failed request has settled.
			await expect( content ).not.toHaveClass(
				/simple-events-calendar-content--hidden/
			);

			expect(
				requests,
				`total requests after ${ clicks } click(s)`
			).toBe( clicks );
		}
	} );

	test( 'one tap toggles the selected day on mobile', async ( { page } ) => {
		await page.setViewportSize( { width: 390, height: 844 } );

		const content = page
			.locator( '[data-js="simple-events-calendar-content"]' )
			.first();
		// The seeded event is today, and today is rendered selected.
		const today = page
			.locator( '.simple-events-calendar-month__day--today' )
			.first();

		await page
			.locator( '.simple-events-mobile__today-button' )
			.first()
			.click();
		await expect( content ).not.toHaveClass(
			/simple-events-calendar-content--hidden/
		);

		await expect( today ).toHaveClass(
			/simple-events-calendar-month__day--active/
		);
		await today.click();

		await expect(
			today,
			'one tap must deselect the day, not deselect and reselect it'
		).not.toHaveClass( /simple-events-calendar-month__day--active/ );
	} );
} );
