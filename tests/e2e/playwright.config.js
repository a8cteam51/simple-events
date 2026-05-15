const { defineConfig, devices } = require( '@playwright/test' );
const path = require( 'path' );
require( 'dotenv' ).config( { path: path.join( __dirname, '.env' ) } );

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';

module.exports = defineConfig( {
	testDir: __dirname,
	outputDir: path.join( __dirname, '../../test-results' ),
	fullyParallel: false,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: 1,
	timeout: 60 * 1000,
	reporter: [
		[ 'html', { outputFolder: path.join( __dirname, '../../playwright-report' ), open: 'never' } ],
		[ 'list' ],
	],
	use: {
		baseURL: BASE_URL,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
		actionTimeout: 15 * 1000,
		navigationTimeout: 30 * 1000,
	},
	projects: [
		{
			name: 'setup',
			testMatch: /global-setup\.js$/,
		},
		{
			name: 'chromium',
			testMatch: /specs\/.*\.spec\.js$/,
			use: {
				...devices[ 'Desktop Chrome' ],
				storageState: path.join( __dirname, 'artifacts/storage-states/admin.json' ),
				launchOptions: {
					slowMo: process.env.SLOWMO ? parseInt( process.env.SLOWMO, 10 ) : 0,
				},
			},
			dependencies: [ 'setup' ],
		},
	],
} );
