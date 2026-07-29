<?php
/**
 * REST API.
 *
 * Register routes for custom endpoints.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register REST API routes.
 *
 * @return void
 */
function simple_events_register_rest_routes() {

	if ( class_exists( 'WooCommerce' ) ) {
		require_once SIMPLE_EVENTS_SRC_PATH . '/classes/class-se-rest-ticket-products.php';

		$instance = new Simple_Events_REST_Ticket_Products();
		$instance->register_routes();
	}

	Simple_Events_Calendar::get_instance()->register_routes();
}

add_action( 'rest_api_init', 'simple_events_register_rest_routes', 10 );
