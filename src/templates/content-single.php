<?php
/**
 * The Template for displaying single event content.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php
		/**
		 * Hook: simple_events_archive_content.
		 */
		do_action( 'simple_events_single_content' );
	?>
</div>
