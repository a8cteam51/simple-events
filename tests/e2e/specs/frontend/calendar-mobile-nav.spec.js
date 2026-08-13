const { test, expect } = require( '@playwright/test' );
const { execSync } = require( 'child_process' );

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';

/**
 * GH-88: the mobile calendar nav must announce the direction it moves.
 *
 * nav.php gives the previous-month item (rel="prev", data-date=previous_date,
 * left chevron) aria-label "Next month", and the next-month item "Previous
 * month". Sighted users follow the chevron; screen-reader users are told the
 * opposite and navigate backwards.
 *
 * The nav is in the DOM on desktop too — CSS hides it — so no mobile viewport
 * is needed to read its labels.
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

test.describe( 'calendar mobile nav labels', () => {
	let pageId;

	test.beforeAll( () => {
		pageId = seedCalendar();
	} );

	test.beforeEach( async ( { page } ) => {
		await page.goto( `${ BASE_URL }/?page_id=${ pageId }` );
		await page.locator( '.simple-events-mobile__nav' ).first().waitFor( {
			state: 'attached',
		} );
	} );

	test( 'the previous-month control is labelled "Previous month"', async ( {
		page,
	} ) => {
		const prev = page
			.locator( '.simple-events-mobile__nav-list-item--prev a' )
			.first();

		await expect( prev ).toHaveAttribute( 'rel', 'prev' );
		await expect(
			prev,
			'the control that moves back must not announce itself as "Next month"'
		).toHaveAttribute( 'aria-label', 'Previous month' );
		await expect( prev ).toHaveAttribute( 'title', 'Previous month' );
	} );

	test( 'the next-month control is labelled "Next month"', async ( {
		page,
	} ) => {
		const next = page
			.locator( '.simple-events-mobile__nav-list-item--next a' )
			.first();

		await expect( next ).toHaveAttribute( 'rel', 'next' );
		await expect(
			next,
			'the control that moves forward must not announce itself as "Previous month"'
		).toHaveAttribute( 'aria-label', 'Next month' );
		await expect( next ).toHaveAttribute( 'title', 'Next month' );
	} );

	test( 'the two controls do not share a label', async ( { page } ) => {
		const labels = await page
			.locator( '.simple-events-mobile__nav-list-item a' )
			.evaluateAll( ( links ) =>
				links.map( ( l ) => l.getAttribute( 'aria-label' ) )
			);

		expect( new Set( labels ).size, `got ${ JSON.stringify( labels ) }` ).toBe(
			labels.length
		);
	} );
} );
