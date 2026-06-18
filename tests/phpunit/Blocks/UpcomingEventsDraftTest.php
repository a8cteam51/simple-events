<?php
/**
 * Tests that the Upcoming Events block excludes events whose parent is not published.
 *
 * The block queries se-event-date posts. When "treat each date as own event" is enabled
 * it skips the unique-parents path and previously applied no parent-published guard, so a
 * draft event's date still rendered. This covers that branch end-to-end via the rendered
 * markup (content-archive.php emits <li id="post-{parent_id}">).
 *
 * @package Simple_Events
 */
class UpcomingEventsDraftTest extends WP_UnitTestCase {

	/**
	 * Stash the original se_options so the treat-each-date toggle can be restored.
	 *
	 * @var mixed
	 */
	private $original_options;

	/**
	 * Enable "treat each date as own event" (the unguarded branch under test).
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->original_options = get_option( 'se_options' );
		update_option( 'se_options', array( 'treat_each_date_as_own_event' => 'on' ) );
	}

	/**
	 * Restore options.
	 *
	 * @return void
	 */
	public function tear_down() {
		if ( false === $this->original_options ) {
			delete_option( 'se_options' );
		} else {
			update_option( 'se_options', $this->original_options );
		}
		parent::tear_down();
	}

	/**
	 * Create an event of a given status with a single upcoming date.
	 *
	 * @param string $parent_status Parent se-event post_status.
	 * @param string $start_day     Date in 'Y-m-d' form for the event date.
	 *
	 * @return int The parent event ID.
	 */
	private function make_event( $parent_status, $start_day ) {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => $parent_status,
			)
		);

		$tz    = wp_timezone();
		$start = DateTime::createFromFormat( 'Y-m-d H:i:s', $start_day . ' 10:00:00', $tz )->getTimestamp();
		$end   = DateTime::createFromFormat( 'Y-m-d H:i:s', $start_day . ' 11:00:00', $tz )->getTimestamp();

		$date = se_event_create_event_date(
			$event_id,
			array(
				'start_date' => $start,
				'end_date'   => $end,
				'all_day'    => false,
			)
		);
		$this->assertNotNull( $date );

		return $event_id;
	}

	/**
	 * Render the Upcoming Events block (future feed).
	 *
	 * @return string
	 */
	private function render() {
		return SE_Blocks::upcoming_events_render(
			array(
				'count'            => 10,
				'feedType'         => 'upcoming',
				'feedOrder'        => 'ASC',
				'showYearDividers' => false,
				'layout'           => 'list',
				'columns'          => 1,
				'align'            => '',
				'className'        => '',
			),
			''
		);
	}

	/**
	 * A published event's date renders; a draft event's date does not.
	 *
	 * @return void
	 */
	public function test_block_excludes_draft_parent_events() {
		$published = $this->make_event( 'publish', '2030-08-10' );
		$draft     = $this->make_event( 'draft', '2030-08-11' );

		$html = $this->render();

		$this->assertStringContainsString( 'post-' . $published, $html, 'Published event should render in the upcoming events block.' );
		$this->assertStringNotContainsString( 'post-' . $draft, $html, 'Draft event should not render in the upcoming events block.' );
	}
}
