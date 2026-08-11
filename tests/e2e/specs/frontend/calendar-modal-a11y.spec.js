const { test, expect } = require( '@playwright/test' );
const { execSync } = require( 'child_process' );

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';

/**
 * GH-88: the calendar event modal must be reachable without a mouse.
 *
 * calendar.js binds only 'mouseenter' and 'mouseleave' on the event title, so
 * there is no way to open the modal from the keyboard (WCAG 2.1.1), no way to
 * dismiss it with Escape (WCAG 1.4.13), and the container is a made-up <modal>
 * element carrying no dialog semantics for assistive technology.
 */

/**
 * Seed the calendar-with-modal page and return its ID.
 *
 * @return {string} The seeded page ID.
 */
function seedCalendarModal() {
	const out = execSync(
		`npx wp-env run cli --env-cwd='wp-content/plugins/simple-events' -- wp eval-file tests/e2e/fixtures/seed-calendar-modal.php`,
		{ encoding: 'utf8' }
	);
	const m = out.match( /(\d+)\s*$/m );
	if ( ! m ) {
		throw new Error( 'Seeder did not return a page ID. Output:\n' + out );
	}
	return m[ 1 ];
}

// calendar/day/event is rendered twice per event — once in the desktop grid
// (day/events.php) and once in the mobile list (mobile-events/day.php). Scope
// everything to the desktop grid, which is the copy a desktop viewport shows.
const GRID = '.simple-events-calendar-month__week';

test.describe( 'calendar event modal accessibility', () => {
	let pageId;

	test.beforeAll( () => {
		pageId = seedCalendarModal();
	} );

	test.beforeEach( async ( { page } ) => {
		await page.goto( `${ BASE_URL }/?page_id=${ pageId }` );
		await page
			.locator( '.simple-events-calendar-month__week' )
			.first()
			.waitFor();
	} );

	test( 'the panel is a real element with tooltip semantics', async ( {
		page,
	} ) => {
		// Confirms the fixture actually rendered its panels before asserting.
		await expect( page.locator( `${ GRID } .se-event-modal` ) ).toHaveCount( 2 );

		await expect(
			page.locator( `${ GRID } modal` ),
			'<modal> is not an HTML element and carries no semantics'
		).toHaveCount( 0 );

		// role=tooltip, not dialog: focus stays on the trigger, which is what
		// the W3C tooltip pattern describes and what calendar.js implements.
		await expect(
			page.locator( `${ GRID } .se-event-modal[role="tooltip"]` )
		).toHaveCount( 2 );
	} );

	test( 'each title describes itself by its own panel', async ( { page } ) => {
		const titles = page.locator(
			`${ GRID } .simple-events-calendar-month__calendar-event-title a`
		);

		await expect( titles ).toHaveCount( 2 );

		for ( let i = 0; i < 2; i++ ) {
			const describedBy = await titles
				.nth( i )
				.getAttribute( 'aria-describedby' );

			expect( describedBy, 'the title must point at a panel' ).toBeTruthy();
			await expect(
				page.locator( `#${ describedBy }` ),
				`aria-describedby="${ describedBy }" must resolve to one element`
			).toHaveCount( 1 );
		}
	} );

	test( 'the modal opens from the keyboard', async ( { page } ) => {
		const title = page
			.locator( `${ GRID } .simple-events-calendar-month__calendar-event-title a` )
			.first();
		const modal = page.locator( `${ GRID } .se-event-modal` ).first();

		await expect( modal ).toBeHidden();

		await title.focus();

		await expect(
			modal,
			'focusing the event title should reveal its modal'
		).toBeVisible();
	} );

	test( 'Escape closes the modal', async ( { page } ) => {
		const title = page
			.locator( `${ GRID } .simple-events-calendar-month__calendar-event-title a` )
			.first();
		const modal = page.locator( `${ GRID } .se-event-modal` ).first();

		await title.focus();
		await expect( modal ).toBeVisible();

		await page.keyboard.press( 'Escape' );

		await expect( modal, 'Escape should dismiss the modal' ).toBeHidden();
	} );

	test( 'moving to another title does not strand the first modal', async ( {
		page,
	} ) => {
		const titles = page.locator(
			`${ GRID } .simple-events-calendar-month__calendar-event-title a`
		);
		const modals = page.locator( `${ GRID } .se-event-modal` );

		await expect( titles ).toHaveCount( 2 );

		await titles.nth( 0 ).focus();
		await expect( modals.nth( 0 ) ).toBeVisible();

		// Straight to the next title, inside the 150ms hide delay.
		await titles.nth( 1 ).focus();

		await expect( modals.nth( 1 ) ).toBeVisible();
		await expect(
			modals.nth( 0 ),
			'the modal we moved away from must not be left over the grid'
		).toBeHidden();
	} );

	test( 'Escape closes the modal that is actually open', async ( {
		page,
	} ) => {
		const titles = page.locator(
			`${ GRID } .simple-events-calendar-month__calendar-event-title a`
		);
		const modals = page.locator( `${ GRID } .se-event-modal` );

		await titles.nth( 0 ).focus();
		await titles.nth( 1 ).focus();
		await page.keyboard.press( 'Escape' );

		await expect( modals.nth( 0 ) ).toBeHidden();
		await expect( modals.nth( 1 ) ).toBeHidden();
	} );

	test( 'the modal still opens on hover', async ( { page } ) => {
		const title = page
			.locator( `${ GRID } .simple-events-calendar-month__calendar-event-title a` )
			.first();
		const modal = page.locator( `${ GRID } .se-event-modal` ).first();

		await expect( modal ).toBeHidden();

		await title.hover();

		await expect(
			modal,
			'the existing mouse behaviour must survive the fix'
		).toBeVisible();
	} );
} );
