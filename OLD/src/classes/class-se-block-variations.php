<?php
/**
 * Event Block Variations.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blocks Class.
 */
class SE_Block_Variations {

	/**
	 * Parsed variation block.
	 *
	 * @var array
	 */
	protected $parsed_block = array();

	/**
	 * Query Loop Events namespace.
	 */
	const QUERY_LOOP_EVENTS = 'query-loop-events';

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public function init() {
		if ( file_exists( SE_PLUGIN_DIR . '/build' ) ) {
			add_action( 'pre_render_block', array( $this, 'update_query' ), 10, 2 );
			add_filter( 'rest_se-event_query', array( $this, 'set_admin_query' ), 10, 2 );
		}
	}

	/**
	 * Check if a given block is a Query Loop Events block variation.
	 *
	 * @param array $parsed_block The block being rendered.
	 *
	 * @return boolean
	 */
	private function is_events_variation( $parsed_block ) {
		return isset( $parsed_block['attrs']['namespace'] ) && substr( $parsed_block['attrs']['namespace'], 0, 9 ) === 'se-events';
	}

	/**
	 * Update the query for the event query block.
	 *
	 * @param string|null $pre_render   The pre-rendered content. Default null.
	 * @param array       $parsed_block The block being rendered.
	 *
	 * @return void
	 */
	public function update_query( $pre_render, $parsed_block ) {
		if ( 'core/query' !== $parsed_block['blockName'] ) {
			return;
		}

		$this->parsed_block = $parsed_block;

		if ( $this->is_events_variation( $parsed_block ) ) {
			add_filter( 'query_loop_block_query_vars', array( $this, 'build_query' ), 10, 1 );
		}
	}

	/**
	 * Return a custom query based on attributes, filters and global WP_Query.
	 *
	 * @param WP_Query $query The WordPress Query.
	 *
	 * @return WP_Query
	 */
	public function build_query( $query ) {
		$parsed_block = $this->parsed_block;
		if ( ! $this->is_events_variation( $parsed_block ) ) {
			return $query;
		}

		$query['sub-type'] = self::QUERY_LOOP_EVENTS;

		if ( ! isset( $parsed_block['attrs']['query']['feedType'] ) ) {
			$parsed_block['attrs']['query']['feedType'] = 'default';
		}

		$feed_type  = $parsed_block['attrs']['query']['feedType'];
		$feed_order = $parsed_block['attrs']['query']['order'];

		// Inherit taxonomy query from global WP_Query if in taxonomy archive context
		if ( ! empty( $parsed_block['attrs']['query']['inheritTaxQuery'] ) ) {
			global $wp_query;
			if ( is_tax() && ! empty( $wp_query->tax_query ) ) {
				$query['tax_query'] = $wp_query->tax_query->queries;
			}
		}

		return $this->set_event_query_args( $query, $feed_type, $feed_order );
	}

	/**
	 * Set the query args for the event loop query admin.
	 *
	 * @param mixed $args    The arguments for the query.
	 * @param mixed $request The request object.
	 *
	 * @return mixed The result of the set event query args.
	 */
	public function set_admin_query( $args, $request ) {

		$feed_type  = $request->get_param( 'feedType' );
		$feed_order = $request->get_param( 'order' );

		return $this->set_event_query_args( $args, $feed_type, $feed_order );
	}

	/**
	 * Set the Event Query Loop Args.
	 *
	 * @param mixed $args       The arguments for the query.
	 * @param mixed $feed_type  The feed type.
	 * @param mixed $feed_order The feed order.
	 *
	 * @return mixed The result of the set event query args.
	 */
	private function set_event_query_args( $args, $feed_type, $feed_order = 'ASC' ) {

		if ( 'upcoming' === $feed_type ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'se_event_date_end',
					'value'   => wp_date( 'U' ),
					'compare' => '>=',
				),
			);

			$args['orderby']  = 'meta_value';
			$args['meta_key'] = 'se_event_date_start';
			$args['order']    = $feed_order;
		}

		if ( 'past' === $feed_type ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'se_event_date_end',
					'value'   => wp_date( 'U' ),
					'compare' => '<',
				),
			);

			$args['orderby']  = 'meta_value';
			$args['meta_key'] = 'se_event_date_start';
			$args['order']    = $feed_order;
		}

		/**
		 * A filter to customize the args of the event query loop.
		 *
		 * @param array    $args The built args passed in to the query.
		 * @param string|null    $feed_type        The feed type.
		 * @param string|null    $feed_order       The feed order.
		 */
		return apply_filters( 'se_pre_set_event_query_loop_args', $args, $feed_type, $feed_order );
	}
}

( new SE_Block_Variations() )->init();
