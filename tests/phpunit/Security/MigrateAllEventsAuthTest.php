<?php
/**
 * S-01: the /simple-events/migrate-all-events REST route is unauthenticated.
 *
 * The route is registered at class-se-migrate-events.php:63-73 with
 * 'permission_callback' => fn() => true and 'methods' => array( 'POST', 'GET' ).
 * Its sibling /migrate-events at :50-60 correctly requires manage_options.
 *
 * An anonymous request therefore runs a site-wide migration: migrate_event()
 * writes se_event_version meta (:288) and migrate_1_0_0_to_2_0_0() creates a
 * child se-event-date post per legacy date entry (:347). A crawler or link
 * prefetch on GET is enough to trigger it.
 *
 * Fix: the route now requires manage_options and accepts POST only, matching the
 * sibling. The refusal assertions accept 401, 403 or 404 so they stay valid if
 * the route is later removed entirely.
 *
 * @package Simple_Events
 */
class MigrateAllEventsAuthTest extends WP_UnitTestCase {

	/**
	 * The route under test.
	 *
	 * @var string
	 */
	private $route = '/simple-events/migrate-all-events';

	/**
	 * Spin up a REST server and register the plugin's routes.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		wp_set_current_user( 0 );
	}

	/**
	 * Tear down the REST server.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Create an event that qualifies for migration.
	 *
	 * It carries legacy se_event_dates meta and no se_event_version, which is the
	 * "NOT EXISTS" arm of the meta_query in get_events_to_migrate().
	 *
	 * @return integer The event post ID.
	 */
	private function create_unmigrated_event() {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);

		$tz    = wp_timezone();
		$start = DateTime::createFromFormat( 'Y-m-d H:i:s', '2030-06-15 10:00:00', $tz )->getTimestamp();
		$end   = DateTime::createFromFormat( 'Y-m-d H:i:s', '2030-06-15 11:00:00', $tz )->getTimestamp();

		update_post_meta(
			$event_id,
			'se_event_dates',
			array(
				array(
					'datetime_start' => $start,
					'datetime_end'   => $end,
					'all_day'        => false,
				),
			)
		);

		delete_post_meta( $event_id, 'se_event_version' );

		// Guard the fixture: the migration must actually have work to do,
		// otherwise the route short-circuits at :133 and proves nothing.
		$this->assertTrue(
			SE_Migrate_Events::has_events_to_migrate(),
			'Fixture should leave at least one event awaiting migration.'
		);

		return $event_id;
	}

	/**
	 * Count the se-event-date children of an event.
	 *
	 * @param integer $event_id The parent event ID.
	 *
	 * @return integer
	 */
	private function count_event_dates( $event_id ) {
		return count(
			get_posts(
				array(
					'post_type'      => 'se-event-date',
					'post_parent'    => $event_id,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			)
		);
	}

	/**
	 * Dispatch a request to the route as the current user.
	 *
	 * @param string $method HTTP method.
	 *
	 * @return WP_REST_Response
	 */
	private function dispatch( $method ) {
		return rest_do_request( new WP_REST_Request( $method, $this->route ) );
	}

	/**
	 * A logged-out GET must be refused.
	 *
	 * GET is the dangerous one: it is reachable by prefetch and crawlers.
	 *
	 * @return void
	 */
	public function test_anonymous_get_is_refused() {
		$this->create_unmigrated_event();

		$status = $this->dispatch( 'GET' )->get_status();

		$this->assertContains(
			$status,
			array( 401, 403, 404 ),
			"An anonymous GET to {$this->route} must be refused, got HTTP {$status}."
		);
	}

	/**
	 * A logged-out POST must be refused.
	 *
	 * @return void
	 */
	public function test_anonymous_post_is_refused() {
		$this->create_unmigrated_event();

		$status = $this->dispatch( 'POST' )->get_status();

		$this->assertContains(
			$status,
			array( 401, 403, 404 ),
			"An anonymous POST to {$this->route} must be refused, got HTTP {$status}."
		);
	}

	/**
	 * A Subscriber must be refused too.
	 *
	 * On a Box Office site every ticket buyer holds this role.
	 *
	 * @return void
	 */
	public function test_subscriber_is_refused() {
		$this->create_unmigrated_event();
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$status = $this->dispatch( 'POST' )->get_status();

		$this->assertContains(
			$status,
			array( 401, 403, 404 ),
			"A Subscriber POST to {$this->route} must be refused, got HTTP {$status}."
		);
	}

	/**
	 * The impact assertion: an anonymous request must not migrate anything.
	 *
	 * A status-code check alone would pass if the route were merely made to
	 * return an error after doing the work. This asserts the side effects are
	 * absent: no version meta written, no event-date posts created.
	 *
	 * @return void
	 */
	public function test_anonymous_request_does_not_migrate_any_event() {
		$event_id = $this->create_unmigrated_event();

		$this->assertSame( 0, $this->count_event_dates( $event_id ), 'Fixture should start with no event dates.' );

		$this->dispatch( 'GET' );

		$this->assertSame(
			'',
			get_post_meta( $event_id, 'se_event_version', true ),
			'An anonymous request must not stamp se_event_version onto an event.'
		);

		$this->assertSame(
			0,
			$this->count_event_dates( $event_id ),
			'An anonymous request must not create event-date posts.'
		);

		$this->assertTrue(
			SE_Migrate_Events::has_events_to_migrate(),
			'The event must still be awaiting migration after an anonymous request.'
		);
	}

	/**
	 * An administrator must still be able to run the migration.
	 *
	 * Guards against the lockdown being over-tight and breaking the feature.
	 *
	 * @return void
	 */
	public function test_administrator_post_is_allowed_and_migrates() {
		$event_id = $this->create_unmigrated_event();
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$status = $this->dispatch( 'POST' )->get_status();

		$this->assertSame( 200, $status, "An administrator POST should succeed, got HTTP {$status}." );

		$this->assertSame(
			'2.0.0',
			get_post_meta( $event_id, 'se_event_version', true ),
			'The migration should have stamped the event version for an administrator.'
		);

		$this->assertSame(
			1,
			$this->count_event_dates( $event_id ),
			'The migration should have created the event date for an administrator.'
		);
	}

	/**
	 * GET must be refused even for an administrator.
	 *
	 * The route is POST-only now, so it cannot be fired by prefetch or a crawler
	 * following a link from an authenticated admin session.
	 *
	 * @return void
	 */
	public function test_administrator_get_is_refused() {
		$this->create_unmigrated_event();
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$status = $this->dispatch( 'GET' )->get_status();

		$this->assertNotSame( 200, $status, 'A GET must not run the migration even for an administrator.' );
		$this->assertTrue(
			SE_Migrate_Events::has_events_to_migrate(),
			'A GET must leave the event unmigrated.'
		);
	}
}
