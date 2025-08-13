<?php
/**
 * Back compat functionality.
 *
 * Action/filter hooks used for preserving compatibility across WordPress/Gutenberg versions.
 */

/**
 * Add admin class to target compatibility tweaks.
 *
 * @param string $classes Admin body classes.
 *
 * @return string
 */
function se_pre_gutenberg_14_3_0_compat( $classes ) {
	global $wp_version;

	if (
		version_compare( $wp_version, '6.1', '<' ) &&
		! ( defined( 'GUTENBERG_VERSION' ) && version_compare( GUTENBERG_VERSION, '14.3.0', '>=' ) )
	) {
		$classes .= ' se-datepicker-compat';
	}

	return $classes;
}

add_filter( 'admin_body_class', 'se_pre_gutenberg_14_3_0_compat' );

/**
 * Adds an admin notice to say we have events that need to be migrated.
 *
 * @return void
 */
function se_admin_notice_events_to_migrate() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Check if we have events that need to be migrated.
	if ( SE_Migrate_Events::has_events_to_migrate() ) {
		// Lets make the error large so it cant be ingnored with a link to the settings page.

		?>
		<div class="notice notice-error" style="border-left-color: #dc3232; background: #ffeaea; padding: 20px; margin: 20px 0; border-radius: 6px;">
			<h2 style="margin-top: 0; color: #dc3232; font-size: 18px; font-weight: bold;">
				⚠️ <?php esc_html_e( 'Simple Events Migration Required', 'simple-events' ); ?>
			</h2>
			<p style="font-size: 16px; margin: 15px 0;">
				<?php esc_html_e( 'We have events that need to be migrated to the new format. This must be completed before the events will function properly.', 'simple-events' ); ?>
			</p>
			<p style="margin: 20px 0 0 0;">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SE_Event_Post_Type::$post_type . '&page=settings#migrate_events' ) ); ?>"
					class="button button-primary button-large"
					style="background: #dc3232; border-color: #dc3232; color: #fff; font-size: 16px; padding: 10px 20px; text-decoration: none;">
					<?php esc_html_e( 'Go to Settings & Migrate Events', 'simple-events' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'se_admin_notice_events_to_migrate' );
