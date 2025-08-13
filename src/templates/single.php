<?php
/**
 * The Template for displaying single event pages.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header( 'events' );

/**
 * Hook: simple_events_before_main_content.
 */
do_action( 'se_before_main_content' );

?>

	<?php
	while ( have_posts() ) :
		the_post();
		?>

		<?php SE_TEMPLATE_LOADER::get_template_part( 'content', 'single' ); ?>

	<?php endwhile; // end of the loop. ?>

<?php

/**
 * Hook: simple_events_after_main_content.
 */
do_action( 'se_after_main_content' );

get_footer( 'events' );
