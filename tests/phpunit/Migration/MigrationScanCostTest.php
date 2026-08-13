<?php
/**
 * GH-87 item 5: the migration scan runs twice per admin request, uncached.
 *
 * has_events_to_migrate() fires on admin_init (class-se-settings.php:345) and
 * again on admin_notices (back-compat.php:41). Each call runs
 * get_events_to_migrate(), which asks for every unmigrated event as a full post
 * object with posts_per_page => -1, only to compare count() against zero.
 *
 * These measure the SQL the function actually causes, so they hold regardless
 * of how the fix is implemented.
 *
 * @package Simple_Events
 */
class MigrationScanCostTest extends WP_UnitTestCase {

	/**
	 * Every SQL statement issued that touches the version meta.
	 *
	 * @var array<string>
	 */
	private $scan_queries = array();

	/**
	 * Start capturing the migration scan's SQL.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->scan_queries = array();

		add_filter(
			'query',
			function ( $sql ) {
				// The scan only — not the postmeta reads and writes that
				// creating the fixtures produces.
				if ( false !== strpos( $sql, 'se_event_version' ) && false !== strpos( $sql, 'FROM wp_posts' ) ) {
					$this->scan_queries[] = $sql;
				}
				return $sql;
			}
		);
	}

	/**
	 * Discard anything captured while building fixtures.
	 *
	 * @return void
	 */
	private function start_capture() {
		$this->scan_queries = array();
	}

	/**
	 * Create published events and strip the version meta, so they are legacy
	 * events the migration scan must find.
	 *
	 * @param integer $count How many to create.
	 *
	 * @return array<int>
	 */
	private function make_legacy_events( $count ) {
		$ids = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$event_id = $this->factory->post->create(
				array(
					'post_type'   => 'se-event',
					'post_status' => 'publish',
					'post_title'  => 'Legacy Event ' . $i,
				)
			);
			delete_post_meta( $event_id, 'se_event_version' );
			$ids[] = $event_id;
		}

		return $ids;
	}

	/**
	 * Both admin hooks fire in one request, so asking twice must cost one query.
	 *
	 * @return void
	 */
	public function test_asking_twice_in_one_request_runs_one_scan() {
		$this->make_legacy_events( 5 );
		$this->start_capture();

		$this->assertTrue( SE_Migrate_Events::has_events_to_migrate() );
		$this->assertTrue( SE_Migrate_Events::has_events_to_migrate() );

		$this->assertCount(
			1,
			$this->scan_queries,
			'has_events_to_migrate() should scan once per request, not once per caller.'
		);
	}

	/**
	 * An existence check must not select whole post rows. WP_Query selects
	 * wp_posts.* for post objects and wp_posts.ID for fields => 'ids'.
	 *
	 * @return void
	 */
	public function test_existence_check_does_not_select_whole_posts() {
		$this->make_legacy_events( 5 );
		$this->start_capture();

		SE_Migrate_Events::has_events_to_migrate();

		$this->assertNotEmpty( $this->scan_queries, 'Expected the scan to run.' );

		$this->assertStringNotContainsString(
			'wp_posts.*',
			$this->scan_queries[0],
			'An existence check should not load every unmigrated event as a post object.'
		);
	}

	/**
	 * An existence check needs one row, not every match.
	 *
	 * @return void
	 */
	public function test_existence_check_asks_for_a_single_row() {
		$this->make_legacy_events( 5 );
		$this->start_capture();

		SE_Migrate_Events::has_events_to_migrate();

		$this->assertNotEmpty( $this->scan_queries, 'Expected the scan to run.' );

		$this->assertMatchesRegularExpression(
			'/LIMIT\s+0,\s*1\b/i',
			$this->scan_queries[0],
			'An existence check should stop at the first match.'
		);
	}

	/**
	 * The full list is still needed by the migration itself, so it must keep
	 * returning every unmigrated event.
	 *
	 * @return void
	 */
	public function test_get_events_to_migrate_still_returns_them_all() {
		$expected = $this->make_legacy_events( 5 );

		$actual = array_map(
			static function ( $post ) {
				return (int) $post->ID;
			},
			SE_Migrate_Events::get_events_to_migrate()
		);

		sort( $expected );
		sort( $actual );

		$this->assertSame( $expected, $actual );
	}
}
