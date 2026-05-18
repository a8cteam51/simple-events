const { test, expect } = require( '@playwright/test' );
const { execSync } = require( 'child_process' );

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';

/**
 * The editor preview of the events Query Loop MUST show the same events,
 * in the same order, as the published front-end. Today it doesn't:
 *
 *  - front-end (build_query) switches the query to the se-event-date child
 *    posts and orders by real event date.
 *  - editor preview (set_admin_query on rest_se-event_query) leaves the
 *    query on the se-event PARENT posts but orders by se_event_date_start,
 *    a meta key that only exists on the children → WP falls back to
 *    post/created-date order → a different, usually older, set.
 *
 * This asymmetry is pure server-side, so it reproduces on any Gutenberg
 * version (unlike the feedType-param-forwarding quirk). The parity
 * assertion below is RED on current code and is the spec for the fix.
 */
test.describe( 'Query Loop events – editor/front parity', () => {
	let pageId;

	test.beforeAll( () => {
		const out = execSync(
			"npx wp-env run cli --env-cwd='wp-content/plugins/simple-events' -- wp eval-file tests/e2e/fixtures/seed-parity.php",
			{ encoding: 'utf8' }
		);
		const m = out.match( /(\d+)\s*$/m );
		if ( ! m ) {
			throw new Error( 'Seeder returned no page ID. Output:\n' + out );
		}
		pageId = m[ 1 ];
	} );

	/** Ordered, de-duplicated sequence of PARITY event numbers from a
	 * container's text. Array (not Set) preserves render order; first-seen
	 * dedupe collapses the nested block-wrapper text repeats. */
	function sequenceFrom( text ) {
		const re = /PARITY (\d{2})/g;
		const seen = new Set();
		const seq = [];
		let m;
		while ( ( m = re.exec( text || '' ) ) ) {
			if ( ! seen.has( m[ 1 ] ) ) {
				seen.add( m[ 1 ] );
				seq.push( m[ 1 ] );
			}
		}
		return seq;
	}

	async function frontSequence( page ) {
		await page.goto( `${ BASE_URL }/?page_id=${ pageId }` );
		const txt = await page
			.locator( '.wp-block-query' )
			.first()
			.innerText();
		return sequenceFrom( txt );
	}

	async function editorSequence( page ) {
		await page.goto( `${ BASE_URL }/wp-admin/post.php?post=${ pageId }&action=edit` );
		await page.waitForFunction( () =>
			window.wp?.data
				?.select( 'core/block-editor' )
				?.getBlocks()
				?.some( ( b ) => b.name === 'core/query' )
		);
		await page.evaluate( () => {
			const prefs = window.wp.data.dispatch( 'core/preferences' );
			prefs && prefs.set && prefs.set( 'core/edit-post', 'welcomeGuide', false );
		} );
		// Wait for the query preview to resolve some PARITY items.
		await expect
			.poll(
				async () =>
					sequenceFrom(
						await page
							.locator( '.wp-block-query' )
							.first()
							.innerText()
							.catch( () => '' )
					).length,
				{ timeout: 15000 }
			)
			.toBeGreaterThan( 0 );
		const txt = await page
			.locator( '.wp-block-query' )
			.first()
			.innerText();
		return sequenceFrom( txt );
	}

	test( 'editor preview matches the front-end (default feed)', async ( { page } ) => {
		const front = await frontSequence( page );
		const editor = await editorSequence( page );

		// Sanity: front-end page 1 has 6 events (perPage 6) from the 14 seeded.
		expect( front ).toHaveLength( 6 );

		// The real assertion: the editor preview's page 1 must be the SAME
		// events in the SAME order as the front-end. RED if the editor query
		// path selects/orders differently from build_query.
		expect( editor ).toEqual( front );
	} );
} );
