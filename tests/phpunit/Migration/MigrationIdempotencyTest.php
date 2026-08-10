<?php
/**
 * GH-86: a migration that fails part way must not duplicate an event's dates.
 *
 * The migration creates a child se-event-date for every row in the legacy
 * se_event_dates meta, and stamps se_event_version only after the whole loop.
 * Die half way and the children created so far survive with no stamp, so the
 * next run starts from the top and creates them again.
 *
 * A clean re-run is not the concern: get_migration_methods() filters on
 * version_compare(), so a stamped event never runs this method again.
 *
 * @package Simple_Events
 */
class MigrationIdempotencyTest extends WP_UnitTestCase {

	/**
	 * Create a legacy event: two rows of se_event_dates meta, no child posts.
	 *
	 * @return integer The event ID.
	 */
	private function create_legacy_event() {
		$event_id = $this->factory->post->create(
			array(
				'post_type'    => 'se-event',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:simple-events/event-info /-->',
			)
		);

		$tomorrow  = strtotime( '+1 day' );
		$day_after = strtotime( '+2 days' );

		update_post_meta(
			$event_id,
			'se_event_dates',
			array(
				array(
					'datetime_start' => (string) $tomorrow,
					'datetime_end'   => (string) ( $tomorrow + 7200 ),
					'all_day'        => false,
				),
				array(
					'datetime_start' => (string) $day_after,
					'datetime_end'   => (string) ( $day_after + 7200 ),
					'all_day'        => false,
				),
			)
		);

		// Publishing stamps the current version via stamp_version_on_new_event(),
		// so drop it — a genuinely legacy event has never been stamped.
		delete_post_meta( $event_id, 'se_event_version' );

		return $event_id;
	}

	/**
	 * Count the se-event-date children of an event, any status.
	 *
	 * @param integer $event_id The parent event ID.
	 *
	 * @return integer
	 */
	private function count_event_dates( $event_id ) {
		return count( $this->event_date_ids( $event_id ) );
	}

	/**
	 * The se-event-date child IDs of an event, any status, oldest first.
	 *
	 * @param integer $event_id The parent event ID.
	 *
	 * @return array<integer>
	 */
	private function event_date_ids( $event_id ) {
		$ids = get_posts(
			array(
				'post_type'      => 'se-event-date',
				'post_parent'    => $event_id,
				'post_status'    => SE_Event_Post_Type::child_date_statuses(),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		sort( $ids );

		return $ids;
	}

	/**
	 * One migration creates one child per legacy row.
	 *
	 * @return void
	 */
	public function test_migrating_once_creates_one_date_per_legacy_row() {
		$event_id = $this->create_legacy_event();

		SE_Migrate_Events::migrate_1_0_0_to_2_0_0( $event_id );

		$this->assertSame( 2, $this->count_event_dates( $event_id ), 'Two legacy rows should produce two child dates.' );
	}

	/**
	 * An already-migrated event must be left alone.
	 *
	 * The method is public and checks nothing, so anything calling it directly
	 * runs it on an event that is already on 2.0.0. It must not rebuild the
	 * children — that would change their IDs and break every ?se-date= permalink.
	 *
	 * @return void
	 */
	public function test_an_already_migrated_event_is_not_rebuilt() {
		$event_id = $this->create_legacy_event();

		SE_Migrate_Events::migrate_1_0_0_to_2_0_0( $event_id );
		update_post_meta( $event_id, 'se_event_version', '2.0.0' );

		$before = $this->event_date_ids( $event_id );
		$this->assertCount( 2, $before, 'Fixture should have two child dates before the second call.' );

		SE_Migrate_Events::migrate_1_0_0_to_2_0_0( $event_id );

		$this->assertSame( $before, $this->event_date_ids( $event_id ), 'An already-migrated event must keep its existing date posts.' );
	}

	/**
	 * A run that died part way through must not leave duplicates behind.
	 *
	 * Simulates the real failure: the first row was created, the process died
	 * before the second, so no version stamp was written and the whole
	 * migration runs again from the top.
	 *
	 * @return void
	 */
	public function test_rerun_after_a_partial_migration_does_not_duplicate_dates() {
		$event_id = $this->create_legacy_event();
		$dates    = get_post_meta( $event_id, 'se_event_dates', true );

		// A partial run: the first row got its child, the second never did.
		se_event_create_event_date(
			$event_id,
			array(
				'start_date' => $dates[0]['datetime_start'],
				'end_date'   => $dates[0]['datetime_end'],
				'all_day'    => false,
			)
		);

		$this->assertSame( 1, $this->count_event_dates( $event_id ), 'Partial run fixture should leave exactly one child.' );

		SE_Migrate_Events::migrate_1_0_0_to_2_0_0( $event_id );

		$this->assertSame( 2, $this->count_event_dates( $event_id ), 'Re-running after a partial migration must leave two child dates, not three.' );
	}
}
