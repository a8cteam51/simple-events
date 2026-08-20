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
import { Fragment, useEffect } from '@wordpress/element';
import { useMergeState } from './use-merge-state';
import { useBlockProps } from '@wordpress/block-editor';

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
 * Get a promise that resolves to the full list of ticket products.
 *
 * @return {Promise<Array>} - A promise resolving to an array of { id, name } products.
 */
const getProducts = async () => {
	const data = await apiFetch( { path: '/simple-events/tickets/all' } );

	return data.map( ( item ) => ( {
		id: parseInt( item.id, 10 ),
		name: item.name,
	} ) );
};

const TicketSelection = ( props ) => {
	const { setAttributes, attributes } = props;

	const [ { loading, selected, products }, setState ] = useMergeState( {
		loading: true,
		selected: [],
		products: [],
	} );

	// Read selected from attributes.
	const savedSelected = attributes.selected ?? [];
	const selectedCount = savedSelected.length;

	// These three ran during render under withState. useState treats a
	// render-phase update as a re-render trigger, and the newTicketAdded one
	// also writes to the block's attributes, so they belong in effects.
	useEffect( () => {
		if ( selectedCount && ! selected.length ) {
			setState( { selected: savedSelected } );
		}
	}, [ selectedCount, selected.length ] );

	// Reload products if a new ticket has been added.
	useEffect( () => {
		if ( attributes.newTicketAdded ) {
			setState( { loading: true } );
			setAttributes( { newTicketAdded: false } );
		}
	}, [ attributes.newTicketAdded ] );

	// Load products.
	useEffect( () => {
		if ( ! loading ) {
			return undefined;
		}

		let cancelled = false;

		getProducts()
			.then( ( data ) => {
				if ( ! cancelled ) {
					setState( { products: data, loading: false } );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setState( { products: [], loading: false } );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ loading ] );

	const onChange = ( ids ) => {
		setState( { selected: ids } );
		setAttributes( { selected: ids } );
	};

	const getSelectedProducts = (items = products) => {
		return selected
			.map((id) => items.find((item) => item.id === id))
			.filter(Boolean); // This removes undefined items
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
							getSelectedProducts().map( ( item ) => (
								<TicketDataControl
									{ ...props }
									key={ item.id }
									editingProduct={ item.id }
									index={ item.id }
									onRemove={ () => {
										// Remove by id, not render index: the
										// rendered list is filtered and can be
										// shorter than selected.
										onChange(
											selected.filter(
												( selectedId ) =>
													selectedId !== item.id
											)
										);
									} }
									onReorder={ ( reorderedSelected ) =>
										// Through onChange so local selected
										// state stays in sync with attributes.
										onChange( reorderedSelected )
									}
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
};

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
