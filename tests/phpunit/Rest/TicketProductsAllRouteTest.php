<?php
/**
 * Issue #96: the lightweight GET simple-events/tickets/all route.
 *
 * The Event Tickets block's product picker only consumes { id, name }, so the
 * route must return all published ticket products (posts with _ticket = yes)
 * as minimal objects from a single query, cached in a transient that is
 * flushed whenever a product is saved, trashed, untrashed or deleted.
 *
 * WooCommerce is not loaded in the test environment; the route is WC-free by
 * design, so the product post types are registered manually here.
 *
 * @package Simple_Events
 */
class TicketProductsAllRouteTest extends WP_UnitTestCase {

	/**
	 * Spin up a REST server, register product post types and start clean.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		register_post_type( 'product', array( 'public' => true ) );
		register_post_type( 'product_variation', array( 'public' => false ) );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		delete_transient( SE_REST_Ticket_Products_List::CACHE_KEY );
		wp_set_current_user( 0 );
	}

	/**
	 * Tear down the REST server and post types.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;

		unregister_post_type( 'product' );
		unregister_post_type( 'product_variation' );

		parent::tear_down();
	}

	/**
	 * Create a product post flagged as a Box Office ticket.
	 *
	 * @param string $title Product title.
	 * @param array  $args  Overrides for the post array.
	 *
	 * @return integer Post ID.
	 */
	private function create_ticket_product( $title, array $args = array() ) {
		$post_id = $this->factory->post->create(
			array_merge(
				array(
					'post_type'   => 'product',
					'post_status' => 'publish',
					'post_title'  => $title,
				),
				$args
			)
		);

		update_post_meta( $post_id, '_ticket', 'yes' );

		return $post_id;
	}

	/**
	 * Dispatch a GET request to the route.
	 *
	 * @return WP_REST_Response
	 */
	private function fetch_all() {
		return rest_do_request( new WP_REST_Request( 'GET', '/simple-events/tickets/all' ) );
	}

	/**
	 * Log in as a user with edit_posts.
	 *
	 * @return void
	 */
	private function login_as_contributor() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'contributor' ) ) );
	}

	/**
	 * Logged-out requests must be refused.
	 *
	 * @return void
	 */
	public function test_route_is_refused_when_logged_out() {
		$status = $this->fetch_all()->get_status();

		$this->assertContains( $status, array( 401, 403 ), "Logged-out request should be refused, got HTTP {$status}." );
	}

	/**
	 * edit_posts (Contributor) is enough, matching the existing tickets route.
	 *
	 * @return void
	 */
	public function test_route_is_allowed_for_contributor() {
		$this->login_as_contributor();

		$this->assertSame( 200, $this->fetch_all()->get_status() );
	}

	/**
	 * Only published ticket products come back, as bare id/name pairs, title ASC.
	 *
	 * @return void
	 */
	public function test_returns_only_published_ticket_products_as_id_name_pairs() {
		$beta  = $this->create_ticket_product( 'Beta Ticket' );
		$alpha = $this->create_ticket_product( 'Alpha Ticket' );

		// Noise that must be excluded.
		$this->create_ticket_product( 'Draft Ticket', array( 'post_status' => 'draft' ) );
		$this->factory->post->create(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'post_title'  => 'Plain Product',
			)
		);
		$page = $this->factory->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Ticket Page',
			)
		);
		update_post_meta( $page, '_ticket', 'yes' );

		$this->login_as_contributor();

		$this->assertSame(
			array(
				array(
					'id'   => $alpha,
					'name' => 'Alpha Ticket',
				),
				array(
					'id'   => $beta,
					'name' => 'Beta Ticket',
				),
			),
			$this->fetch_all()->get_data()
		);
	}

	/**
	 * Ticket variations are included alongside their parents.
	 *
	 * @return void
	 */
	public function test_includes_ticket_product_variations() {
		$variation = $this->create_ticket_product(
			'Show Ticket - Front Row',
			array( 'post_type' => 'product_variation' )
		);

		$this->login_as_contributor();

		$this->assertContains(
			array(
				'id'   => $variation,
				'name' => 'Show Ticket - Front Row',
			),
			$this->fetch_all()->get_data()
		);
	}

	/**
	 * The first request primes the transient with the response payload.
	 *
	 * @return void
	 */
	public function test_response_is_cached_in_transient() {
		$this->create_ticket_product( 'Cached Ticket' );
		$this->login_as_contributor();

		$data = $this->fetch_all()->get_data();

		$this->assertSame( $data, get_transient( SE_REST_Ticket_Products_List::CACHE_KEY ) );
	}

	/**
	 * A warm transient is served without re-querying.
	 *
	 * @return void
	 */
	public function test_warm_cache_is_served() {
		$sentinel = array(
			array(
				'id'   => 123,
				'name' => 'From The Cache',
			),
		);
		set_transient( SE_REST_Ticket_Products_List::CACHE_KEY, $sentinel, HOUR_IN_SECONDS );

		$this->login_as_contributor();

		$this->assertSame( $sentinel, $this->fetch_all()->get_data() );
	}

	/**
	 * Saving a product flushes the cache, so new products appear immediately.
	 *
	 * This is the "newly created products not appearing in the picker" half of
	 * issue #96.
	 *
	 * @return void
	 */
	public function test_cache_is_flushed_when_a_product_is_created() {
		$this->create_ticket_product( 'First Ticket' );
		$this->login_as_contributor();

		$this->assertCount( 1, $this->fetch_all()->get_data() );

		$fresh = $this->create_ticket_product( 'Second Ticket' );

		$this->assertFalse( get_transient( SE_REST_Ticket_Products_List::CACHE_KEY ), 'Creating a product must flush the cache.' );
		$this->assertContains(
			array(
				'id'   => $fresh,
				'name' => 'Second Ticket',
			),
			$this->fetch_all()->get_data()
		);
	}

	/**
	 * Updating a product flushes the cache.
	 *
	 * @return void
	 */
	public function test_cache_is_flushed_when_a_product_is_updated() {
		$ticket = $this->create_ticket_product( 'Old Name' );
		$this->login_as_contributor();
		$this->fetch_all();

		wp_update_post(
			array(
				'ID'         => $ticket,
				'post_title' => 'New Name',
			)
		);

		$this->assertFalse( get_transient( SE_REST_Ticket_Products_List::CACHE_KEY ), 'Updating a product must flush the cache.' );
	}

	/**
	 * Saving a variation flushes the cache.
	 *
	 * @return void
	 */
	public function test_cache_is_flushed_when_a_variation_is_saved() {
		$this->login_as_contributor();
		$this->fetch_all();

		$this->create_ticket_product( 'Variation', array( 'post_type' => 'product_variation' ) );

		$this->assertFalse( get_transient( SE_REST_Ticket_Products_List::CACHE_KEY ), 'Saving a variation must flush the cache.' );
	}

	/**
	 * Trashing, untrashing and deleting a product each flush the cache.
	 *
	 * @return void
	 */
	public function test_cache_is_flushed_on_trash_untrash_and_delete() {
		$ticket = $this->create_ticket_product( 'Doomed Ticket' );
		$this->login_as_contributor();

		$this->fetch_all();
		wp_trash_post( $ticket );
		$this->assertFalse( get_transient( SE_REST_Ticket_Products_List::CACHE_KEY ), 'Trashing a product must flush the cache.' );

		$this->fetch_all();
		wp_untrash_post( $ticket );
		$this->assertFalse( get_transient( SE_REST_Ticket_Products_List::CACHE_KEY ), 'Untrashing a product must flush the cache.' );

		$this->fetch_all();
		wp_delete_post( $ticket, true );
		$this->assertFalse( get_transient( SE_REST_Ticket_Products_List::CACHE_KEY ), 'Deleting a product must flush the cache.' );
	}

	/**
	 * Saving an unrelated post type must NOT flush the cache.
	 *
	 * @return void
	 */
	public function test_cache_survives_unrelated_post_saves() {
		$this->create_ticket_product( 'Stable Ticket' );
		$this->login_as_contributor();
		$this->fetch_all();

		$post = $this->factory->post->create( array( 'post_title' => 'Blog Post' ) );
		wp_trash_post( $post );

		$this->assertNotFalse( get_transient( SE_REST_Ticket_Products_List::CACHE_KEY ), 'Unrelated posts must not flush the ticket cache.' );
	}
}
