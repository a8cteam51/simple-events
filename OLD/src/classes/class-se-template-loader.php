<?php
/**
 * Template Loader.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template Loader Class.
 */
class SE_Template_Loader {

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template_include' ) );
	}

	/**
	 * Load a template.
	 *
	 * @param string $template Template to load.
	 *
	 * @return string
	 */
	public static function template_include( $template ) {
		// Return early if this view is not an event view.
		if ( ! is_singular( 'se-event' ) && ! is_post_type_archive( 'se-event' ) ) {
			return $template;
		}

		// Determine if standard single se-event templates are available in the theme
		// before replacing with the custom template in this plugin.
		if ( is_singular( 'se-event' ) ) {
			$event = get_post();

			$theme_templates = array(
				'single-se-event-' . sanitize_key( $event->post_name ) . '.php',
				'single-se-event.php',
			);

			// If a custom page template has been assigned to the event, add it to the front of the list.
			if ( '' !== $event->page_template ) {
				array_unshift( $theme_templates, $event->page_template );
			}

			// Determine if any of these templates is available in the theme.
			$single_event_template = get_query_template( 'singular', $theme_templates );

			if ( $single_event_template ) {
				return $single_event_template;
			}

			$fallback_template = 'single.php';
		}

		// Determine if standard se-event archive templates are available in the theme
		// before replacing with the custom template in this plugin.
		if ( is_archive( 'se-event' ) ) {
			$theme_templates = array(
				'archive-se-event.php',
			);

			// Determine if any of these templates is available in the theme.
			$archive_event_template = get_query_template( 'archive', $theme_templates );

			if ( $archive_event_template ) {
				return $archive_event_template;
			}

			$fallback_template = 'archive.php';
		}

		// Retrieve the full path of the fallback template in this plugin.
		$fallback_template = self::locate_template( array( $fallback_template ) );

		if ( '' !== $fallback_template ) {
			return $fallback_template;
		}

		return $template;
	}

	/**
	 * Load a template.
	 *
	 * @param array   $template_names Template file(s) to search for, in order.
	 * @param boolean $load           If true the template file will be loaded if it is found.
	 * @param boolean $once           Whether to require_once or require. Default true. Has no effect if $load is false.
	 * @param array   $args           Arguments.
	 * @param boolean $return_html    Return generated HTML.
	 *
	 * @return string
	 */
	public static function locate_template( $template_names, $load = false, $once = true, $args = array(), $return_html = false ) {
		$located = '';

		foreach ( (array) $template_names as $template_name ) {
			if ( ! $template_name ) {
				continue;
			}

			if ( file_exists( SE_TEMPLATE_PATH . '/' . $template_name ) ) {
				$located = SE_TEMPLATE_PATH . '/' . $template_name;
				break;
			}
		}

		if ( $load && '' !== $located ) {
			if ( $return_html ) {
				ob_start();
				load_template( $located, $once, $args );
				$output = ob_get_contents();
				ob_end_clean();
				return $output;
			}
			load_template( $located, $once, $args );
		}

		return $located;
	}

	/**
	 * Loads a template part into a template.
	 *
	 * @param string  $slug        The slug name for the generic template.
	 * @param string  $name        The name of the specialised template.
	 * @param boolean $load        If true the template file will be loaded if it is found.
	 * @param array   $args        Arguments.
	 * @param boolean $return_html Return generated HTML.
	 *
	 * @return string
	 */
	public static function get_template_part( $slug, $name = null, $load = true, $args = array(), $return_html = false ) {
		$templates = array();

		if ( isset( $name ) ) {
			$templates[] = $slug . '-' . $name . '.php';
		}

		$templates[] = $slug . '.php';

		return self::locate_template( $templates, $load, false, $args, $return_html );
	}
}

SE_Template_Loader::init();
