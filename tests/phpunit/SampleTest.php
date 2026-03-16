<?php
/**
 * Sample test to verify the test infrastructure works.
 *
 * @package Simple_Events
 */
class SampleTest extends WP_UnitTestCase {

	/**
	 * Verify the plugin is loaded and the version constant is defined.
	 *
	 * @return void
	 */
	public function test_plugin_loaded() {
		$this->assertTrue( defined( 'SE_VERSION' ) );
	}

	/**
	 * Verify the se-event post type is registered.
	 *
	 * @return void
	 */
	public function test_event_post_type_registered() {
		$this->assertTrue( post_type_exists( 'se-event' ) );
	}

	/**
	 * Verify the se-event-date post type is registered.
	 *
	 * @return void
	 */
	public function test_event_date_post_type_registered() {
		$this->assertTrue( post_type_exists( 'se-event-date' ) );
	}
}
