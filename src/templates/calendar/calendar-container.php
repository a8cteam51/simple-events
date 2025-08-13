<?php
/**
 * Calendar Container
 *
 * @var array $args
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * To check if the screen is an editor screen
 *
 * @var bool
 */
$se_editor_screen = defined( 'REST_REQUEST' ) ? REST_REQUEST : false;

?>

<div class="simple-events-calendar <?php echo esc_attr( se_alignment( $args['attributes'] ) ); ?>">
	<?php

	// If it is an editor screen, add dynamic styles for real-time feedback in editor.
	if ( $se_editor_screen ) {
		printf( '<style>%s</style>', esc_html( se_apply_customization( $args['attributes'] ) ) );
	}

	SE_Template_Loader::get_template_part(
		'calendar/calendar',
		'main',
		true,
		$args
	);
	?>
</div>
