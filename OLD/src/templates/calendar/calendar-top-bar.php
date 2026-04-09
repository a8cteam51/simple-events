<?php
/**
 * Calendar Top Bar
 *
 * @var array $args
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="simple-events-top-bar">

	<?php
	if ( 'top' === $args['arrow_position'] ) {
		SE_Template_Loader::get_template_part(
			'calendar/top-bar/nav',
			null,
			true,
			array(
				'previous_date' => $args['previous_date'],
				'next_date'     => $args['next_date'],
			)
		);
	}
	SE_Template_Loader::get_template_part(
		'calendar/top-bar/today',
	);

	SE_Template_Loader::get_template_part(
		'calendar/top-bar/current-date',
		null,
		true,
		array(
			'current_date' => $args['current_date'],
		)
	);
	?>
</div>
