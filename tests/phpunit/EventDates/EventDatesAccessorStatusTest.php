<?php
/**
 * GH-86: se_event_get_event_dates() must return an event's own dates.
 *
 * The accessor was hardcoded to post_status => 'publish'. That was correct when
 * dates were always created published, but now that a date mirrors its event's
 * status it meant a draft or private event returned none of its own dates —
 * including in the editor, which reads them through this function via the REST
 * route and the /sync response.
 *
 * Public visibility is the caller's responsibility, not the accessor's.
 *
 * @package Simple_Events
 */
class EventDatesAccessorStatusTest extends WP_UnitTestCase {

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
	 * A published event returns its dates.
	 *
	 * @return void
	 */
	public function test_published_event_returns_its_dates() {
		$ids = $this->create_event_with_date( 'publish' );

		$this->assertCount( 1, se_event_get_event_dates( $ids['event_id'] ) );
	}

	/**
	 * A draft event returns its own dates, so the editor can show them.
	 *
	 * @return void
	 */
	public function test_draft_event_returns_its_dates() {
		$ids = $this->create_event_with_date( 'draft' );

		$dates = se_event_get_event_dates( $ids['event_id'] );

		$this->assertCount( 1, $dates, 'A draft event must still return its own dates.' );
		$this->assertSame( $ids['date_id'], $dates[0]['id'] );
	}

	/**
	 * A private event returns its own dates.
	 *
	 * @return void
	 */
	public function test_private_event_returns_its_dates() {
		$ids = $this->create_event_with_date( 'private' );

		$this->assertCount( 1, se_event_get_event_dates( $ids['event_id'] ), 'A private event must still return its own dates.' );
	}

	/**
	 * A pending event returns its own dates.
	 *
	 * @return void
	 */
	public function test_pending_event_returns_its_dates() {
		$ids = $this->create_event_with_date( 'pending' );

		$this->assertCount( 1, se_event_get_event_dates( $ids['event_id'] ), 'A pending event must still return its own dates.' );
	}

	/**
	 * A trashed event's dates are not returned.
	 *
	 * Trashed dates are not the event's working set, and nothing should be
	 * editing or displaying a trashed event.
	 *
	 * @return void
	 */
	public function test_trashed_event_returns_no_dates() {
		$ids = $this->create_event_with_date( 'publish' );

		wp_trash_post( $ids['event_id'] );

		$this->assertCount( 0, se_event_get_event_dates( $ids['event_id'] ), 'A trashed event should return no dates.' );
	}

	/**
	 * Only the event's own dates come back.
	 *
	 * @return void
	 */
	public function test_only_the_events_own_dates_are_returned() {
		$mine   = $this->create_event_with_date( 'draft' );
		$theirs = $this->create_event_with_date( 'publish' );

		$dates = se_event_get_event_dates( $mine['event_id'] );

		$this->assertCount( 1, $dates );
		$this->assertSame( $mine['date_id'], $dates[0]['id'] );
		$this->assertNotSame( $theirs['date_id'], $dates[0]['id'] );
	}
}
