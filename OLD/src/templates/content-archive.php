<?php
/**
 * The Template for displaying event archives content.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
