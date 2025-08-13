/**
 * BLOCK: Query Loop Events
 *
 * Displays query loop events with upcoming, past settings.
 */

import { InspectorControls } from '@wordpress/block-editor';
import { registerBlockType, registerBlockVariation } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import { PanelBody, SelectControl, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
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
			_cacheBuster: Date.now(),
		},
		eventsPerPage: 6,
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
	const [eventsPerPage, setEventsPerPage] = useState(
		query.perPage || 6
	);

	// Store the query data so child blocks can access it
	useEffect(() => {
		dispatch('se-events/query-data').setQueryData(clientId, {
			feedType,
			order: feedOrder,
		});
	}, [feedType, feedOrder, clientId]);


/**
 * Gets options for feed order based on the type.
 * @param {string} type The current feed type.
 * @returns options for feed order based on the type.
 */
const getFeedOrderOptions = (type) => {
	switch (type) {
		case 'upcoming':
			return [
				{ label: 'Soonest First', value: 'asc' },
				{ label: 'Furthest in Future First', value: 'desc' },
			];
		case 'past':
			return [
				{ label: 'Oldest First', value: 'asc' },
				{ label: 'Most Recent First', value: 'desc' },
			];
		case 'default':
		default:
			return [
				{ label: 'Oldest to Newest', value: 'asc' },
				{ label: 'Newest to Oldest', value: 'desc' },
			];
	}
};

let feedOrderOptions = getFeedOrderOptions(feedType);

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
							_cacheBuster: Date.now()
						},
					});
				}}
				__nextHasNoMarginBottom
			/>
			<SelectControl
				label="Feed Order"
				value={feedOrder}
				options={feedOrderOptions}
				onChange={(value) => {
					setAttributes({
						query: {
							...query,
							order: value,
							_cacheBuster: Date.now()
						},
					});
				}}
				__nextHasNoMarginBottom
			/>
			<RangeControl
				label={__('Events per Page', 'simple-events')}
				value={eventsPerPage}
				onChange={(value) => {
					setEventsPerPage(value);
					setAttributes({
						query: {
							...query,
							perPage: value,
							_cacheBuster: Date.now()
						},
					});
				}}
				min={1}
				max={100}
				step={1}
				__nextHasNoMarginBottom
			/>
			<p className="description">
				{__(
					'Select the type of events to display and their order.',
					'simple-events'
				)}
			</p>
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
