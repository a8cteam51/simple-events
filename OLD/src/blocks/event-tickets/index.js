/**
 * BLOCK: Event Tickets
 *
 * Fetches tickets from WooCommerce.
 */
import './style.scss';
import './editor.scss';

import SearchListControl from './search-list-control';
import TicketDataControl from './ticket-data-control';

import { __, sprintf } from '@wordpress/i18n';
import apiFetch from "@wordpress/api-fetch";
import { registerBlockType } from '@wordpress/blocks';
import { Placeholder, Button, Spinner } from '@wordpress/components';
import { Fragment } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';
import { withState } from '@wordpress/compose';
import { useBlockProps } from '@wordpress/block-editor';
import { flatten, uniqBy, debounce } from 'lodash';

const productCount = window.seSettings.productCount || 50;
const isLargeCatalog = window.seSettings.isLargeCatalog || false;
const isWCActive = window.seSettings.isWCActive || false;
const isBOActive = window.seSettings.isBOActive || false;

const renderMissingDependencies = () => {
	const dependencies = [];

	if ( ! isWCActive ) {
		dependencies.push( 'WooCommerce' );
	}

	if ( ! isBOActive ) {
		dependencies.push( 'WooCommerce Box Office' );
	}

	return dependencies.length ? (
		<p>
			{ sprintf(
				__(
					'%s must be installed and active to use this block.',
					'simple-events'
				),
				dependencies.join( __( ' and ', 'simple-events' ) )
			) }
		</p>
	) : null;
};

/**
 * Get a promise that resolves to a list of products from the API.
 *
 * @param {string} - A query string with the search term.
 * @return {Array} - An array of products.
 */
const getProducts = async ( { selected = [], search = '' } ) => {
	const postTypes = [ 'product', 'product_variation' ];
	const pageSize = 25;
	let offset = 0;
	let requests = [];
	let queryArgs = null;

	while ( offset < productCount ) {
		queryArgs = {
			post_type: postTypes,
			offset: offset,
			per_page: pageSize,
			status: 'publish',
			search,
		};

		requests.push( addQueryArgs( 'simple-events/tickets', queryArgs ) );
		offset += pageSize;
	}

	if ( selected.length ) {
		requests.push(
			addQueryArgs( 'simple-events/tickets', {
				status: 'publish',
				include: selected,
			} )
		);
	}

	const data = await Promise.all(
		requests.map( ( path ) => apiFetch( { path } ) )
	);

	return uniqBy( flatten( data ), 'id' ).map( ( item ) => {
		return {
			id: parseInt( item.id, 10 ),
			name: item.name,
		};
	} );
};

const TicketSelection = withState( {
	loading: true,
	selected: [],
	products: [],
	search: '',
} )( ( props ) => {
	const {
		setState,
		setAttributes,
		attributes,
		loading,
		selected,
		products,
		search,
	} = props;



	// Read selected from attributes.
	const savedSelected = attributes.selected ?? [];
	const selectedCount = savedSelected.length;

	if ( selectedCount && ! selected.length ) {
		setState( { selected: savedSelected } );
	}

	// Reload products if a new ticket has been added.
	if ( attributes.newTicketAdded ) {
		setState( { loading: true } );
		setAttributes( { newTicketAdded: false } );
	}

	// Load products.
	if ( loading ) {
		getProducts( { savedSelected, search } )
			.then( ( data ) => {
				setState( { products: data, loading: false } );
			} )
			.catch( () => {
				setState( { products: [], loading: false } );
			} );
	}

	const debounceSearch = debounce( ( searchValue ) => {
		setState( { loading: true, search: searchValue } );
	}, 400 );

	const onChange = ( ids ) => {
		setState( { selected: ids } );
		setAttributes( { selected: ids } );
	};

	const getSelectedProducts = ( items = products ) => {
		return selected.map( ( id ) =>
			items.find( ( item ) => item.id === id )
		);
	};

	const searchList = (
		<Fragment>
			<SearchListControl
				className="simple-events-tickets"
				isLoading={ loading }
				list={
					selected
						? products.filter(
								( { id } ) => ! selected.includes( id ) && ! isNaN( id )
						  )
						: products
				}
				selected={
					selected
						? products.filter( ( { id } ) =>
								selected.includes( id )
						  )
						: []
				}
				onChange={ ( items ) => {
					let updatedSelected = selected;

					if ( items.length > selected.length ) {
						updatedSelected.push( items.pop().id );
					} else {
						updatedSelected = getSelectedProducts( items )
							.filter( Boolean )
							.map( ( { id } ) => id );
					}

					onChange( updatedSelected );
				} }
				onSearch={ isLargeCatalog ? debounceSearch : null }
			/>
			<Button
				isPrimary
				onClick={ () => setAttributes( { searchMode: false } ) }
			>
				{ __( 'Done adding existing tickets', 'simple-events' ) }
			</Button>
		</Fragment>
	);

	const selectedList = (
		<div className="se-selected-tickets">
			<div className="se-selected-tickets_header">
				{ ! loading && 1 < selectedCount ? (
					<Button
						isLink
						isDestructive
						onClick={ () => onChange( [] ) }
						aria-label={ __(
							'Clear all selected tickets',
							'simple-events'
						) }
					>
						{ __( 'Clear all', 'simple-events' ) }
					</Button>
				) : null }
			</div>

			<div className="se-selected-tickets_list">
				{ selectedCount && (
					<Fragment>
						{ loading || attributes.newTicketAdded ? (
							<Spinner />
						) : (
							getSelectedProducts().map( ( item, i ) => (
								<TicketDataControl
									{ ...props }
									editingProduct={ item.id }
									index={ item.id }
									onRemove={ () => {
										const updatedSelected = selected;

										updatedSelected.splice( i, 1 );

										onChange( updatedSelected );
									} }
									onReorder={ ( reorderedSelected ) => {
										setAttributes( {
											selected: reorderedSelected,
										} );
									} }
									title={ item.name }
								/>
							) )
						) }
					</Fragment>
				) }
			</div>
		</div>
	);

	return (
		<Fragment>
			{ ! selectedCount ? (
				<p>
					{ __(
						'No tickets have been added to this event.',
						'simple-events'
					) }
				</p>
			) : (
				selectedList
			) }
			{ attributes.searchMode && searchList }
		</Fragment>
	);
} );

/**
 * Register: a Gutenberg Block.
 *
 * Registers a new block provided a unique name and an object defining its
 * behavior. Once registered, the block is made editor as an option to any
 * editor interface where blocks are implemented.
 *
 * @link https://wordpress.org/gutenberg/handbook/block-api/
 * @param {string} name     Block name.
 * @param {Object} settings Block settings.
 * @return {?WPBlock}          The block, if it has been successfully
 *                             registered; otherwise `undefined`.
 */
registerBlockType( 'simple-events/event-tickets', {
	/**
	 * The edit function describes the structure of your block in the context of the editor.
	 * This represents what the editor will render when the block is used.
	 *
	 * The "edit" property must be a valid function.
	 *
	 * @link https://wordpress.org/gutenberg/handbook/block-api/block-edit-save/
	 *
	 * @param {Object} props Props.
	 * @return {Mixed} JSX Component.
	 */
	edit: ( props ) => {
		const { attributes, setAttributes } = props;
		const { addMode, editMode, searchMode, selected } = attributes;

		return (
			<div { ...useBlockProps() }>
				<Placeholder
					icon="tickets-alt"
					label={ __( 'Event Tickets', 'simple-events' ) }
				>
					{ isWCActive && isBOActive ? (
						<Fragment>
							<TicketSelection { ...props } />
							{ addMode && (
								<TicketDataControl
									{ ...props }
									onRemove={ () =>
										setAttributes( { addMode: false } )
									}
									onSave={ ( updatedSelected ) =>
										setAttributes( {
											selected: updatedSelected,
											newTicketAdded: true,
											addMode: false,
										} )
									}
								/>
							) }
							{ ! addMode && ! editMode && ! searchMode && (
								<div className="se-mode-button-container">
									<Button
										isSecondary
										onClick={ () =>
											setAttributes( { addMode: true } )
										}
									>
										{ __(
											'Create new ticket',
											'simple-events'
										) }
									</Button>
									<Button
										isSecondary
										onClick={ () =>
											setAttributes( {
												searchMode: true,
											} )
										}
									>
										{ __(
											'Add existing tickets',
											'simple-events'
										) }
									</Button>
								</div>
							) }
						</Fragment>
					) : (
						renderMissingDependencies()
					) }
				</Placeholder>
			</div>
		);
	},

	/**
	 * The save function defines the way in which the different attributes should be combined
	 * into the final markup, which is then serialized by Gutenberg into post_content.
	 *
	 * The "save" property must be specified and must be a valid function.
	 *
	 * @link https://wordpress.org/gutenberg/handbook/block-api/block-edit-save/
	 *
	 * @param {Object} props Props.
	 * @return {Mixed} JSX Frontend HTML.
	 */
	save: () => {
		return null;
	},
} );
