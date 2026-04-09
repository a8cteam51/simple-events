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
