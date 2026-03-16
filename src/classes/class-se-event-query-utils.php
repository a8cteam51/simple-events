<?php
/**
 * Event Query Utilities
 *
 * Shared utilities for event query filtering and post modification.
 * Consolidates functionality that was previously duplicated across
 * SE_Event_Post_Type, SE_Blocks, and SE_Block_Variations.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Event Query Utilities Class.
 */
class SE_Event_Query_Utils {

		/**
	 * Get all child date posts from a tax query.
	 *
	 * @param array $tax_query The taxonomy query.
	 *
	 * @return array
	 */
	public static function get_child_date_posts_from_tax_query( array $tax_query ): array {

		// If this is empty, return early.
		if ( empty( $tax_query ) ) {
			return array();
		}

		// Do a query of all se-event posts using the tax query.
		$query = new \WP_Query(
			array(
				'post_type'      => SE_Event_Post_Type::$post_type,
				'post_status'    => 'publish',
				'tax_query'      => $tax_query,
				'fields'         => 'ids',
				'posts_per_page' => -1,
			)
		);
		// If no posts found, return empty array.
		if ( ! $query->have_posts() ) {
			return array();
		}

		$events = $query->posts;

		return self::get_event_dates_from_events( $events );
	}

	/**
	 * Get all child post ids from an array of parent post ids.
	 *
	 * @param array $events The parent post IDs.
	 *
	 * @return array
	 */
	public static function get_event_dates_from_events( array $events ): array {
		$dates = array();
		foreach ( $events as $event_id ) {
			$event_dates = se_event_get_event_dates( $event_id );
			if ( ! empty( $event_dates ) ) {
				$dates = array_merge( $dates, wp_list_pluck( $event_dates, 'id' ) );
			}
		}

		// Return the unique event dates.
		return array_unique( $dates );
	}

	/**
	 * Filter event date queries to ensure parent events are published,
	 * and optionally include only the correct event date for each parent.
	 *
	 * @param string   $where The WHERE clause of the query.
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return string
	 */
	public static function filter_event_dates_where( $where, $query ) {
		// Only apply to event date queries.
		if ( SE_Event_Post_Type::$event_date_post_type !== $query->get( 'post_type' ) ) {
			return $where;
		}

		global $wpdb;

		// Always ensure the parent event post is published.
		$where .= " AND {$wpdb->posts}.post_parent IN (
			SELECT ID FROM {$wpdb->posts}
			WHERE post_type = '" . SE_Event_Post_Type::$post_type . "'
			AND post_status = 'publish'
		)";

		// If treating each date as own event or unique_parents not set, skip the unique parents subquery.
		if ( se_event_treat_each_date_as_own_event() || ! isset( $query->query_vars['unique_parents'] ) || ! isset( $query->query_vars['feed_order'] ) ) {
			return $where;
		}

		$feed_order = $query->query_vars['feed_order'];
		$meta_key   = 'desc' === strtolower( $feed_order ) ? 'se_event_date_end' : 'se_event_date_start';

		// Get the current time filtering from the main query's meta_query
		$time_filter = '';
		$meta_query  = $query->get( 'meta_query' );
		if ( ! empty( $meta_query ) && is_array( $meta_query ) ) {
			foreach ( $meta_query as $meta_condition ) {
				if ( isset( $meta_condition['key'] ) && 'se_event_date_end' === $meta_condition['key'] ) {
					$compare = $meta_condition['compare'];
					$value   = $meta_condition['value'];

					// Add the same time filtering to the subquery
					if ( '>=' === $compare ) {
						// For upcoming events
						$time_filter = "AND pm3.meta_value >= {$value}";
					} elseif ( '<' === $compare ) {
						// For past events
						$time_filter = "AND pm3.meta_value < {$value}";
					}
					break;
				}
			}
		}

		// Subquery to get the correct post ID for each parent based on sort order
		// Also ensures the parent event post is published.

		$subquery = "
			AND {$wpdb->posts}.ID IN (
				SELECT p1.ID
				FROM {$wpdb->posts} p1
				INNER JOIN {$wpdb->postmeta} pm1 ON p1.ID = pm1.post_id AND pm1.meta_key = '{$meta_key}'
				INNER JOIN {$wpdb->posts} parent ON p1.post_parent = parent.ID AND parent.post_status = 'publish'
				WHERE p1.post_type = '" . SE_Event_Post_Type::$event_date_post_type . "'
				AND p1.post_status = 'publish'
				AND pm1.meta_value = (
					SELECT " . ( 'desc' === strtolower( $feed_order ) ? 'MAX' : 'MIN' ) . "(pm2.meta_value)
					FROM {$wpdb->posts} p2
					INNER JOIN {$wpdb->postmeta} pm2 ON p2.ID = pm2.post_id AND pm2.meta_key = '{$meta_key}'
					" . ( $time_filter ? "INNER JOIN {$wpdb->postmeta} pm3 ON p2.ID = pm3.post_id AND pm3.meta_key = 'se_event_date_end'" : '' ) . "
					WHERE p2.post_parent = p1.post_parent
					AND p2.post_type = '" . SE_Event_Post_Type::$event_date_post_type . "'
					AND p2.post_status = 'publish'
					{$time_filter}
				)
				GROUP BY p1.post_parent
			)
		";

		$where .= $subquery;

		return $where;
	}

	/**
	 * Modify event posts results to convert event date posts to parent events.
	 *
	 * @param array    $posts   The array of post objects.
	 * @param WP_Query $query   The WP_Query instance.
	 * @param string   $context The context where this is being called ('archive', 'blocks', 'variations').
	 *
	 * @return array
	 */
	public static function modify_event_posts( $posts, $query, $context = 'archive' ) {

		// If not an event or event date, bail.
		if ( ! in_array( $query->get( 'post_type' ), array( SE_Event_Post_Type::$post_type, SE_Event_Post_Type::$event_date_post_type ), true ) ) {
			return $posts;
		}

		// Check if this is our events query based on context
		$should_modify = false;
		switch ( $context ) {
			case 'archive':
				// For archive: modify if unique_parents is set OR if treating each date as own event
				$should_modify = isset( $query->query_vars['unique_parents'] ) || se_event_treat_each_date_as_own_event() || get_post_type() === SE_Event_Post_Type::$post_type;
				break;
			case 'blocks':
				// For blocks: modify if unique_parents is set
				$should_modify = isset( $query->query_vars['unique_parents'] ) && $query->query_vars['unique_parents'];
				break;
			case 'variations':
				// For variations: modify if sub-type is QUERY_LOOP_EVENTS
				$should_modify = isset( $query->query_vars['sub-type'] ) && SE_Block_Variations::QUERY_LOOP_EVENTS === $query->query_vars['sub-type'];
				break;
		}

		if ( ! $should_modify ) {
			return $posts;
		}

		// Return back the modified posts with parent info and event_date_id
		return array_filter(
			array_map(
				function ( $post ) {
					$parent = get_post( $post->post_parent );

					// If we can't get the parent for some reason, return the original post to avoid breaking things, even though it likely won't display correctly.
					if ( ! $parent ) {
						return null;
					}

					// Get the start date from the event.
					$start_date_ts = get_post_meta( $post->ID, 'se_event_date_start', true );

					// Get the event timezone.
					$timezone = get_post_meta( $parent->ID, 'se_event_timezone', true );
					// use the timezone or default to the site timezone.
					$timezone = $timezone ? $timezone : wp_timezone_string();

					// Get the date in this format 2025-07-01 13:14:09
					$start_date     = wp_date( 'Y-m-d H:i:s', $start_date_ts, new \DateTimeZone( $timezone ) );
					$start_date_gmt = wp_date( 'Y-m-d H:i:s', $start_date_ts, new \DateTimeZone( 'UTC' ) );

					// update the parent posts post date
					$parent->post_date         = $start_date;
					$parent->post_date_gmt     = $start_date_gmt;
					$parent->post_modified     = $start_date;
					$parent->post_modified_gmt = $start_date_gmt;
					$parent->event_date_id     = $post->ID;

					return $parent;
				},
				$posts
			)
		);
	}

	/**
	 * Fix sort order for unique parents queries.
	 *
	 * @param string   $orderby The current orderby clause.
	 * @param WP_Query $query   The WP_Query instance.
	 *
	 * @return string
	 */
	public static function fix_sort_order( $orderby, $query ) {
		if ( isset( $query->query_vars['unique_parents'] ) && $query->query_vars['unique_parents'] ) {
			if ( str_ends_with( $orderby, '+0 ASC' ) ) {
				$feed_order = isset( $query->query_vars['feed_order'] ) ? $query->query_vars['feed_order'] : 'ASC';
				$new_order  = sprintf( ' %s', strtoupper( $feed_order ) );
				$orderby    = str_replace( '+0 ASC', $new_order, $orderby );
			}
		}

		return $orderby;
	}

	/**
	 * Add event query filters to a query.
	 *
	 * @param WP_Query $query      The query to add filters to.
	 * @param string   $feed_order The feed order (ASC/DESC).
	 * @param string   $context    The context ('archive', 'blocks', 'variations').
	 *
	 * @return void
	 */
	public static function add_event_query_filters( $query, $feed_order, $context = 'archive' ) { // phpcs:ignore
		// Add unique parents filtering if not treating each date as own event
		if ( ! se_event_treat_each_date_as_own_event() ) {
			$query->set( 'unique_parents', true );
			$query->set( 'feed_order', $feed_order );

			// Add filter for unique parents WHERE clause
			add_filter( 'posts_where', array( __CLASS__, 'filter_event_dates_where' ), 10, 2 );

			// Add filter to modify posts
			add_filter( 'the_posts', array( __CLASS__, 'modify_event_posts' ), 10, 2 );

			// Add custom order by filter
			add_filter( 'posts_orderby', array( __CLASS__, 'fix_sort_order' ), 10, 2 );
		} else {
			// When treating each date as own event, still convert event date posts to parent events
			// but don't filter for unique parents
			add_filter( 'the_posts', array( __CLASS__, 'modify_event_posts' ), 10, 2 );
		}
	}

	/**
	 * Remove event query filters.
	 *
	 * @param string $context The context ('archive', 'blocks', 'variations').
	 *
	 * @return void
	 */
	public static function remove_event_query_filters( $context = 'archive' ) { // phpcs:ignore
		// Remove filters based on context
		if ( ! se_event_treat_each_date_as_own_event() ) {
			remove_filter( 'posts_where', array( __CLASS__, 'filter_event_dates_where' ), 10 );
			remove_filter( 'posts_orderby', array( __CLASS__, 'fix_sort_order' ), 10 );
		}

		// Always remove the posts filter
		remove_filter( 'the_posts', array( __CLASS__, 'modify_event_posts' ), 10 );
	}
}
