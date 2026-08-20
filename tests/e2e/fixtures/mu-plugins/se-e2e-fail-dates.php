<?php
/**
 * Plugin Name: SE E2E — fail the event-dates endpoint
 *
 * Test fixture. While the se_e2e_fail_dates option is set, GET
 * /simple-events/event-dates/{id} returns a 500. Toggled over plain HTTP so a
 * test (or curl) can flip it server-side:
 *
 *   Turn on:  /?se_e2e_fail_dates=true
 *   Turn off: /?se_e2e_fail_dates=false
 *
 * The toggle request prints "on"/"off" and exits. The failure has to be
 * server-side: the editor block fetches its dates while mounting, before any
 * in-page script could patch apiFetch.
 *
 * @package Simple_Events
 */

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- test fixture, test env only.

add_action(
	'init',
	function () {
		if ( ! isset( $_GET['se_e2e_fail_dates'] ) ) {
			return;
		}

		if ( 'true' === $_GET['se_e2e_fail_dates'] ) {
			update_option( 'se_e2e_fail_dates', '1' );
			die( 'on' );
		}

		delete_option( 'se_e2e_fail_dates' );
		die( 'off' );
	}
);

add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		if ( ! get_option( 'se_e2e_fail_dates' ) ) {
			return $result;
		}

		if ( 'GET' !== $request->get_method() ) {
			return $result;
		}

		// The dates read only, never the sync endpoint.
		if ( ! preg_match( '#^/simple-events/event-dates/\d+$#', $request->get_route() ) ) {
			return $result;
		}

		return new WP_Error(
			'se_e2e_forced_failure',
			'Forced failure for e2e testing.',
			array( 'status' => 500 )
		);
	},
	10,
	3
);
