<?php
/**
 * Custom functionionaly and actions for event dates.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Event Dates
 */
class SE_Event_Dates {
	public const UPDATE_QUERY_DATES_HOOK = 'se_event_update_query_dates_cron';

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( self::UPDATE_QUERY_DATES_HOOK, array( __CLASS__, 'handle_cron' ) );
		add_action( 'init', array( __CLASS__, 'register_cron_event' ) );
		add_action( 'update_option_se_options', array( __CLASS__, 'maybe_remove_cron_job' ), 10, 2 );
	}

	/**
	 * Get the interval for the cron event.
	 *
	 * @return string
	 */
	public static function get_cron_interval(): string {
		return esc_attr( apply_filters( 'se_event_update_query_dates_interval', 'hourly' ) );
	}

	/**
	 * Checks if the events dates should be updated.
	 *
	 * @return boolean
	 */
	public static function should_update_event_query_dates(): bool {
		$should_update = false;

		// Get the options.
		$options = get_option( 'se_options', null );

		// If we have no options.
		if ( ! $options ) {
			return $should_update;
		}

		// If se_event_dates_updated is not set, return false.
		if ( ! isset( $options['update_start_end_dates'] ) ) {
			return $should_update;
		}

		return 'on' === $options['update_start_end_dates'] ? true : false;
	}

	/**
	 * Self register the cron event.
	 *
	 * @return void
	 */
	public static function register_cron_event() {
		// Bail if disabled.
		if ( ! self::should_update_event_query_dates() ) {
			return;
		}

		if ( ! wp_next_scheduled( self::UPDATE_QUERY_DATES_HOOK ) ) {
			wp_schedule_event( time(), self::get_cron_interval(), self::UPDATE_QUERY_DATES_HOOK );
		}
	}

	/**
	 * Checks if we should remove the cron job.
	 *
	 * @param mixed $old_value Old value.
	 * @param mixed $new_value New value.
	 *
	 * @return void
	 */
	public static function maybe_remove_cron_job( $old_value, $new_value ): void {
		if ( ! isset( $new_value['update_start_end_dates'] ) || 'on' !== $new_value['update_start_end_dates'] ) {
			self::clear_cron_events();
		}
	}

	/**
	 * Clear all existing cron events.
	 *
	 * @return void
	 */
	public static function clear_cron_events() {
		wp_clear_scheduled_hook( self::UPDATE_QUERY_DATES_HOOK );
	}

	/**
	 * Handle cron.
	 *
	 * @return void
	 */
	public static function handle_cron() {
		// IF we are set to not update, return.
		if ( ! self::should_update_event_query_dates() ) {
			return;
		}

		// Remove all hooks.
		remove_action( 'pre_get_posts', array( SE_Event_Post_Type::class, 'pre_get_posts' ) );

		// Get all events where the end date is within the defined range.
		$range  = apply_filters( 'se_event_update_dates_search_range', 48 * HOUR_IN_SECONDS );
		$events = new WP_Query(
			array(
				'post_type'      => SE_Event_Post_Type::$post_type,
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => 'se_event_date_start',
						'value'   => array(
							SE_Calendar::get_instance()->create_date_time( 'now' )->modify( '-' . $range . ' seconds' )->format( 'U' ),
							SE_Calendar::get_instance()->create_date_time( 'now' )->format( 'U' ),
						),
						'compare' => 'BETWEEN',
					),
				),
			)
		);

		// if we have dates, process them.
		if ( $events->have_posts() ) {
			// Loop through the events and update the dates.
			while ( $events->have_posts() ) {
				$events->the_post();
				// Check if event should be updated.
				if ( (bool) apply_filters( 'se_event_update_query_dates_skip', false, get_the_ID() ) ) {
					continue;
				}
				se_event_update_event_query_dates( get_the_ID() );

				do_action( 'se_event_updated_query_dates', get_the_ID() );
			}
		}

		// Reset the post data.
		wp_reset_postdata();

		// Add the hooks back.
		add_action( 'pre_get_posts', array( SE_Event_Post_Type::class, 'pre_get_posts' ) );
	}
}

// Self initialization.
SE_Event_Dates::init();
