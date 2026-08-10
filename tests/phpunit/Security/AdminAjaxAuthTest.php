<?php
/**
 * S-02: admin-ajax handlers with no capability or nonce check.
 *
 * Two wp_ajax_* actions are registered in class-se-settings.php:28-29:
 *
 *   wp_ajax_se_clear_orphaned_events          -> clear_orphaned_events()          :787
 *   wp_ajax_se_mark_existing_orders_as_completed -> mark_existing_orders_as_completed() :734
 *
 * Neither handler checks a capability or a nonce; both start straight at work.
 * clear_orphaned_events() calls wp_delete_post( $id, true ) at :805 — a permanent
 * delete, no trash. The only current_user_can() in the file is at :695, in
 * options_page_html(), which guards the settings screen and nothing else.
 * admin.js:196-249 posts only { action }, so no nonce is sent today either.
 *
 * Because the hooks are wp_ajax_ (not wp_ajax_nopriv_), WordPress blocks logged
 * out callers itself. The real exposure is any authenticated user: on a Box
 * Office site every ticket buyer holds Subscriber. These tests therefore target
 * Subscriber, plus the CSRF case of an administrator with no valid nonce.
 *
 * These assert the SECURE behaviour, so they fail against the current code.
 *
 * Note: ajax tests are excluded from the default run. Execute with:
 *   npm run test:php -- --group ajax
 *
 * @group ajax
 *
 * @package Simple_Events
 */
class AdminAjaxAuthTest extends WP_Ajax_UnitTestCase {

	/**
	 * The nonce action the handlers are expected to verify.
	 *
	 * @var string
	 */
	private $nonce_action = 'se_admin_ajax';

	/**
	 * Create an orphaned event date, i.e. one whose parent event does not exist.
	 *
	 * This is what clear_orphaned_events() targets: its query at :791-798 matches
	 * se-event-date posts with post_parent = 0 or a missing parent row.
	 *
	 * @return integer The orphaned event-date post ID.
	 */
	private function create_orphaned_event_date() {
		$orphan_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event-date',
				'post_status' => 'publish',
				'post_parent' => 0,
			)
		);

		$this->assertSame(
			'se-event-date',
			get_post_type( $orphan_id ),
			'Fixture should be an se-event-date post.'
		);

		return $orphan_id;
	}

	/**
	 * Fire an ajax action and report whether it was refused.
	 *
	 * A refusal may surface as either ajax die exception depending on how the
	 * guard is implemented (wp_die( -1 ) from check_ajax_referer, or
	 * wp_send_json_error). Both count; the side-effect assertions in each test
	 * are what actually prove the behaviour.
	 *
	 * @param string $action The ajax action name, without the wp_ajax_ prefix.
	 *
	 * @return boolean True if the handler terminated via an ajax die.
	 */
	private function fire( $action ) {
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			return true;
		} catch ( WPAjaxDieStopException $e ) {
			return true;
		}

		return false;
	}

	/**
	 * A Subscriber must not be able to permanently delete event-date posts.
	 *
	 * The deletion assertion is the one that matters: a status-only check would
	 * still pass if the handler deleted first and errored afterwards.
	 *
	 * @return void
	 */
	public function test_subscriber_cannot_clear_orphaned_events() {
		$orphan_id = $this->create_orphaned_event_date();
		$this->_setRole( 'subscriber' );

		$this->fire( 'se_clear_orphaned_events' );

		$this->assertNotNull(
			get_post( $orphan_id ),
			'A Subscriber must not be able to delete orphaned event dates.'
		);
	}

	/**
	 * An administrator without a valid nonce must be refused.
	 *
	 * This is the CSRF case: a logged-in admin who lands on an attacker's page
	 * has the capability but never intended the request.
	 *
	 * @return void
	 */
	public function test_administrator_without_nonce_cannot_clear_orphaned_events() {
		$orphan_id = $this->create_orphaned_event_date();
		$this->_setRole( 'administrator' );

		unset( $_POST['nonce'], $_REQUEST['nonce'] );

		$this->fire( 'se_clear_orphaned_events' );

		$this->assertNotNull(
			get_post( $orphan_id ),
			'An administrator request with no nonce must not delete orphaned event dates.'
		);
	}

	/**
	 * An administrator with a bad nonce must be refused.
	 *
	 * @return void
	 */
	public function test_administrator_with_invalid_nonce_cannot_clear_orphaned_events() {
		$orphan_id = $this->create_orphaned_event_date();
		$this->_setRole( 'administrator' );

		$nonce             = 'not-a-real-nonce';
		$_POST['nonce']    = $nonce;
		$_REQUEST['nonce'] = $nonce;

		$this->fire( 'se_clear_orphaned_events' );

		$this->assertNotNull(
			get_post( $orphan_id ),
			'An administrator request with an invalid nonce must not delete orphaned event dates.'
		);
	}

	/**
	 * The happy path: an administrator with a valid nonce still clears orphans.
	 *
	 * Guards against the fix being over-tight and breaking the settings button.
	 *
	 * @return void
	 */
	public function test_administrator_with_valid_nonce_clears_orphaned_events() {
		$orphan_id = $this->create_orphaned_event_date();
		$this->_setRole( 'administrator' );

		$nonce             = wp_create_nonce( $this->nonce_action );
		$_POST['nonce']    = $nonce;
		$_REQUEST['nonce'] = $nonce;

		$this->fire( 'se_clear_orphaned_events' );

		$this->assertNull(
			get_post( $orphan_id ),
			'An administrator with a valid nonce should still clear orphaned event dates.'
		);
	}

	/**
	 * A Subscriber must be refused before the order handler does any work.
	 *
	 * WooCommerce is not loaded in this environment, so wc_get_orders() is
	 * undefined. That is precisely the point: with the capability check in
	 * place the handler must terminate before ever reaching it. Against the
	 * unfixed code this test errors with "undefined function wc_get_orders",
	 * which is itself proof that an unprivileged request reaches the work.
	 *
	 * The order-completion behaviour itself cannot be asserted here; it needs a
	 * WooCommerce-enabled environment.
	 *
	 * @return void
	 */
	public function test_subscriber_cannot_mark_orders_as_completed() {
		$this->_setRole( 'subscriber' );

		$refused = $this->fire( 'se_mark_existing_orders_as_completed' );

		$this->assertTrue(
			$refused,
			'A Subscriber must be refused before the order handler reaches WooCommerce.'
		);
	}
}
