<?php
/**
 * GH-87 item 6: the event-date accessors query once per date.
 *
 * se_event_get_event_dates() (event-functions.php:768) fetches the date IDs in
 * one query, then reads five meta keys per date with nothing priming the meta
 * cache, so the cost grows with the number of dates. The calendar's own
 * assembly, SE_Event_Query_Utils::map_events_dates_to_event_dates(), does the
 * same thing per date.
 *
 * These compare a small set against a larger one rather than asserting a fixed
 * number, so they measure growth and do not need rewriting when an unrelated
 * query is added or removed.
 *
 * @package Simple_Events
 */
class EventDateQueryCostTest extends WP_UnitTestCase {

	/**
	 * SQL captured during a measured call.
	 *
	 * @var array<string>
	 */
	private $queries = array();

	/**
	 * Whether the query filter is currently recording.
	 *
	 * @var boolean
	 */
	private $recording = false;

	/**
	 * Start capturing SQL.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		add_filter(
			'query',
			function ( $sql ) {
				if ( $this->recording ) {
					$this->queries[] = $sql;
				}
				return $sql;
			}
		);
	}

	/**
	 * Create a published event carrying the given number of dates.
	 *
	 * @param integer $count How many dates.
	 *
	 * @return integer Event ID.
	 */
	private function make_event_with_dates( $count ) {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);

		for ( $i = 0; $i < $count; $i++ ) {
			$start = strtotime( '+' . ( $i + 1 ) . ' days' );
			se_event_create_event_date(
				$event_id,
				array(
					'start_date' => $start,
					'end_date'   => $start + 7200,
					'all_day'    => false,
				)
			);
		}

		return $event_id;
	}

	/**
	 * Run a callback with a cold cache and return how many queries it issued.
	 *
	 * @param callable $callback The work to measure.
	 *
	 * @return integer
	 */
	private function count_queries( callable $callback ) {
		wp_cache_flush();

		$this->queries   = array();
		$this->recording = true;

		$callback();

		$this->recording = false;

		return count( $this->queries );
	}

	/**
	 * Reading an event's dates must not cost a query per date.
	 *
	 * @return void
	 */
	public function test_get_event_dates_does_not_scale_with_date_count() {
		$small = $this->make_event_with_dates( 2 );
		$large = $this->make_event_with_dates( 10 );

		$small_cost = $this->count_queries(
			function () use ( $small ) {
				se_event_get_event_dates( $small );
			}
		);

		$large_cost = $this->count_queries(
			function () use ( $large ) {
				se_event_get_event_dates( $large );
			}
		);

		$this->assertSame(
			$small_cost,
			$large_cost,
			"se_event_get_event_dates() cost {$small_cost} queries for 2 dates and {$large_cost} for 10 — it should not grow with the number of dates."
		);
	}

	/**
	 * The calendar's own assembly must not cost a query per date either.
	 *
	 * @return void
	 */
	public function test_map_events_dates_does_not_scale_with_date_count() {
		$this->make_event_with_dates( 2 );

		$small_dates = get_posts(
			array(
				'post_type'      => 'se-event-date',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$small_cost = $this->count_queries(
			function () use ( $small_dates ) {
				SE_Event_Query_Utils::map_events_dates_to_event_dates( $small_dates );
			}
		);

		$this->make_event_with_dates( 10 );

		$large_dates = get_posts(
			array(
				'post_type'      => 'se-event-date',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$large_cost = $this->count_queries(
			function () use ( $large_dates ) {
				SE_Event_Query_Utils::map_events_dates_to_event_dates( $large_dates );
			}
		);

		$this->assertGreaterThan( count( $small_dates ), count( $large_dates ), 'Fixture is wrong: the larger set should hold more dates.' );

		$this->assertSame(
			$small_cost,
			$large_cost,
			"map_events_dates_to_event_dates() cost {$small_cost} queries for " . count( $small_dates ) . ' dates and ' . "{$large_cost} for " . count( $large_dates ) . ' — it should not grow with the number of dates.'
		);
	}
}
