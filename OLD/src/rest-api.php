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
function se_register_rest_routes() {

	if ( class_exists( 'WooCommerce' ) ) {
		require_once SE_SRC_PATH . '/classes/class-se-rest-ticket-products.php';

		$instance = new SE_REST_Ticket_Products();
		$instance->register_routes();
	}

	SE_Calendar::get_instance()->register_routes();
}

add_action( 'rest_api_init', 'se_register_rest_routes', 10 );
