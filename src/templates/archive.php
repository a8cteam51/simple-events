<?php
/**
 * The Template for displaying event archives.
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

<header class="page-header">
	<?php the_archive_title( '<h1 class="page-title">', '</h1>' ); ?>
</header><!-- .page-header -->

<?php if ( have_posts() ) : ?>

	<?php
		/**
		 * Hook: simple_events_archive_start.
		 */
		do_action( 'se_archive_start' );
	?>

	<ul class="simple-events-archive">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<?php SE_TEMPLATE_LOADER::get_template_part( 'content', 'archive' ); ?>
		<?php endwhile; ?>
	</ul>

	<?php
		/**
		 * Hook: simple_events_archive_end.
		 */
		do_action( 'se_archive_end' );
	?>
<?php else : ?>
	<p><?php esc_html_e( 'No events were found matching your selection.', 'simple-events' ); ?></p>
<?php endif; ?>

<?php

/**
 * Hook: simple_events_after_main_content.
 */
do_action( 'se_after_main_content' );

get_footer( 'events' );
