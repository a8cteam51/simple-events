<?php
/**
 * GH-86: clearing event dates when the Event Info block is absent.
 *
 * Saving fires delete_event_dates_if_no_event_info_block()
 * (class-se-event-post-type.php:749-770), which clears every child date when
 * the block is missing from the content.
 *
 * This is deliberate, not the data-loss bug GH-86 describes. The Event Info
 * block is locked to the event template, so content without it means the event
 * is already broken and its dates are already unreachable. Clearing is the
 * intended cleanup.
 *
 * These tests pin that intent so a later "no path deletes without a trash step"
 * change does not silently resurrect orphaned dates, and so the guard against
 * clearing on an ordinary save stays covered.
 *
 * @package Simple_Events
 */
class EventDatesBlockRemovalTest extends WP_UnitTestCase {

	/**
	 * Content that contains the Event Info block.
	 *
	 * @var string
	 */
	private $with_block = '<!-- wp:simple-events/event-info /-->';

	/**
	 * Content that does not contain the Event Info block.
	 *
	 * @var string
	 */
	private $without_block = '<!-- wp:paragraph --><p>No event info here.</p><!-- /wp:paragraph -->';

	/**
	 * Create an event carrying the Event Info block, with one child date.
	 *
	 * @return array{event_id:integer, date_id:integer}
	 */
	private function create_event_with_date() {
		$event_id = $this->factory->post->create(
			array(
				'post_type'    => 'se-event',
				'post_status'  => 'publish',
				'post_content' => $this->with_block,
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
		$this->assertSame( 'publish', get_post_status( $date->ID ), 'Fixture date should start published.' );

		// se_event_create_event_date() only writes meta to the date post. A saved
		// event also carries these three on the parent, so set them by hand.
		update_post_meta(
			$event_id,
			'se_event_dates',
			array(
				array(
					'datetime_start' => (string) $start,
					'datetime_end'   => (string) ( $start + 3600 ),
					'all_day'        => false,
				),
			)
		);
		update_post_meta( $event_id, 'se_event_date_start', (string) $start );
		update_post_meta( $event_id, 'se_event_date_end', (string) ( $start + 3600 ) );

		return array(
			'event_id' => $event_id,
			'date_id'  => $date->ID,
		);
	}

	/**
	 * Save an event with new content, driving the save_post hook.
	 *
	 * @param integer $event_id The event to update.
	 * @param string  $content  The new post content.
	 *
	 * @return void
	 */
	private function save_content( $event_id, $content ) {
		wp_update_post(
			array(
				'ID'           => $event_id,
				'post_content' => $content,
			)
		);
	}

	/**
	 * Count the se-event-date children of an event, any status.
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
	 * An event saved without the block has its dates cleared.
	 *
	 * @return void
	 */
	public function test_saving_without_the_block_clears_dates() {
		$ids = $this->create_event_with_date();

		$this->save_content( $ids['event_id'], $this->without_block );

		$this->assertSame(
			0,
			$this->count_event_dates( $ids['event_id'] ),
			'An event with no Event Info block should have no child dates left.'
		);
	}

	/**
	 * Clearing must not leave the parent advertising dates that no longer exist.
	 *
	 * GH-86 also reports stale parent meta: children go, se_event_dates and the
	 * se_event_date_start/_end mirrors stay, so cron and the countdown keep
	 * reading dates for children that are gone.
	 *
	 * @return void
	 */
	public function test_clearing_dates_also_clears_stale_parent_meta() {
		$ids = $this->create_event_with_date();

		// Without this the assertions below pass on meta that was never set.
		$this->assertNotEmpty(
			get_post_meta( $ids['event_id'], 'se_event_dates', true ),
			'Fixture should carry se_event_dates before the block is removed.'
		);
		$this->assertNotEmpty(
			get_post_meta( $ids['event_id'], 'se_event_date_start', true ),
			'Fixture should carry se_event_date_start before the block is removed.'
		);
		$this->assertNotEmpty(
			get_post_meta( $ids['event_id'], 'se_event_date_end', true ),
			'Fixture should carry se_event_date_end before the block is removed.'
		);

		$this->save_content( $ids['event_id'], $this->without_block );

		$this->assertEmpty(
			get_post_meta( $ids['event_id'], 'se_event_dates', true ),
			'se_event_dates must not still list cleared dates.'
		);
		$this->assertEmpty(
			get_post_meta( $ids['event_id'], 'se_event_date_start', true ),
			'se_event_date_start must not survive its date being cleared.'
		);
		$this->assertEmpty(
			get_post_meta( $ids['event_id'], 'se_event_date_end', true ),
			'se_event_date_end must not survive its date being cleared.'
		);
	}

	/**
	 * Saving with the block still present must leave the dates alone.
	 *
	 * Guards against clearing dates on every ordinary save.
	 *
	 * @return void
	 */
	public function test_saving_with_block_present_leaves_dates_published() {
		$ids = $this->create_event_with_date();

		$this->save_content( $ids['event_id'], $this->with_block . '<!-- wp:paragraph --><p>Edited.</p><!-- /wp:paragraph -->' );

		$this->assertSame(
			'publish',
			get_post_status( $ids['date_id'] ),
			'Saving an event that still has the block must not touch its dates.'
		);
	}
}
