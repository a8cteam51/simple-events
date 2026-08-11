<?php
/**
 * Tests for the event-category archive main query (GH-87 item 1).
 *
 * SE_Event_Post_Type::pre_get_posts() resolves a category archive by first
 * querying the parent se-event posts that carry the term, then restricting the
 * archive to those events' se-event-date children via post__in.
 *
 * When no parent events match, class-se-event-post-type.php:598 sets
 * $date_post_ids = null under a comment reading "return no results" — but :608
 * only applies post__in when the value is not null, so the branch meant to
 * return nothing is the branch that applies no restriction at all.
 *
 * @package Simple_Events
 */
class ArchiveCategoryQueryTest extends WP_UnitTestCase {

	/**
	 * Term ID of a category with events assigned to it.
	 *
	 * @var integer
	 */
	private $populated_term;

	/**
	 * Term ID of a category with no events assigned to it.
	 *
	 * @var integer
	 */
	private $empty_term;

	/**
	 * Seed two categories — one with events, one deliberately empty — and three
	 * events, only two of which are categorised.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->populated_term = $this->factory->term->create(
			array(
				'taxonomy' => 'se-event-category',
				'name'     => 'Populated Category',
				'slug'     => 'populated-category',
			)
		);

		$this->empty_term = $this->factory->term->create(
			array(
				'taxonomy' => 'se-event-category',
				'name'     => 'Empty Category',
				'slug'     => 'empty-category',
			)
		);

		$in_category = $this->make_event( 'Categorised Event A', '+10 days' );
		$also_in     = $this->make_event( 'Categorised Event B', '+20 days' );
		$this->make_event( 'Uncategorised Event', '+30 days' );

		wp_set_object_terms( $in_category, array( $this->populated_term ), 'se-event-category' );
		wp_set_object_terms( $also_in, array( $this->populated_term ), 'se-event-category' );
	}

	/**
	 * Create a published event carrying a single future date.
	 *
	 * The date is written both as a child se-event-date post and into the
	 * se_event_dates meta, because se_event_create_event_date() writes meta on
	 * the date post only and the archive orders on se_event_date_start, which is
	 * derived from se_event_dates by se_event_update_event_query_dates().
	 *
	 * @param string $title  Event title.
	 * @param string $offset strtotime offset for the event's single date.
	 *
	 * @return integer
	 */
	private function make_event( $title, $offset ) {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);

		$start = strtotime( $offset );

		se_event_create_event_date(
			$event_id,
			array(
				'start_date' => $start,
				'end_date'   => $start + 7200,
				'all_day'    => false,
			)
		);

		update_post_meta(
			$event_id,
			'se_event_dates',
			array(
				array(
					'datetime_start' => (string) $start,
					'datetime_end'   => (string) ( $start + 7200 ),
					'all_day'        => false,
				),
			)
		);

		se_event_update_event_query_dates( $event_id );

		return $event_id;
	}

	/**
	 * The titles the main query returned, after the_posts has remapped
	 * se-event-date results back onto their parent events.
	 *
	 * @return array<string>
	 */
	private function queried_titles() {
		return array_map(
			function ( $post ) {
				return get_the_title( $post->ID );
			},
			$GLOBALS['wp_query']->posts
		);
	}

	/**
	 * Control: a category that does match events lists exactly those events.
	 *
	 * Without this, the zero-match assertion below would also pass if the
	 * category archive were simply broken and never listed anything.
	 *
	 * @return void
	 */
	public function test_populated_category_archive_lists_only_its_own_events() {
		$this->go_to( get_term_link( $this->populated_term, 'se-event-category' ) );

		$this->assertTrue( is_tax( 'se-event-category' ), 'Expected a category archive request.' );

		$titles = $this->queried_titles();

		sort( $titles );
		$this->assertSame(
			array( 'Categorised Event A', 'Categorised Event B' ),
			$titles
		);
	}

	/**
	 * GH-87 item 1, second path: a category whose events have no dates.
	 *
	 * The parent query at :594 matches, so :600 runs and
	 * get_event_dates_from_events() returns an empty array — no event has any
	 * dates to collect. That passes the null check at :608, so post__in is set
	 * to an empty array and :610 strips the se-event-category query var, which
	 * is the only thing restricting the zero-match case.
	 *
	 * @return void
	 */
	public function test_category_whose_events_have_no_dates_lists_no_events() {
		$dateless_term = $this->factory->term->create(
			array(
				'taxonomy' => 'se-event-category',
				'name'     => 'Dateless Category',
				'slug'     => 'dateless-category',
			)
		);

		// A published, categorised event carrying no se-event-date children.
		$dateless_event = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
				'post_title'  => 'Dateless Event',
			)
		);
		wp_set_object_terms( $dateless_event, array( $dateless_term ), 'se-event-category' );

		$this->assertSame(
			array(),
			se_event_get_event_dates( $dateless_event ),
			'Fixture is wrong: the event was expected to have no dates.'
		);

		$this->go_to( get_term_link( $dateless_term, 'se-event-category' ) );

		$this->assertTrue( is_tax( 'se-event-category' ), 'Expected a category archive request.' );

		// The events seeded in set_up() belong to other categories or none, so
		// anything from them appearing here is the restriction having vanished.
		$this->assertSame(
			array(),
			$this->queried_titles(),
			'A category whose events have no dates must not list other events.'
		);
	}

	/**
	 * GH-87 item 1: a category matching zero events must list zero events.
	 *
	 * @return void
	 */
	public function test_category_matching_no_events_lists_no_events() {
		$this->go_to( get_term_link( $this->empty_term, 'se-event-category' ) );

		$this->assertTrue( is_tax( 'se-event-category' ), 'Expected a category archive request.' );

		$this->assertSame(
			array(),
			$this->queried_titles(),
			'A category with no events must not list any events.'
		);
	}
}
