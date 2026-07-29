<?php
/**
 * The Template for displaying event archives content.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If the post type is an event date, change post context to its parent.
if ( get_post_type() === Simple_Events_Event_Post_Type::$event_date_post_type ) {
	global $post;
	$simple_events_post_event_date = get_post( get_the_ID() );
	// Validate that we have a valid event date post with a parent
	if ( $simple_events_post_event_date && $simple_events_post_event_date->post_parent ) {
		$simple_events_parent_post = get_post( $simple_events_post_event_date->post_parent );
		if ( $simple_events_parent_post ) {
			$post                = $simple_events_parent_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$post->event_date_id = $simple_events_post_event_date->ID;
		}
	}
}
?>

<li id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php
		/**
		 * Hook: simple_events_archive_content.
		 */
		do_action( 'simple_events_archive_content' );
	?>
</li>
