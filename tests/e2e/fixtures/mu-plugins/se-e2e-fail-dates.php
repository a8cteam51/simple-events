<?php
/**
 * Plugin Name: SE E2E — fail the event-dates endpoint
 *
 * Test fixture. Fails GET /simple-events/event-dates/{id} with a 500 whenever the
 * request carries the cookie `se_e2e_fail_dates`. A WP_Error and a thrown exception
 * are indistinguishable to the block — apiFetch rejects either way — so this uses
 * the one that doesn't write a stack trace to the debug log on every request.
 *
 * The failure has to be server-side: the editor block fetches its dates while
 * mounting, before any in-page script could patch apiFetch.
 *
 * Switch on from the browser console:
 *   document.cookie = 'se_e2e_fail_dates=1; path=/';
 * Switch off:
 *   document.cookie = 'se_e2e_fail_dates=; path=/; max-age=0';
 *
 * Drop the cookie check below to make it unconditional.
 *
 * @package Simple_Events
 */

add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		if ( empty( $_COOKIE['se_e2e_fail_dates'] ) ) {
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
