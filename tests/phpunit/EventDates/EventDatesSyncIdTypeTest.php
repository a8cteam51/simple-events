<?php
/**
 * GH-86: syncing must not recreate dates that were sent back unchanged.
 *
 * The editor posts its dates as JSON, so IDs arrive as strings. The comparison
 * in sync_event_dates() is strict, so 107 !== "107" and every existing date
 * reads as missing: deleted, then recreated with a new ID. Every ?se-date=
 * permalink breaks on every save.
 *
 * @package Simple_Events
 */
class EventDatesSyncIdTypeTest extends WP_UnitTestCase {

	/**
	 * Create a published event carrying two child dates.
	 *
	 * @return integer The event ID.
	 */
	private function create_event_with_dates() {
		$event_id = $this->factory->post->create(
			array(
				'post_type'    => 'se-event',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:simple-events/event-info /-->',
			)
		);

		$tomorrow  = strtotime( '+1 day' );
		$day_after = strtotime( '+2 days' );

		foreach ( array( $tomorrow, $day_after ) as $start ) {
			$date = se_event_create_event_date(
				$event_id,
				array(
					'start_date' => $start,
					'end_date'   => $start + 7200,
					'all_day'    => false,
				)
			);

			$this->assertNotNull( $date, 'Fixture event-date should have been created.' );
		}

		return $event_id;
	}

	/**
	 * Send the event's own dates back through sync, with IDs as the given type.
	 *
	 * @param integer  $event_id The event to sync.
	 * @param callable $cast     Applied to each ID before sending.
	 *
	 * @return WP_REST_Response
	 */
	private function sync_existing_dates( $event_id, callable $cast ) {
		$dates = array_map(
			function ( $date ) use ( $cast ) {
				return array(
					'id'                 => $cast( $date['id'] ),
					'start_date'         => $date['start_date'],
					'end_date'           => $date['end_date'],
					'all_day'            => $date['all_day'],
					'hide_from_calendar' => $date['hide_from_calendar'],
					'hide_from_feed'     => $date['hide_from_feed'],
				);
			},
			se_event_get_event_dates( $event_id )
		);

		$request = new WP_REST_Request( 'POST', '/simple-events/event-dates/' . $event_id . '/sync' );
		$request->set_param( 'event_id', $event_id );
		$request->set_param( 'dates', $dates );
		$request->set_param( 'nonce', wp_create_nonce( 'se_event_nonce' ) );

		$sync = new SE_Event_Dates();

		return $sync->sync_event_dates( $request );
	}

	/**
	 * The event's child date IDs, oldest first.
	 *
	 * @param integer $event_id The parent event ID.
	 *
	 * @return array<integer>
	 */
	private function event_date_ids( $event_id ) {
		$ids = wp_list_pluck( se_event_get_event_dates( $event_id ), 'id' );

		sort( $ids );

		return $ids;
	}

	/**
	 * Integer IDs round-trip without recreating anything.
	 *
	 * @return void
	 */
	public function test_syncing_with_integer_ids_keeps_the_same_dates() {
		$event_id = $this->create_event_with_dates();
		$before   = $this->event_date_ids( $event_id );

		$this->assertCount( 2, $before );

		$this->sync_existing_dates( $event_id, 'intval' );

		$this->assertSame( $before, $this->event_date_ids( $event_id ), 'Integer IDs should not recreate the dates.' );
	}

	/**
	 * String IDs, as JSON delivers them, must round-trip too.
	 *
	 * @return void
	 */
	public function test_syncing_with_string_ids_keeps_the_same_dates() {
		$event_id = $this->create_event_with_dates();
		$before   = $this->event_date_ids( $event_id );

		$this->assertCount( 2, $before );

		$this->sync_existing_dates( $event_id, 'strval' );

		$this->assertSame( $before, $this->event_date_ids( $event_id ), 'String IDs must not delete and recreate the dates.' );
	}

	/**
	 * A date left out of the payload is still removed.
	 *
	 * Guards against fixing the comparison by never deleting anything.
	 *
	 * @return void
	 */
	public function test_a_date_omitted_from_the_payload_is_removed() {
		$event_id = $this->create_event_with_dates();
		$before   = $this->event_date_ids( $event_id );

		$kept    = se_event_get_event_dates( $event_id )[0];
		$request = new WP_REST_Request( 'POST', '/simple-events/event-dates/' . $event_id . '/sync' );
		$request->set_param( 'event_id', $event_id );
		$request->set_param(
			'dates',
			array(
				array(
					'id'                 => (string) $kept['id'],
					'start_date'         => $kept['start_date'],
					'end_date'           => $kept['end_date'],
					'all_day'            => $kept['all_day'],
					'hide_from_calendar' => $kept['hide_from_calendar'],
					'hide_from_feed'     => $kept['hide_from_feed'],
				),
			)
		);
		$request->set_param( 'nonce', wp_create_nonce( 'se_event_nonce' ) );

		$sync = new SE_Event_Dates();
		$sync->sync_event_dates( $request );

		$after = $this->event_date_ids( $event_id );

		$this->assertCount( 1, $after, 'The omitted date should have been removed.' );
		$this->assertSame( array( $before[0] ), $after, 'The kept date should keep its ID.' );
	}
}
