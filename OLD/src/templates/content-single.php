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
		 * Hook: se_archive_content.
		 */
		do_action( 'se_single_content' );
	?>
</div>
