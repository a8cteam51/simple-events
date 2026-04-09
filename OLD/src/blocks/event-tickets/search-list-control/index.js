/**
 * Internal dependencies
 */
import SearchListItem from '@woocommerce/components/build-module/search-list-control/item';

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	MenuGroup,
	Spinner,
	TextControl,
	Icon,
	withSpokenMessages,
} from '@wordpress/components';
import { Component } from '@wordpress/element';
import { compose, withState } from '@wordpress/compose';
import { escapeRegExp } from 'lodash';

const Messages = {
	clear: __( 'Clear all selected tickets', 'simple-events' ),
	list: __( 'Ticket Products', 'simple-events' ),
	resultsList: __( 'Results', 'simple-events' ),
	noItems: __(
		"Your store doesn't have any ticket products.",
		'simple-events'
	),
	noResults: __( 'No results for %s', 'simple-events' ),
	search: __( 'Search for a ticket product:', 'simple-events' ),
	updated: __( 'Ticket products search results updated.', 'simple-events' ),
};

/**
 * Component to display a searchable, selectable list of items.
 */
export class SearchListControl extends Component {
	constructor() {
		super( ...arguments );

		this.onSelect = this.onSelect.bind( this );
		this.renderList = this.renderList.bind( this );
	}

	componentDidUpdate( prevProps ) {
		const { onSearch, search } = this.props;

		if ( search !== prevProps.search && typeof onSearch === 'function' ) {
			onSearch( search );
		}
	}

	onSelect( item ) {
		const { onChange, selected } = this.props;

		return () => {
			onChange( [ ...selected, item ] );
		};
	}

	getFilteredList( list, search ) {
		if ( ! search ) {
			return list;
		}

		const re = new RegExp( escapeRegExp( search ), 'i' );

		this.props.debouncedSpeak( Messages.updated );

		const filteredList = list
			.map( ( item ) => ( re.test( item.name ) ? item : false ) )
			.filter( Boolean );

		return filteredList;
	}

	renderList( list, depth = 0 ) {
		const { search } = this.props;

		if ( ! list ) {
			return null;
		}

		return list.map( ( item ) => (
			<SearchListItem
				depth={ depth }
				isSingle={ false }
				item={ item }
				onSelect={ this.onSelect }
				search={ search }
			/>
		) );
	}

	renderListSection() {
		const { isLoading, search } = this.props;

		if ( isLoading ) {
			return (
				<div className="woocommerce-search-list__list is-loading">
					<Spinner />
				</div>
			);
		}

		const list = this.getFilteredList( this.props.list, search );

		if ( ! list.length ) {
			return (
				<div className="woocommerce-search-list__list is-not-found">
					<Icon
						className="woocommerce-search-list__not-found-icon"
						role="img"
						aria-hidden="true"
						focusable="false"
						icon="warning"
					/>
					<span className="woocommerce-search-list__not-found-text">
						{ search
							? // eslint-disable-next-line @wordpress/valid-sprintf
							  sprintf( Messages.noResults, search )
							: Messages.noItems }
					</span>
				</div>
			);
		}

		return (
			<MenuGroup
				label={ search ? Messages.resultsList : Messages.list }
				className="woocommerce-search-list__list"
			>
				{ this.renderList( list ) }
			</MenuGroup>
		);
	}

	render() {
		const { className = '', search, setState } = this.props;

		return (
			<div className={ `woocommerce-search-list ${ className }` }>
				<div className="woocommerce-search-list__search">
					<TextControl
						label={ Messages.search }
						type="search"
						value={ search }
						onChange={ ( value ) => setState( { search: value } ) }
					/>
				</div>

				{ this.renderListSection() }
			</div>
		);
	}
}

export default compose( [
	withState( {
		search: '',
	} ),
	withSpokenMessages,
] )( SearchListControl );
