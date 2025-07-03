<?php
/**
 * Handles the functionality to update event dates in the database.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrate Events
 */
class SE_Migrate_Events {

	/**
	 * The map of commands to run
	 *
	 * The key is the min version that doesnt need to be run.
	 *
	 * @param array
	 */
	private const VERSION_UPGRADES = array(
		'2.0.0' => array( 'migrate_1_0_0_to_2_0_0' ),
	);

	/**
	 * Initialize the class.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_route' ) );
		add_action( 'init', array( __CLASS__, 'register_cli_command' ) );
		add_action( 'se_migrate_events', array( __CLASS__, 'migrate_events' ) );
	}


	/**
	 * Registers the rest route.
	 *
	 * @return void
	 */
	public static function register_rest_route() {
		// The Namespace
		$namespace = 'simple-events';

		// Route to pass a list of events to update.
		register_rest_route(
			$namespace,
			'/migrate-events',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'migrate_events_rest' ),
				'permission_callback' => function () {
					return true;
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Migrate events.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public static function migrate_events( $request ) {
	}

	/**
	 * Registesrs the CLI command.
	 *
	 * @return void
	 */
	public static function register_cli_command() {
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		// Register the command.
		WP_CLI::add_command(
			'se migrate-events',
			array( __CLASS__, 'migrate_events_cli' )
		);
	}

	/**
	 * Migrate events via CLI.
	 *
	 * @param array $args       The command arguments.
	 * @param array $assoc_args The command associative arguments.
	 * @return void
	 */
	public static function migrate_events_cli( $args, $assoc_args ) {}

	/**
	 * Migrate events via REST.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public static function migrate_events_rest( $request ) {
		// Check if we have events in the body (form-data).
		$event_ids = $request->get_param( 'events' );

		// If no events are provided, return an error.
		if ( empty( $event_ids ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'No events provided for migration.', 'simple-events' ),
				),
				400
			);
		}

		// Convert the event IDs from a string to an array.
		$event_ids = json_decode( $event_ids, true );

		// if there was an error decoding the JSON, return an error.
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Invalid event IDs provided.' . json_last_error_msg(), 'simple-events' ),
				),
				400
			);
		}

		// Cast to an array of integers from JSON
		$event_ids = array_map( 'intval', $event_ids );
		// Validate the event IDs.

		try {
			$response = self::migrate_events_by_ids( $event_ids );
		} catch ( \Throwable $th ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'An error occurred while migrating events.', 'simple-events' ),
					'error'   => $th->getMessage(),
				),
				500
			);
		}

		// Return the response.
		return new WP_REST_Response(
			array(
				'message' => __( 'Events migrated successfully.', 'simple-events' ),
				'data'    => $response,
			),
			200
		);
	}

	/**
	 * Migrate events.
	 *
	 * @param array<integer> $event_ids The event IDs to migrate.
	 *
	 * @return array<int, bool>
	 */
	private static function migrate_events_by_ids( $event_ids ) {
		// Iterate over the event IDs and update them.
		$results = array();

		foreach ( $event_ids as $event_id ) {
			// Check if the event exists.
			if ( ! get_post( $event_id ) ) {
				$results[ $event_id ] = false;
				continue;
			}

			// Migrate the event.
			$success              = self::migrate_event( $event_id );
			$results[ $event_id ] = $success;
		}

		return $results;
	}

	/**
	 * Migrate a single event.
	 *
	 * @param integer $event_id The event ID to migrate.
	 *
	 * @return boolean
	 */
	private static function migrate_event( $event_id ) {
		// Get the event post.
		$event_post = get_post( $event_id );

		if ( ! $event_post || 'se-event' !== $event_post->post_type ) {
			return false; // Not a valid event post.
		}

		try {
			// Get the version of the event.
			$migration_methods = self::get_migration_methods( get_post_meta( $event_id, 'se_event_version', true ) ?: '1.0.0' );
			foreach ( $migration_methods as $version => $methods ) {
				// Iterate over the methods.
				foreach ( $methods as $method ) {
					// Check if the method exists in the class.
					if ( ! method_exists( __CLASS__, $method ) ) {
						continue; // Skip if the method does not exist.
					}

					// Call the migration method.
					call_user_func( array( __CLASS__, $method ), $event_id );
				}

				// Update the posts meta to say updated to.
				update_post_meta( $event_id, 'se_event_version', $version );
			}
		} catch ( \Throwable $th ) {
			adump( $th->getMessage() );
			return false; // If any error occurs, return false.
		}
		return true; // If everything goes well, return true.
	}

	/**
	 * Get the list of methods based on the version.
	 *
	 * @param string $version The version to check.
	 *
	 * @return array<callable>
	 */
	private static function get_migration_methods( $version ) {
		// If the version is not set, return all methods.
		if ( empty( $version ) ) {
			return self::VERSION_UPGRADES;
		}

		// Filter the methods based on the version.
		return array_filter(
			self::VERSION_UPGRADES,
			function ( $methods, $min_version ) use ( $version ) {
				return version_compare( $version, $min_version, '<' );
			},
			ARRAY_FILTER_USE_BOTH
		);
	}



	##################
	# Migration Methods
	##################

	/**
	 * Migrate from version 1.0.0 to 2.0.0.
	 *
	 * @return void
	 */
	public static function migrate_1_0_0_to_2_0_0( int $event_id ): void {
		// Get all the events from its meta.
		$dates = get_post_meta( $event_id, 'se_event_dates', true );
		// Iterate over the dates.
		if ( ! is_array( $dates ) || empty( $dates ) ) {
			return; // No dates to migrate.
		}
		foreach ( $dates as $key => $date ) {
			// Unpack
			$start   = $date['datetime_start'] ?? '';
			$end     = $date['datetime_end'] ?? '';
			$all_day = $date['all_day'] ?? false;

			se_event_create_event_date(
				$event_id,
				array(
					'all_day'        => $all_day,
					'start_date'     => $start,
					'end_date'       => $end,
				)
			);
		}
	}
}

SE_Migrate_Events::init();
