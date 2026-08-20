<?php
/**
 * Lightweight REST route listing all ticket products for the Event Tickets block picker.
 *
 * The picker only consumes { id, name }, so this returns every published
 * ticket product from a single query — no WooCommerce product hydration —
 * cached in a transient that is flushed when products change. See issue #96.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ticket products list controller.
 */
class SE_REST_Ticket_Products_List {

	/**
	 * Transient holding the cached response payload.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'se_ticket_products_all';

	/**
	 * TTL backstop for the transient; normally invalidated by the hooks below.
	 *
	 * @var integer
	 */
	const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Hook the cache invalidation.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'save_post_product', array( __CLASS__, 'flush_cache' ) );
		add_action( 'save_post_product_variation', array( __CLASS__, 'flush_cache' ) );
		add_action( 'trashed_post', array( __CLASS__, 'maybe_flush_cache' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'maybe_flush_cache' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush_cache_on_delete' ), 10, 2 );

		// _ticket can change without a post save (importer, WP-CLI, meta-only CRUD save).
		add_action( 'added_post_meta', array( __CLASS__, 'maybe_flush_cache_on_meta' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'maybe_flush_cache_on_meta' ), 10, 3 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'maybe_flush_cache_on_meta' ), 10, 3 );
	}

	/**
	 * Register the /tickets/all route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'simple-events',
			'/tickets/all',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Same gate as SE_REST_Ticket_Products: editors and above.
	 *
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot list resources.', 'simple-events' ), array( 'status' => \rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Return all published ticket products as [ { id, name } ], from cache when warm.
	 *
	 * @return WP_REST_Response
	 */
	public function get_items() {
		$items = get_transient( self::CACHE_KEY );

		if ( false === $items ) {
			$items = $this->query_ticket_products();
			set_transient( self::CACHE_KEY, $items, self::CACHE_TTL );
		}

		return rest_ensure_response( $items );
	}

	/**
	 * Single query for every published ticket product, no found rows, no cache priming.
	 *
	 * @return array<array{id: integer, name: string}>
	 */
	private function query_ticket_products() {
		/**
		 * Bound the list so the transient payload stays under object-cache item limits.
		 *
		 * @param integer $limit Maximum number of ticket products returned.
		 */
		$limit = (int) apply_filters( 'se_ticket_products_all_limit', 2000 );

		$query = new WP_Query(
			array(
				'post_type'              => array( 'product', 'product_variation' ),
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_ticket',
						'value'   => 'yes',
						'compare' => '=',
					),
				),
			)
		);

		return array_map(
			static function ( $post ) {
				return array(
					'id'   => (int) $post->ID,
					'name' => $post->post_title,
				);
			},
			$query->posts
		);
	}

	/**
	 * Flush the cached list.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Flush when a trashed/untrashed post is a product or variation.
	 *
	 * @param integer $post_id The post ID.
	 *
	 * @return void
	 */
	public static function maybe_flush_cache( $post_id ) {
		if ( in_array( get_post_type( $post_id ), array( 'product', 'product_variation' ), true ) ) {
			self::flush_cache();
		}
	}

	/**
	 * Flush when the _ticket meta itself is added, updated or deleted.
	 *
	 * @param integer|array $meta_id  Meta row ID(s).
	 * @param integer       $post_id  The post ID.
	 * @param string        $meta_key The meta key.
	 *
	 * @return void
	 */
	public static function maybe_flush_cache_on_meta( $meta_id, $post_id, $meta_key ) {
		if ( '_ticket' === $meta_key ) {
			self::flush_cache();
		}
	}

	/**
	 * Flush when a deleted post was a product or variation.
	 *
	 * @param integer      $post_id The post ID.
	 * @param WP_Post|null $post    The deleted post object (WP 5.5+).
	 *
	 * @return void
	 */
	public static function flush_cache_on_delete( $post_id, $post = null ) {
		$post_type = $post instanceof WP_Post ? $post->post_type : get_post_type( $post_id );

		if ( in_array( $post_type, array( 'product', 'product_variation' ), true ) ) {
			self::flush_cache();
		}
	}
}

SE_REST_Ticket_Products_List::init();
