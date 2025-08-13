<?php
/**
 * REST API Products controller customized for Event Tickets Block.
 *
 * Handles requests to the /tickets endpoint. This endpoint allows read-only access to editors.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Tickets controller class.
 */
class SE_REST_Ticket_Products extends WC_REST_Products_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'simple-events';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'tickets';

	/**
	 * Register the routes for products.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Check if a given request has access to read items.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot list resources.', 'simple-events' ), array( 'status' => \rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access to read an item.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|boolean
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot view this resource.', 'simple-events' ), array( 'status' => \rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Change REST API permissions so that authors have access to this API.
	 *
	 * This code only runs for methods of this class. @see Products::get_items below.
	 *
	 * @param boolean $permission Does the current user have access to the API.
	 *
	 * @return boolean
	 */
	public function force_edit_posts_permission( $permission ) {
		// If user has access already, we can bypass additonal checks.
		if ( $permission ) {
			return $permission;
		}

		return current_user_can( 'edit_posts' );
	}

	/**
	 * Get a collection of posts.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		add_filter( 'woocommerce_rest_check_permissions', array( $this, 'force_edit_posts_permission' ) );
		$response = parent::get_items( $request );
		remove_filter( 'woocommerce_rest_check_permissions', array( $this, 'force_edit_posts_permission' ) );

		return $response;
	}

	/**
	 * Make extra product orderby features supported by WooCommerce available to the WC API.
	 * This includes 'price', 'popularity', and 'rating'.
	 *
	 * @param WP_REST_Request $request Request data.
	 *
	 * @return array
	 */
	protected function prepare_objects_query( $request ) {
		$args = parent::prepare_objects_query( $request );

		$args['meta_query'][] = array(
			'key'     => '_ticket',
			'value'   => 'yes',
			'compare' => '=',
		);

		return $args;
	}

	/**
	 * Get product data.
	 *
	 * @param \WC_Product|\WC_Product_Variation $product Product instance.
	 * @param string                            $context Request context. Options: 'view' and 'edit'.
	 *
	 * @return array
	 */
	protected function get_product_data( $product, $context = 'view' ) {
		return array(
			'id'             => $product->get_id(),
			'name'           => $product->get_title(),
			'variation'      => $product->is_type( 'variation' ) ? wc_get_formatted_variation( $product, true, true, false ) : '',
			'permalink'      => $product->get_permalink(),
			'sku'            => $product->get_sku(),
			'description'    => apply_filters( 'woocommerce_short_description', $product->get_short_description() ? $product->get_short_description() : wc_trim_string( $product->get_description(), 400 ) ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			'onsale'         => $product->is_on_sale(),
			'price'          => $product->get_price(),
			'price_html'     => $product->get_price_html(),
			'has_options'    => $product->has_options(),
			'is_purchasable' => $product->is_purchasable(),
			'is_in_stock'    => $product->is_in_stock(),
		);
	}

	/**
	 * Get the Product's schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'simple_events_ticket_products',
			'type'       => 'object',
			'properties' => array(
				'id'             => array(
					'description' => __( 'Unique identifier for the resource.', 'simple-events' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'name'           => array(
					'description' => __( 'Product name.', 'simple-events' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'variation'      => array(
					'description' => __( 'Product variation attributes, if applicable.', 'simple-events' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'permalink'      => array(
					'description' => __( 'Product URL.', 'simple-events' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'sku'            => array(
					'description' => __( 'Unique identifier.', 'simple-events' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'onsale'         => array(
					'description' => __( 'Is the product on sale?', 'simple-events' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'description'    => array(
					'description' => __( 'Short description or excerpt from description.', 'simple-events' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'price'          => array(
					'description' => __( 'Current product price.', 'simple-events' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'price_html'     => array(
					'description' => __( 'Price formatted in HTML.', 'simple-events' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'has_options'    => array(
					'description' => __( 'Does the product have options?', 'simple-events' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'is_purchasable' => array(
					'description' => __( 'Is the product purchasable?', 'simple-events' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'is_in_stock'    => array(
					'description' => __( 'Is the product in stock?', 'simple-events' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}
}
