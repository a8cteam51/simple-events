/**
 * BLOCK: Query Loop Events
 *
 * Displays query loop events with upcoming, past settings.
 */

import { InspectorControls } from '@wordpress/block-editor';
import { registerBlockType, registerBlockVariation } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import { createReduxStore, register, dispatch, select } from '@wordpress/data';

const EVENTS_VARIATION = 'se-events/query-loop-events';

// Create a simple store for query loop data
const eventsQueryStore = createReduxStore('se-events/query-data', {
	reducer: (state = {}, action) => {
		switch (action.type) {
			case 'SET_QUERY_DATA':
				return {
					...state,
					[action.blockId]: action.data,
				};
			default:
				return state;
		}
	},
	actions: {
		setQueryData: (blockId, data) => ({
			type: 'SET_QUERY_DATA',
			blockId,
			data,
		}),
	},
	selectors: {
		getQueryData: (state, blockId) => state[blockId] || {},
	},
});

register(eventsQueryStore);

registerBlockVariation('core/query', {
	name: EVENTS_VARIATION,
	title: 'Query Loop Events',
	description: 'Displays query loop events with upcoming, past settings',
	category: 'simple-events',
	isActive: ({ namespace, query }) => {
		return namespace === EVENTS_VARIATION && query.postType === 'se-event';
	},
	icon: 'layout',
	attributes: {
		namespace: EVENTS_VARIATION,
		query: {
			perPage: 6,
			pages: 0,
			offset: 0,
			postType: 'se-event',
			order: 'asc',
			orderBy: 'date',
			author: '',
			search: '',
			exclude: [],
			sticky: '',
			inherit: false,
			inheritTaxQuery: true,
			feedType: 'default',
		},
	},
	innerBlocks: [
		[
			'core/post-template',
			{},
			[['core/post-title'], ['simple-events/loop-event-info']],
		],
		['core/query-pagination'],
		['core/query-no-results'],
	],

	scope: ['inserter'],
	allowedControls: ['taxQuery', 'search', 'feedType'],
});

const FeedTypeControl = ({ attributes, setAttributes, clientId }) => {
	const { query } = attributes;
	const feedType = query.feedType || 'default';
	const feedOrder = query.order || 'asc';

	// Store the query data so child blocks can access it
	useEffect(() => {
		dispatch('se-events/query-data').setQueryData(clientId, {
			feedType,
			order: feedOrder,
		});
	}, [feedType, feedOrder, clientId]);

	return (
		<>
			<SelectControl
				label="Feed Type"
				value={feedType}
				options={[
					{ label: 'Default', value: 'default' },
					{ label: 'Upcoming', value: 'upcoming' },
					{ label: 'Past', value: 'past' },
				]}
				onChange={(value) => {
					setAttributes({
						query: {
							...query,
							feedType: value,
						},
					});
				}}
				__nextHasNoMarginBottom
			/>
			<SelectControl
				label="Feed Order"
				value={feedOrder}
				options={[
					{ label: 'Oldest First', value: 'asc' },
					{ label: 'Newest First', value: 'desc' },
				]}
				onChange={(value) => {
					setAttributes({
						query: {
							...query,
							order: value,
						},
					});
				}}
				__nextHasNoMarginBottom
			/>
		</>
	);
};

export const withEventsQueryControls = (BlockEdit) => (props) => {
	return isMyEventsVariation(props) ? (
		<>
			<BlockEdit {...props} />
			<InspectorControls>
				<PanelBody
					title={__('Events Query Loop', 'simple-events')}
					className="query-loop-events-panel"
				>
					<FeedTypeControl {...props} />
				</PanelBody>
			</InspectorControls>
		</>
	) : (
		<BlockEdit {...props} />
	);

	function isMyEventsVariation(props) {
		return props.attributes.namespace === EVENTS_VARIATION;
	}
};

addFilter(
	'editor.BlockEdit',
	'se-events/my-awesome-pattern',
	withEventsQueryControls
);
