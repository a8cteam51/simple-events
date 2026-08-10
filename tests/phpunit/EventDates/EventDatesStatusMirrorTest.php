<?php
/**
 * GH-86: an event date's status must always match its event's status.
 *
 * Today dates are created 'publish' regardless of the parent
 * (event-functions.php:722) and nothing propagates a later status change, so a
 * draft or private event has publicly published date posts hanging off it. The
 * query layer papers over this by filtering on the parent's status
 * (class-se-event-query-utils.php:225) rather than the date's own.
 *
 * The rule is simply: the date matches the event, and when the event's status
 * changes the dates follow.
 *
 * These assert that rule, so they fail against the current code.
 *
 * @package Simple_Events
 */
class EventDatesStatusMirrorTest extends WP_UnitTestCase {

	/**
	 * Create an event of a given status, carrying one child date.
	 *
	 * @param string $status The event's post_status.
	 *
	 * @return array{event_id:integer, date_id:integer}
	 */
	private function create_event_with_date( $status ) {
		$event_id = $this->factory->post->create(
			array(
				'post_type'    => 'se-event',
				'post_status'  => $status,
				'post_content' => '<!-- wp:simple-events/event-info /-->',
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
	 * Move an event to a new status.
	 *
	 * @param integer $event_id The event to update.
	 * @param string  $status   The new post_status.
	 *
	 * @return void
	 */
	private function set_status( $event_id, $status ) {
		wp_update_post(
			array(
				'ID'          => $event_id,
				'post_status' => $status,
			)
		);
	}

	/**
	 * A date created under a published event is published.
	 *
	 * @return void
	 */
	public function test_date_created_under_published_event_is_published() {
		$ids = $this->create_event_with_date( 'publish' );

		$this->assertSame( 'publish', get_post_status( $ids['date_id'] ) );
	}

	/**
	 * A date created under a draft event is a draft, not published.
	 *
	 * @return void
	 */
	public function test_date_created_under_draft_event_is_draft() {
		$ids = $this->create_event_with_date( 'draft' );

		$this->assertSame(
			'draft',
			get_post_status( $ids['date_id'] ),
			'A draft event must not have publicly published dates.'
		);
	}

	/**
	 * A date created under a private event is private.
	 *
	 * @return void
	 */
	public function test_date_created_under_private_event_is_private() {
		$ids = $this->create_event_with_date( 'private' );

		$this->assertSame(
			'private',
			get_post_status( $ids['date_id'] ),
			'A private event must not have publicly published dates.'
		);
	}

	/**
	 * A date created under a pending event is pending.
	 *
	 * @return void
	 */
	public function test_date_created_under_pending_event_is_pending() {
		$ids = $this->create_event_with_date( 'pending' );

		$this->assertSame( 'pending', get_post_status( $ids['date_id'] ) );
	}

	/**
	 * Publishing a draft event publishes its dates.
	 *
	 * Without this, dates created while drafting stay hidden after the event
	 * goes live — the event would publish with no visible dates.
	 *
	 * @return void
	 */
	public function test_publishing_a_draft_event_publishes_its_dates() {
		$ids = $this->create_event_with_date( 'draft' );

		$this->set_status( $ids['event_id'], 'publish' );

		$this->assertSame(
			'publish',
			get_post_status( $ids['date_id'] ),
			'Publishing an event must publish its dates.'
		);
	}

	/**
	 * Unpublishing an event back to draft drafts its dates.
	 *
	 * @return void
	 */
	public function test_unpublishing_an_event_drafts_its_dates() {
		$ids = $this->create_event_with_date( 'publish' );

		$this->set_status( $ids['event_id'], 'draft' );

		$this->assertSame(
			'draft',
			get_post_status( $ids['date_id'] ),
			'Moving an event to draft must draft its dates.'
		);
	}

	/**
	 * Making an event private makes its dates private.
	 *
	 * @return void
	 */
	public function test_making_an_event_private_makes_its_dates_private() {
		$ids = $this->create_event_with_date( 'publish' );

		$this->set_status( $ids['event_id'], 'private' );

		$this->assertSame( 'private', get_post_status( $ids['date_id'] ) );
	}

	/**
	 * Trashing an event trashes its dates.
	 *
	 * @return void
	 */
	public function test_trashing_an_event_trashes_its_dates() {
		$ids = $this->create_event_with_date( 'publish' );

		wp_trash_post( $ids['event_id'] );

		$this->assertSame( 'trash', get_post_status( $ids['date_id'] ) );
	}

	/**
	 * Child dates are trashed through the trash API, not a bare status write.
	 *
	 * Setting post_status directly skips the trash bookkeeping: no
	 * _wp_trash_meta_time, and the wp_trash_post/trashed_post actions never
	 * fire, so anything listening on them stops seeing event dates.
	 *
	 * @return void
	 */
	public function test_trashing_an_event_trashes_its_dates_through_the_trash_api() {
		$ids = $this->create_event_with_date( 'publish' );

		$trashed = array();
		add_action(
			'trashed_post',
			function ( $post_id ) use ( &$trashed ) {
				$trashed[] = $post_id;
			}
		);

		wp_trash_post( $ids['event_id'] );

		$this->assertContains(
			$ids['date_id'],
			$trashed,
			'Child dates must go through wp_trash_post(), so trash hooks fire for them.'
		);
		$this->assertNotEmpty(
			get_post_meta( $ids['date_id'], '_wp_trash_meta_time', true ),
			'A properly trashed child carries _wp_trash_meta_time.'
		);
	}

	/**
	 * Untrashing an event restores its dates.
	 *
	 * @return void
	 */
	public function test_untrashing_an_event_restores_its_dates() {
		$ids = $this->create_event_with_date( 'publish' );

		wp_trash_post( $ids['event_id'] );
		wp_untrash_post( $ids['event_id'] );

		$this->assertSame(
			get_post_status( $ids['event_id'] ),
			get_post_status( $ids['date_id'] ),
			'A restored event\'s dates must match the event again.'
		);
	}

	/**
	 * Only the changed event's dates move.
	 *
	 * @return void
	 */
	public function test_status_change_does_not_affect_other_events_dates() {
		$changed   = $this->create_event_with_date( 'publish' );
		$untouched = $this->create_event_with_date( 'publish' );

		$this->set_status( $changed['event_id'], 'draft' );

		$this->assertSame( 'draft', get_post_status( $changed['date_id'] ) );
		$this->assertSame(
			'publish',
			get_post_status( $untouched['date_id'] ),
			'An unrelated event\'s dates must not change.'
		);
	}
}
