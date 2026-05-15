<?php
/**
 * Tests for the event migration detector.
 *
 * The core requirement: a freshly created event must NOT be flagged for
 * migration just because a date /sync never happened to write the version
 * meta. A genuinely legacy event with no version meta MUST still be flagged.
 *
 * These assert the user-facing outcome (presence in the migration queue)
 * rather than the meta mechanism, so the guard survives changes to how the
 * version is tracked.
 *
 * @package Simple_Events
 */
class EventMigrationTest extends WP_UnitTestCase {

	/**
	 * Pluck post IDs flagged for migration.
	 *
	 * @return array<int>
	 */
	private function migration_ids(): array {
		return array_map(
			static function ( $post ) {
				return (int) $post->ID;
			},
			SE_Migrate_Events::get_events_to_migrate()
		);
	}

	/**
	 * A new event created directly as published must not be flagged for
	 * migration — even though no /sync ever ran.
	 *
	 * @testdox When a new event is created and published, the current version meta is stamped automatically on creation, so the migration check never flags it even though no date sync ever ran
	 *
	 * @return void
	 */
	public function test_new_published_event_is_not_flagged_for_migration() {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);

		$this->assertNotContains(
			$event_id,
			$this->migration_ids(),
			'A freshly created event should not appear in the migration queue.'
		);
	}

	/**
	 * The real Gutenberg flow: an event starts as an auto-draft and is then
	 * published. Once published it must not be flagged for migration.
	 *
	 * @testdox When an event moves from auto-draft to published (the normal Gutenberg editor flow), the version meta is stamped on that status transition, so the event is never wrongly flagged for migration
	 *
	 * @return void
	 */
	public function test_autodraft_event_published_is_not_flagged() {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'auto-draft',
			)
		);

		wp_update_post(
			array(
				'ID'          => $event_id,
				'post_status' => 'publish',
			)
		);

		$this->assertNotContains(
			$event_id,
			$this->migration_ids(),
			'An event published from auto-draft should not be flagged for migration.'
		);
		$this->assertFalse(
			SE_Migrate_Events::has_events_to_migrate(),
			'No events should require migration when only a fresh event exists.'
		);
	}

	/**
	 * Regression guard: the new-event behaviour must NOT mask a genuinely
	 * legacy event. An existing published event whose version meta is absent
	 * (and which is only re-saved, never transitioning from auto-draft/new)
	 * must still be flagged for migration.
	 *
	 * @testdox A genuinely legacy event with no version meta that is only re-saved (never transitioning from auto-draft) must still be detected by the migration system, so the new-event stamping does not accidentally skip real migrations
	 *
	 * @return void
	 */
	public function test_legacy_event_without_version_is_still_flagged() {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'se-event',
				'post_status' => 'publish',
			)
		);

		// Simulate a pre-2.0.0 event: no version meta, and it is not
		// transitioning out of auto-draft/new (just a normal re-save).
		delete_post_meta( $event_id, 'se_event_version' );
		wp_update_post(
			array(
				'ID'         => $event_id,
				'post_title' => 'Edited legacy event',
			)
		);

		$this->assertContains(
			$event_id,
			$this->migration_ids(),
			'A legacy event with no version meta must still be flagged for migration.'
		);
	}
}
