const { test, expect } = require( '@playwright/test' );
const { execSync } = require( 'child_process' );
const path = require( 'path' );

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';

/**
 * Front-end coverage for the loop-event-info enhancements on
 * feature/the-pocket-features:
 *   - tagName  → wrapper element is <h2>, not <div>
 *   - dateFormat override → date renders as Y/m/d, not the site default
 *   - query offset → 3 events seeded, offset:1 ⇒ exactly 2 rendered
 *
 * Seeds via wp-env cli (PHP seeder), then asserts the rendered page DOM.
 */
test.describe( 'loop-event-info front-end render', () => {
	let pageId;

	test.beforeAll( () => {
		const out = execSync(
			"npx wp-env run cli --env-cwd='wp-content/plugins/simple-events' -- wp eval-file tests/e2e/fixtures/seed-loop.php",
			{ encoding: 'utf8' }
		);
		const m = out.match( /(\d+)\s*$/m );
		if ( ! m ) {
			throw new Error( 'Seeder did not return a page ID. Output:\n' + out );
		}
		pageId = m[ 1 ];
	} );

	test( 'tagName, dateFormat override and query offset all apply', async ( { page } ) => {
		await page.goto( `${ BASE_URL }/?page_id=${ pageId }` );

		const blocks = page.locator( 'h2.wp-block-simple-events-loop-event-info' );

		// Screenshot for the eyeball check.
		await page.screenshot( {
			path: path.join( __dirname, '../../../../test-results/loop-event-info.png' ),
			fullPage: true,
		} );

		// offset:1 of 3 seeded ⇒ exactly 2 rendered.
		await expect( blocks ).toHaveCount( 2 );

		// tagName=h2 → every loop-event-info is an <h2> (selector already
		// asserts the tag; this confirms the wrapper class is present too).
		const count = await blocks.count();
		expect( count ).toBe( 2 );

		// dateFormat="Y/m/d" → each rendered date matches \d{4}/\d{2}/\d{2}
		// and NOT the site default (e.g. "May 25, 2026").
		for ( let i = 0; i < count; i++ ) {
			const text = ( await blocks.nth( i ).innerText() ).trim();
			expect( text, `block ${ i } date format` ).toMatch( /\d{4}\/\d{2}\/\d{2}/ );
			expect( text, `block ${ i } not site-default format` ).not.toMatch( /[A-Za-z]{3,}/ );
		}
	} );
} );
