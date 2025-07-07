<?php
/**
 * The Template for displaying event archives content.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If the post type is an event date, change post context to its parent.
if ( get_post_type() === SE_Event_Post_Type::$event_date_post_type ) {
	global $post;
	$se_post_event_date  = get_post( get_the_ID() );
	// Validate that we have a valid event date post with a parent
	if ( $se_post_event_date && $se_post_event_date->post_parent ) {
		$se_parent_post = get_post( $se_post_event_date->post_parent );
		if ( $parent_post ) {
			$post                = $se_parent_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$post->event_date_id = $se_post_event_date->ID;
		}
	}
}
?>

<li id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php
		/**
		 * Hook: se_archive_content.
		 */
		do_action( 'se_archive_content' );
	?>
</li>
