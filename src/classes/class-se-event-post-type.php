<?php
/**
 * Event Post Type
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post types Class.
 */
class SE_Event_Post_Type {
	/**
	 * This is the name of this post type.
	 *
	 * @var string
	 */
	public static $post_type = 'se-event';

	/**
	 * This is the slug of this post type.
	 *
	 * @var string
	 */
	public static $slug = 'events';

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 15 );
		add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_expired_events' ) );
		add_filter( 'get_the_archive_title', array( __CLASS__, 'the_archive_title' ) );
		register_activation_hook( __FILE__, array( __CLASS__, 'flush_rewrite_rules' ) );
		add_action( 'save_post', array( __CLASS__, 'delete_event_dates_if_no_event_info_block' ) );
			add_filter( 'is_protected_meta', array( __CLASS__, 'is_protected_meta' ), 10, 3 );
	}

	/**
	 * Post type registration.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		// Retrieve options for hiding/showing event info and event tickets blocks.
		$options                    = get_option( 'se_options' );
		$remove_event_tickets_block = isset( $options['remove_event_tickets_block'] ) ? $options['remove_event_tickets_block'] : false;

		$template = array(
			array( 'simple-events/event-info' ),
			array( 'simple-events/inner-blocks', array(), array( array( 'core/paragraph' ) ) ),
		);

		if ( ! $remove_event_tickets_block ) {
			$template[] = array( 'simple-events/event-tickets' );
		}

		register_post_type(
			self::$post_type,
			array(
				'labels'                => array(
					'name'                  => __( 'Events', 'simple-events' ),
					'singular_name'         => __( 'Event', 'simple-events' ),
					'all_items'             => __( 'All Events', 'simple-events' ),
					'archives'              => __( 'Event Archives', 'simple-events' ),
					'attributes'            => __( 'Event Attributes', 'simple-events' ),
					'insert_into_item'      => __( 'Insert into Event', 'simple-events' ),
					'uploaded_to_this_item' => __( 'Uploaded to this Event', 'simple-events' ),
					'featured_image'        => _x( 'Featured Image', 'se-event', 'simple-events' ),
					'set_featured_image'    => _x( 'Set featured image', 'se-event', 'simple-events' ),
					'remove_featured_image' => _x( 'Remove featured image', 'se-event', 'simple-events' ),
					'use_featured_image'    => _x( 'Use as featured image', 'se-event', 'simple-events' ),
					'filter_items_list'     => __( 'Filter Events list', 'simple-events' ),
					'items_list_navigation' => __( 'Events list navigation', 'simple-events' ),
					'items_list'            => __( 'Events list', 'simple-events' ),
					'new_item'              => __( 'New Event', 'simple-events' ),
					'add_new'               => __( 'Add New', 'simple-events' ),
					'add_new_item'          => __( 'Add New Event', 'simple-events' ),
					'edit_item'             => __( 'Edit Event', 'simple-events' ),
					'view_item'             => __( 'View Event', 'simple-events' ),
					'view_items'            => __( 'View Events', 'simple-events' ),
					'search_items'          => __( 'Search Events', 'simple-events' ),
					'not_found'             => __( 'No Events found', 'simple-events' ),
					'not_found_in_trash'    => __( 'No Events found in trash', 'simple-events' ),
					'parent_item_colon'     => __( 'Parent Event:', 'simple-events' ),
					'menu_name'             => __( 'Events', 'simple-events' ),
				),
				'public'                => true,
				'hierarchical'          => false,
				'show_ui'               => true,
				'show_in_nav_menus'     => true,
				'supports'              => array(
					'title',
					'editor',
					'thumbnail',
					'custom-fields',
					'excerpt',
					'author',
				),
				'rewrite'               => array(
					'slug'       => 'event',
					'with_front' => false,
				),
				'has_archive'           => self::$slug,
				'query_var'             => true,
				'menu_position'         => null,
				'menu_icon'             => 'dashicons-calendar-alt',
				'show_in_rest'          => true,
				'rest_base'             => self::$post_type,
				'rest_controller_class' => 'WP_REST_Posts_Controller',
				'template'              => $template,
				'template_lock'         => 'insert',
				'taxonomies'            => array(
					'post_tag',
				),
			)
		);
	}

	/**
	 * Taxonomy registration.
	 *
	 * @return void
	 */
	public static function register_taxonomy() {
		register_taxonomy(
			self::$post_type . '-category',
			self::$post_type,
			array(
				'description'       => __( 'Categories for simple event posts', 'simple-events' ),
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => 'events/category',
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Register meta keys.
	 *
	 * @return void
	 */
	public static function register_meta() {
		register_meta(
			'post',
			'se_event_location',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'string',
				'object_subtype' => self::$post_type,
			)
		);

		register_meta(
			'post',
			'se_event_venue',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'string',
				'object_subtype' => self::$post_type,
			)
		);

		register_meta(
			'post',
			'se_event_dates',
			array(
				'single'       => true,
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'datetime_start' => array(
									'type' => 'string',
								),
								'datetime_end'   => array(
									'type' => 'string',
								),
								'all_day'        => array(
									'type' => 'boolean',
								),
							),
						),
					),
				),
			)
		);

		register_meta(
			'post',
			'se_event_date_start',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'string',
				'object_subtype' => self::$post_type,
				'auth_callback'  => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_meta(
			'post',
			'se_event_date_end',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'string',
				'object_subtype' => self::$post_type,
				'auth_callback'  => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_meta(
			'post',
			'se_event_timezone',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'string',
				'object_subtype' => self::$post_type,
			)
		);

		register_meta(
			'post',
			'se_event_display_timezone',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
			)
		);

		register_meta(
			'post',
			'se_event_display_grouped',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => true,
			)
		);

		register_meta(
			'post',
			'se_event_hide_end_time',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => false,
			)
		);

		register_meta(
			'post',
			'se_event_hide_start_time',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => false,
			)
		);

		register_meta(
			'post',
			'se_event_add_calendar_links',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => false,
			)
		);

		register_meta(
			'post',
			'se_event_open_in_new_window',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => false,
			)
		);
		register_meta(
			'post',
			'se_event_external_link',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'string',
				'object_subtype' => self::$post_type,
			)
		);

		register_meta(
			'post',
			'se_event_external_link_label',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'string',
				'object_subtype' => self::$post_type,
				'default'        => esc_html__( 'Tickets', 'simple-events' ),
			)
		);

		register_meta(
			'post',
			'se_open_external_link',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => false,
			)
		);

		register_meta(
			'post',
			'se_event_modal_access',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => true,
			)
		);

		register_meta(
			'post',
			'se_show_modal_title',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => true,
			)
		);

		register_meta(
			'post',
			'se_show_modal_excerpt',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => true,
			)
		);

		register_meta(
			'post',
			'se_event_show_on_frontend',
			array(
				'show_in_rest'   => true,
				'single'         => true,
				'type'           => 'boolean',
				'object_subtype' => self::$post_type,
				'default'        => true,
			)
		);
	}

	/**
	 * Defines protected meta keys for the event post type.
	 *
	 * This method registers meta keys that are used to store event-related data.
	 *
	 * @param boolean $is_protected Whether the meta keys should be protected.
	 * @param string  $meta_key     The meta key to register.
	 * @param string  $meta_type    The type of the meta key.
	 *
	 * @return boolean
	 */
	public static function is_protected_meta( bool $is_protected, string $meta_key, string $meta_type = 'string' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$protected_keys = array( 'se_event_date_end', 'se_event_date_start' );

		if ( in_array( $meta_key, $protected_keys, true ) ) {
			return true;
		}
		return $is_protected;
	}

	/**
	 * Flush rewrite rules if the flag added during activation exists.
	 *
	 * @return void
	 */
	public static function maybe_flush_rewrite_rules() {
		if ( get_option( 'simple_events_flush_rewrite_rules_flag' ) ) {
			delete_option( 'simple_events_flush_rewrite_rules_flag' );
			flush_rewrite_rules();
		}
	}

	/**
	 * Order events by date.
	 *
	 * @param WP_Query $query WP_Query instance.
	 *
	 * @return void
	 */
	public static function pre_get_posts( $query ) {
		if ( is_admin() || ( defined( 'REST_API_REQUEST' ) && REST_API_REQUEST ) || ( isset( $query->query['type'] ) && 'calendar' === $query->query['type'] ) ) {
			return;
		}

		$options    = get_option( 'se_options' );
		$sort_order = ( isset( $options['reverse_events_order'] ) ) ? 'DESC' : 'ASC';

		// Check if there's an override for the sort order through the 'se_pre_get_posts_order_override' filter.
		$order_override = apply_filters( 'se_pre_get_posts_order_override', $query->get( 'se_event_order' ), $query );

		// If a custom order override exists, use it. Otherwise, stick with the existing sort order.
		$sort_order = ! empty( $order_override ) ? $order_override : $sort_order;

		if (
			( $query->is_main_query() && ( is_post_type_archive( self::$post_type )
			|| is_tax( self::$post_type . '-category' ) ) )
			|| ( ! $query->is_main_query() && self::$post_type === $query->get( 'post_type' ) && ! $query->get( 'se_countdown' ) && $query->get( 'sub-type' ) !== SE_Block_Variations::QUERY_LOOP_EVENTS )
		) {
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'meta_key', 'se_event_date_start' );
			$query->set( 'order', apply_filters( 'se_pre_get_posts_order', $sort_order, $query ) );

			// Values for which passed events should be hidden on Feed.
			$event_options = array( 'hide_events_on_both', 'hide_events_on_feed', 'on' );

			if ( isset( $options['hide_past_events'] ) && in_array( $options['hide_past_events'], $event_options, true ) ) {
				$query->set(
					'meta_query',
					array(
						array(
							'key'     => 'se_event_date_end',
							'value'   => wp_date( 'U' ),
							'compare' => '>=',
						),
					)
				);
			}
		}
	}

	/**
	 * Restrict access to event single page.
	 *
	 * @return void
	 */
	public static function handle_expired_events() {
		global $wp_query;

		if ( $wp_query->is_singular( self::$post_type ) ) {
			$options = get_option( 'se_options' );

			// Values for which passed events should be shown on Single View.
			$event_options = array( '', 'hide_events_on_feed' );

			if ( ! isset( $options['hide_past_events'] ) || in_array( $options['hide_past_events'], $event_options, true ) ) {
				return;
			}

			$event = $wp_query->get_queried_object();

			if ( ! empty( $event ) && se_event_is_expired( $event->ID ) ) {
				$wp_query->set_404();
				status_header( 404 );
			}
		}
	}

	/**
	 * Remove "Archive:" from archive title.
	 *
	 * @param string $title Archive title.
	 *
	 * @return string
	 */
	public static function the_archive_title( $title ) {
		if ( is_post_type_archive( self::$post_type ) ) {
			return post_type_archive_title( '', false );
		}

		return $title;
	}

	/**
	 * Remove rewrite rules and then recreate rewrite rules.
	 *
	 * @return void
	 */
	public static function flush_rewrite_rules() {
		self::register_post_type();
		flush_rewrite_rules();
	}

	/**
	 * Deletes event dates if no event info block is present.
	 *
	 * @param integer $event_id Event ID.
	 *
	 * @return void
	 */
	public static function delete_event_dates_if_no_event_info_block( $event_id ) {
		if ( wp_is_post_revision( $event_id ) || get_post_type( $event_id ) !== 'se-event' ) {
			return;
		}

		$event  = get_post( $event_id );
		$blocks = parse_blocks( $event->post_content );

		$is_event_info_block_present = false;

		foreach ( $blocks as $block ) {
			if ( 'simple-events/event-info' === $block['blockName'] ) {
				$is_event_info_block_present = true;
				break;
			}
		}

		if ( ! $is_event_info_block_present ) {
			delete_post_meta( $event_id, 'se_event_dates' );
		}
	}
}

SE_Event_Post_Type::init();
