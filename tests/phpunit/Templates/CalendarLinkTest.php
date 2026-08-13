<?php
/**
 * GH-88: se_event_get_calendar_link() must produce a valid URL.
 *
 * The function builds the query string correctly, choosing '?' or '&' to suit
 * the permalink (event-functions.php:566-572), then discards that value and
 * rebuilds with a hardcoded '?' (:574-576). On plain permalinks the result is
 * '?p=123?se-date=456', which no query parser reads.
 *
 * @package Simple_Events
 */
class CalendarLinkTest extends WP_UnitTestCase {

	/**
	 * The parent event.
	 *
	 * @var integer
	 */
	private $event_id = 0;

	/**
	 * A child date of that event.
	 *
	 * @var integer
	 */
	private $date_id = 0;

	/**
	 * An event with one date.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->event_id = $this->factory->post->create(
			array(
				'post_type'    => 'se-event',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:simple-events/event-info /-->',
			)
		);

		$start = strtotime( '2030-06-15 10:00:00' );
		$date  = se_event_create_event_date(
			$this->event_id,
			array(
				'start_date' => $start,
				'end_date'   => $start + 3600,
				'all_day'    => false,
			)
		);

		$this->assertNotNull( $date, 'Fixture event-date should have been created.' );

		$this->date_id = $date->ID;
	}

	/**
	 * Plain permalinks already carry '?p=', so the date must join with '&'.
	 *
	 * @return void
	 */
	public function test_plain_permalinks_append_the_date_with_an_ampersand() {
		$this->set_permalink_structure( '' );

		$link = se_event_get_calendar_link( $this->event_id, $this->date_id );

		$this->assertSame(
			1,
			substr_count( $link, '?' ),
			sprintf( 'A URL may only have one query separator, got "%s".', $link )
		);
		$this->assertStringContainsString(
			'&se-date=' . $this->date_id,
			$link,
			sprintf( 'The date should join an existing query string, got "%s".', $link )
		);
	}

	/**
	 * The date must be readable as a query parameter, not part of another value.
	 *
	 * The visible symptom: parse_str() on '?p=123?se-date=456' yields p as
	 * '123?se-date=456' and no se-date at all, so the link opens the event on
	 * its first date rather than the one that was clicked.
	 *
	 * @return void
	 */
	public function test_plain_permalink_date_is_parseable() {
		$this->set_permalink_structure( '' );

		$link = se_event_get_calendar_link( $this->event_id, $this->date_id );

		parse_str( (string) wp_parse_url( $link, PHP_URL_QUERY ), $query );
		parse_str( (string) wp_parse_url( get_the_permalink( $this->event_id ), PHP_URL_QUERY ), $permalink_query );

		$this->assertArrayHasKey( 'se-date', $query, sprintf( 'se-date should be a query parameter in "%s".', $link ) );
		$this->assertSame( (string) $this->date_id, $query['se-date'] );

		// The permalink identifies a CPT by ?se-event=slug, so assert whatever it
		// used survives rather than assuming ?p=.
		foreach ( $permalink_query as $key => $value ) {
			$this->assertSame( $value, $query[ $key ] ?? null, sprintf( 'Permalink parameter "%s" must survive intact.', $key ) );
		}
	}

	/**
	 * Pretty permalinks have no query string, so the date opens one.
	 *
	 * @return void
	 */
	public function test_pretty_permalinks_append_the_date_with_a_question_mark() {
		$this->set_permalink_structure( '/%postname%/' );

		// The post type's permastruct is built at init, when the structure was
		// still plain, so rebuild it the way the plugin's own flush does.
		SE_Event_Post_Type::register_post_type();
		flush_rewrite_rules();

		$permalink = get_the_permalink( $this->event_id );

		$this->assertStringNotContainsString(
			'?',
			$permalink,
			sprintf( 'This test is only meaningful on a pretty permalink, got "%s".', $permalink )
		);

		$link = se_event_get_calendar_link( $this->event_id, $this->date_id );

		$this->assertStringContainsString( '?se-date=' . $this->date_id, $link );
		$this->assertSame( 1, substr_count( $link, '?' ), sprintf( 'Got "%s".', $link ) );
	}

	/**
	 * With no date id the permalink is returned untouched.
	 *
	 * @return void
	 */
	public function test_no_date_id_returns_the_bare_permalink() {
		$this->set_permalink_structure( '' );

		$this->assertSame(
			get_the_permalink( $this->event_id ),
			se_event_get_calendar_link( $this->event_id ),
			'Without a date the link is just the event permalink.'
		);
	}

	/**
	 * An external link set to open externally wins, and takes no date.
	 *
	 * @return void
	 */
	public function test_external_link_is_returned_when_set_to_open_externally() {
		update_post_meta( $this->event_id, 'se_event_external_link', 'https://example.com/tickets' );
		update_post_meta( $this->event_id, 'se_open_external_link', true );

		$this->assertSame(
			'https://example.com/tickets',
			se_event_get_calendar_link( $this->event_id, $this->date_id )
		);
	}

	/**
	 * An external link not set to open externally is ignored.
	 *
	 * @return void
	 */
	public function test_external_link_is_ignored_when_not_set_to_open_externally() {
		$this->set_permalink_structure( '' );

		update_post_meta( $this->event_id, 'se_event_external_link', 'https://example.com/tickets' );
		update_post_meta( $this->event_id, 'se_open_external_link', false );

		$link = se_event_get_calendar_link( $this->event_id, $this->date_id );

		$this->assertStringNotContainsString( 'example.com', $link );
		$this->assertStringContainsString( '&se-date=' . $this->date_id, $link );
	}
}
