<?php
/**
 * GH-88: get_header_date() must print the event's own date.
 *
 * array_filter() preserves keys, so a match that is not the first date leaves
 * $found_date[0] undefined (class-date-display-formatter.php:291).
 * render_single_date() then formats null, and wp_date() substitutes the current
 * time — the header prints today's date as the event date.
 *
 * The sibling render_active_date() gets this right with array_values(), so it is
 * covered here too as the reference behaviour.
 *
 * @package Simple_Events
 */
class HeaderDateTest extends WP_UnitTestCase {

	/**
	 * The parent event.
	 *
	 * @var integer
	 */
	private $event_id = 0;

	/**
	 * Child date IDs, in creation order.
	 *
	 * @var array<integer>
	 */
	private $date_ids = array();

	/**
	 * Start timestamps, matching $date_ids by index.
	 *
	 * @var array<integer>
	 */
	private $starts = array();

	/**
	 * Three dates in separate months, so a wrong pick is unambiguous.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		// get_header_date()'s branch only runs when each date is its own event.
		update_option( 'se_options', array( 'treat_each_date_as_own_event' => 'on' ) );

		$this->event_id = $this->factory->post->create(
			array(
				'post_type'    => 'se-event',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:simple-events/event-info /-->',
			)
		);

		foreach ( array( '2030-06-15 10:00:00', '2030-07-20 10:00:00', '2030-08-25 10:00:00' ) as $when ) {
			$start = strtotime( $when );

			$date = se_event_create_event_date(
				$this->event_id,
				array(
					'start_date' => $start,
					'end_date'   => $start + 3600,
					'all_day'    => false,
				)
			);

			$this->assertNotNull( $date, 'Fixture event-date should have been created.' );

			$this->date_ids[] = $date->ID;
			$this->starts[]   = $start;
		}
	}

	/**
	 * Leave no ?se-date= behind for the next test.
	 *
	 * @return void
	 */
	public function tear_down() {
		unset( $_GET['se-date'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		parent::tear_down();
	}

	/**
	 * A formatter reading the given date from ?se-date=.
	 *
	 * Date-only with a fixed format, so the assertion pins the date and nothing
	 * else. The constructor reads $_GET, so it is set first.
	 *
	 * @param integer $event_date_id The date the request is for.
	 *
	 * @return SE_Date_Display_Formatter
	 */
	private function formatter_for( $event_date_id ) {
		$_GET['se-date'] = (string) $event_date_id; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$formatter = new SE_Date_Display_Formatter( $this->event_id );
		$formatter->set_date_only( true );
		$formatter->set_date_format( 'Y-m-d' );

		return $formatter;
	}

	/**
	 * The event's dates, as the templates fetch them.
	 *
	 * @return array
	 */
	private function event_dates() {
		return se_event_get_event_dates( $this->event_id );
	}

	/**
	 * Every date, whichever position it sits in, prints itself.
	 *
	 * Indexes 1 and 2 are the bug: array_filter() preserves keys, so [0] is
	 * undefined and today's date is printed instead.
	 *
	 * @return void
	 */
	public function test_header_date_shows_the_requested_date_whatever_its_position() {
		foreach ( $this->date_ids as $index => $date_id ) {
			$this->assertSame(
				wp_date( 'Y-m-d', $this->starts[ $index ] ),
				$this->formatter_for( $date_id )->get_header_date( $this->event_dates() ),
				sprintf( 'Date at position %d should print its own date.', $index )
			);
		}
	}

	/**
	 * The header must never fall back to today.
	 *
	 * Stated separately because it is the visible symptom: wp_date() given null
	 * returns the current time, so the page silently shows today as the event
	 * date rather than failing.
	 *
	 * @return void
	 */
	public function test_header_date_never_prints_todays_date() {
		$today = wp_date( 'Y-m-d' );

		$this->assertNotSame(
			$today,
			$this->formatter_for( $this->date_ids[1] )->get_header_date( $this->event_dates() ),
			'A non-first date must not print today\'s date.'
		);
	}

	/**
	 * An unknown ?se-date= falls back to the event's own range, not today.
	 *
	 * The fallback is the grouped branch, which spans earliest start to latest
	 * end rather than picking a single date.
	 *
	 * @return void
	 */
	public function test_unknown_date_id_falls_back_to_the_event_range() {
		$this->assertSame(
			wp_date( 'Y-m-d', $this->starts[0] ) . ' &ndash; ' . wp_date( 'Y-m-d', end( $this->starts ) + 3600 ),
			$this->formatter_for( 999999 )->get_header_date( $this->event_dates() ),
			'An unmatched date should fall back to the event range, earliest start to latest end.'
		);
	}

	/**
	 * render_active_date() already handles this — the reference for the fix.
	 *
	 * @return void
	 */
	public function test_render_active_date_already_handles_a_non_first_date() {
		$this->assertSame(
			wp_date( 'Y-m-d', $this->starts[1] ),
			$this->formatter_for( $this->date_ids[1] )->render_active_date( $this->event_dates() ),
			'render_active_date() uses array_values() and should already be correct.'
		);
	}
}
