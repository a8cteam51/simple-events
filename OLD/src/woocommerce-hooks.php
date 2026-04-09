<?php
/**
 * WooCommerce hooks.
 *
 * @package simple-events
 */

/**
 * Autocomplete order if all the items are tickets.
 *
 * @param integer $order_id The ID of the order.
 *
 * @return void
 */
function se_autocomplete_ticket_order( $order_id ) {
	$se_options           = get_option( 'se_options' );
	$perform_autocomplete = isset( $se_options['autocomplete_ticket_order'] ) ? $se_options['autocomplete_ticket_order'] : false;

	// If setting is not enabled, return.
	if ( ! $perform_autocomplete ) {
		return;
	}

	$order        = wc_get_order( $order_id );
	$order_status = $order->get_status();

	// If order is already completed or isn't being processed, return.
	if ( 'completed' === $order_status || 'processing' !== $order_status ) {
		return;
	}

	$items               = $order->get_items();
	$should_autocomplete = true;

	// Check if order contains only tickets.
	foreach ( $items as $item ) {
		if ( ! wc_box_office_is_product_ticket( $item['product_id'] ) ) {
			$should_autocomplete = false;
		}
	}

	// Mark order as completed if it contains only tickets.
	if ( $should_autocomplete ) {
		$order->update_status( 'completed' );
	}
}


add_action( 'woocommerce_thankyou', 'se_autocomplete_ticket_order' );

/**
 * Empty cart before adding tickets.
 *
 * @param boolean $passed     The result of the validation.
 * @param integer $product_id The ID of the product.
 *
 * @return boolean
 */
function se_empty_cart_before_adding_tickets( $passed, $product_id ) {
	$se_options = get_option( 'se_options' );
	$empty_cart = isset( $se_options['empty_cart_before_adding_tickets'] ) ? $se_options['empty_cart_before_adding_tickets'] : false;

	if (
		$empty_cart &&
		wc_box_office_is_product_ticket( $product_id ) &&
		! WC()->cart->is_empty()
	) {
		WC()->cart->empty_cart();
	}

	return $passed;
}

add_filter( 'woocommerce_add_to_cart_validation', 'se_empty_cart_before_adding_tickets', 10, 2 );

/**
 * Redirect to checkout if skip cart is enabled.
 *
 * @param string  $url     The URL to redirect to.
 * @param integer $product WC_Simple_Product object being added to the cart.
 *
 * @return string
 */
function se_get_checkout_url( $url, $product ) {
	$se_options = get_option( 'se_options' );
	$skip_cart  = isset( $se_options['skip_cart'] ) ? $se_options['skip_cart'] : false;

	if ( $skip_cart && wc_box_office_is_product_ticket( $product ) ) {
		$url = wc_get_checkout_url();
	}

	return $url;
}

add_filter( 'woocommerce_add_to_cart_redirect', 'se_get_checkout_url', 10, 2 );
