<?php
/**
 * S-03 and S-04: the event-dates /sync route.
 *
 * S-03 — class-se-event-dates.php:69-71 gates the route on a bare edit_posts
 * capability. The handler at :148 verifies a nonce (:154) and nothing else: the
 * nonce is CSRF protection, not authorisation. At :197 it takes the caller's own
 * $date['id'], absint()s it and writes five se_* meta keys to it, without ever
 * checking that the ID is an se-event-date belonging to $event_id. A Contributor
 * can therefore write meta onto any post in the site.
 *
 * S-04 — the delete loop at :173-177 runs BEFORE the create/update loop and force
 * deletes ($force = true, no trash). Combined with the missing object-level check,
 * a Contributor can wipe another author's event dates by posting an empty set.
 *
 * These assert the SECURE behaviour, so they fail against the current code.
 *
 * @package Simple_Events
 */
class EventDatesSyncAuthTest extends WP_UnitTestCase {

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
	 * Create an event owned by a given user, with one child date.
	 *
	 * @param integer $author_id The owning user ID.
	 *
	 * @return array{event_id:int, date_id:int}
	 */
	private function create_event_with_date( $author_id ) {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
				'post_author' => $author_id,
			)
		);

		$start = strtotime( '2030-06-15 10:00:00' );
		$date  = se_event_create_event_date(
			$event_id,
			array(
				'start_date' => $start,
				'end_date'   => $start + 3600,
				'all_day'    => false,
			)
		);

		$this->assertNotNull( $date, 'Fixture event-date should have been created.' );

		return array(
			'event_id' => $event_id,
			'date_id'  => $date->ID,
		);
	}

	/**
	 * Dispatch a sync request for an event.
	 *
	 * @param integer $event_id The event to sync.
	 * @param array   $dates    The dates payload.
	 *
	 * @return WP_REST_Response
	 */
	private function sync( $event_id, array $dates ) {
		$request = new WP_REST_Request( 'POST', "/simple-events/event-dates/{$event_id}/sync" );
		$request->set_param( 'dates', $dates );
		$request->set_param( 'nonce', wp_create_nonce( 'se_event_nonce' ) );

		return rest_do_request( $request );
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
	 * S-03: a Contributor must not be able to write meta to an arbitrary post.
	 *
	 * The payload names a post ID that is not a child of the event being synced
	 * — here another author's ordinary page. Its meta must be untouched.
	 *
	 * @return void
	 */
	public function test_contributor_cannot_write_meta_to_arbitrary_post() {
		$owner       = $this->factory->user->create( array( 'role' => 'author' ) );
		$contributor = $this->factory->user->create( array( 'role' => 'contributor' ) );

		$own    = $this->create_event_with_date( $contributor );
		$victim = $this->factory->post->create(
			array(
				'post_type'   => 'page',
				'post_author' => $owner,
			)
		);

		wp_set_current_user( $contributor );

		$this->sync(
			$own['event_id'],
			array(
				array(
					'id'                 => $victim,
					'start_date'         => '1893456000',
					'end_date'           => '1893459600',
					'all_day'            => false,
					'hide_from_calendar' => false,
					'hide_from_feed'     => false,
				),
			)
		);

		$this->assertSame(
			'',
			get_post_meta( $victim, 'se_event_date_start', true ),
			'A Contributor must not be able to write se_event_date_start onto an unrelated post.'
		);
	}

	/**
	 * S-03: a Contributor must not be able to sync another author's event.
	 *
	 * @return void
	 */
	public function test_contributor_cannot_sync_another_authors_event() {
		$owner       = $this->factory->user->create( array( 'role' => 'author' ) );
		$contributor = $this->factory->user->create( array( 'role' => 'contributor' ) );

		$victim   = $this->create_event_with_date( $owner );
		$original = get_post_meta( $victim['date_id'], 'se_event_date_start', true );

		wp_set_current_user( $contributor );

		$status = $this->sync(
			$victim['event_id'],
			array(
				array(
					'id'                 => $victim['date_id'],
					'start_date'         => '1893456000',
					'end_date'           => '1893459600',
					'all_day'            => false,
					'hide_from_calendar' => false,
					'hide_from_feed'     => false,
				),
			)
		)->get_status();

		$this->assertContains(
			$status,
			array( 401, 403 ),
			"A Contributor syncing another author's event must be refused, got HTTP {$status}."
		);

		$this->assertSame(
			$original,
			get_post_meta( $victim['date_id'], 'se_event_date_start', true ),
			"The victim event's date must be unchanged."
		);
	}

	/**
	 * S-04: a Contributor must not be able to wipe another author's dates.
	 *
	 * Posting an empty set drives the delete loop at :173-177 over every existing
	 * child date.
	 *
	 * @return void
	 */
	public function test_contributor_cannot_wipe_another_authors_event_dates() {
		$owner       = $this->factory->user->create( array( 'role' => 'author' ) );
		$contributor = $this->factory->user->create( array( 'role' => 'contributor' ) );

		$victim = $this->create_event_with_date( $owner );
		$this->assertSame( 1, $this->count_event_dates( $victim['event_id'] ), 'Fixture should have one date.' );

		wp_set_current_user( $contributor );

		$this->sync( $victim['event_id'], array() );

		$this->assertSame(
			1,
			$this->count_event_dates( $victim['event_id'] ),
			"A Contributor must not be able to delete another author's event dates."
		);
		$this->assertNotNull(
			get_post( $victim['date_id'] ),
			'The victim date post must still exist.'
		);
	}

	/**
	 * The happy path: an owner can still sync their own event.
	 *
	 * Guards against the fix being over-tight and breaking the editor's save.
	 *
	 * @return void
	 */
	public function test_owner_can_sync_their_own_event() {
		$owner = $this->factory->user->create( array( 'role' => 'author' ) );
		$own   = $this->create_event_with_date( $owner );

		wp_set_current_user( $owner );

		$status = $this->sync(
			$own['event_id'],
			array(
				array(
					'id'                 => $own['date_id'],
					'start_date'         => '1893456000',
					'end_date'           => '1893459600',
					'all_day'            => false,
					'hide_from_calendar' => false,
					'hide_from_feed'     => false,
				),
			)
		)->get_status();

		$this->assertSame( 200, $status, "An owner should be able to sync their own event, got HTTP {$status}." );

		$this->assertSame(
			'1893456000',
			get_post_meta( $own['date_id'], 'se_event_date_start', true ),
			'The owner\'s own date should have been updated.'
		);
	}

	/**
	 * An administrator can sync any event.
	 *
	 * @return void
	 */
	public function test_administrator_can_sync_any_event() {
		$owner = $this->factory->user->create( array( 'role' => 'author' ) );
		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );

		$event = $this->create_event_with_date( $owner );

		wp_set_current_user( $admin );

		$status = $this->sync(
			$event['event_id'],
			array(
				array(
					'id'                 => $event['date_id'],
					'start_date'         => '1893456000',
					'end_date'           => '1893459600',
					'all_day'            => false,
					'hide_from_calendar' => false,
					'hide_from_feed'     => false,
				),
			)
		)->get_status();

		$this->assertSame( 200, $status, "An administrator should be able to sync any event, got HTTP {$status}." );
	}
}
