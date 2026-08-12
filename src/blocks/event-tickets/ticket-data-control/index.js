/**
 * Internal dependencies
 */
import DraggableItem from '../draggable';

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from "@wordpress/api-fetch";
import {
	Button,
	Disabled,
	TextControl,
	SelectControl,
	CheckboxControl,
	Spinner,
	Dashicon,
	PanelBody,
	TimePicker,
	Dropdown,
	Tooltip,
	TextareaControl,
	Path,
	SVG,
} from '@wordpress/components';
import { Fragment, useState } from '@wordpress/element';
import { useMergeState } from '../use-merge-state';
import { decodeEntities } from '@wordpress/html-entities';
import { getSettings, date } from '@wordpress/date';
import { concat, isEqual } from 'lodash';
const md5 = require( 'md5' );

const BOTicketFieldTypes = window.seSettings.BOTicketFieldTypes || [];

const fieldTypeOptions = () => {
	const options = Object.entries( BOTicketFieldTypes ).map(
		( [ key, value ] ) => {
			return { value: key, label: value };
		}
	);

	options.unshift( { value: '', label: __( 'Type', 'simple-events' ) } );

	return options;
};

/**
 * One row of the additional-fields editor.
 *
 * Defined at module scope on purpose. While it lived inside TicketDataControl it
 * was a new component type on every parent render, so React unmounted and
 * rebuilt the row each time the parent updated — which meant the text input lost
 * focus after a keystroke.
 *
 * @param {Object}   props
 * @param {Object}   props.fieldset         The field being edited.
 * @param {number}   props.index            Its position in the list.
 * @param {Array}    props.additionalFields The whole list, for reordering.
 * @param {Function} props.onChange         Called with a reordered list.
 * @param {Function} props.onUpdate         Called with ( index, changes ).
 * @param {Function} props.onRemove         Called with ( index ).
 */
const TicketFieldSet = ( {
	fieldset,
	index,
	additionalFields,
	onChange,
	onUpdate,
	onRemove,
} ) => {
	const defaultRequired = [ 'first_name', 'last_name', 'email' ].includes(
		fieldset.type
	);

	return (
		<DraggableItem
			className="se-ticket-data_additional-field"
			index={ index }
			onChange={ ( order ) =>
				onChange( order.map( ( i ) => additionalFields[ i ] ) )
			}
		>
			<TextControl
				autoComplete="off"
				disabled={ defaultRequired }
				label={ __( 'Field label', 'simple-events' ) }
				hideLabelFromVision
				placeholder={ __( 'Field label', 'simple-events' ) }
				value={ fieldset.label }
				onChange={ ( value ) => onUpdate( index, { label: value } ) }
			/>
			<SelectControl
				disabled={ defaultRequired }
				label={ __( 'Field type', 'simple-events' ) }
				hideLabelFromVision
				value={ fieldset.type }
				options={ fieldTypeOptions() }
				onChange={ ( type ) => onUpdate( index, { type } ) }
			/>
			<CheckboxControl
				className={ defaultRequired ? 'disabled' : '' }
				checked={ fieldset.required }
				disabled={ defaultRequired }
				label={ __( 'Required', 'simple-events' ) }
				onChange={ ( required ) => onUpdate( index, { required } ) }
			/>
			{ ! defaultRequired && (
				<Button
					className="se-ticket-data_additional-field-remove"
					label={ sprintf( __( 'Remove field', 'simple-events' ) ) }
					onClick={ () => onRemove( index ) }
				>
					{ removeIcon }
				</Button>
			) }
			{ [ 'select', 'radio', 'checkbox' ].includes( fieldset.type ) && (
				<TextareaControl
					className="se-ticket-data_additional-field-options"
					label={ __( 'Options', 'simple-events' ) }
					hideLabelFromVision
					help={ __(
						'Comma-separated list of available options',
						'simple-events'
					) }
					value={ fieldset.options }
					onChange={ ( value ) => onUpdate( index, { options: value } ) }
				/>
			) }
		</DraggableItem>
	);
};

const removeIcon = (
	<SVG
		width="20"
		height="20"
		viewBox="0 0 20 20"
		focusable="false"
		role="img"
		xmlns="http://www.w3.org/2000/svg"
	>
		<Path d="M10 2c4.42 0 8 3.58 8 8s-3.58 8-8 8-8-3.58-8-8 3.58-8 8-8zm5.657 6.586H4.343v2.828h11.314V8.586z" />
	</SVG>
);

/**
 * The block details interface.
 *
 * @param {*} param0
 * @return {Object}
 */
const TicketDataControl = ( {
	dataLoaded,
	editingProduct,
	index,
	loading,
	name,
	price,
	saleData,
	saleDateFrom,
	saleDateTo,
	salePrice,
	stock,
	additionalFields,
	setState,
	attributes,
	setAttributes,
	title,
	onRemove,
	onReorder,
	onSave,
} ) => {
	// Load data if this is an existing product being edited.
	if ( editingProduct && ! dataLoaded ) {
		setState( {
			loading: true,
			dataLoaded: true,
		} );

		apiFetch( { path: `/wc/v2/products/${ editingProduct }` } ).then(
			( response ) => {
				const ticketFields = Object.values(
					response.meta_data.find(
						( item ) => item.key === '_ticket_fields'
					).value
				);

				setState( {
					name: response.name,
					price: response.regular_price,
					saleData: response.date_on_sale_from ? true : false,
					saleDateFrom: response.date_on_sale_from,
					saleDateTo: response.date_on_sale_to,
					salePrice: response.sale_price,
					stock: response.stock_quantity,
					additionalFields: ticketFields,
					loading: false,
				} );
			}
		);
	}

	/**
	 * Saves a new product post or updates an existing one.
	 */
	const saveProduct = async () => {
		let path = '/wc/v2/products';

		const productData = {
			name,
			regular_price: price,
			sale_price: salePrice,
			stock_quantity: stock,
			meta_data: [],
		};

		if ( salePrice && saleData && saleDateFrom && saleDateTo ) {
			productData.date_on_sale_from = `${ date(
				'Y-m-d',
				saleDateFrom
			) }T00:00:00`;
			productData.date_on_sale_to = `${ date(
				'Y-m-d',
				saleDateTo
			) }T23:59:59`;
		}

		const ticketFields = {};

		additionalFields.forEach(
			( { label, type, required, options = '' } ) => {
				if ( '' !== type ) {
					const key = md5( label + type );
					ticketFields[ key ] = {
						label,
						type,
						required,
						options,
						autofill: 'none',
						email_contact: 'yes',
						email_gravatar: 'yes',
					};
				}
			}
		);

		if ( Object.keys( ticketFields ).length !== 0 ) {
			productData.meta_data.push( {
				key: '_ticket_fields',
				value: ticketFields,
			} );
		}

		if ( ! editingProduct ) {
			productData.virtual = true;
			productData.meta_data.push( { key: '_ticket', value: 'yes' } );

			if ( stock ) {
				productData.manage_stock = true;
			}
		}

		if ( editingProduct ) {
			path += `/${ editingProduct }`;
		}

		return await apiFetch( {
			path,
			method: editingProduct ? 'PUT' : 'POST',
			data: productData,
		} );
	};

	/**
	 * Update the given date with the new selection from TimePicker.
	 *
	 * @param {string} whichDate Which date to update.
	 * @param {string} oldDate   The previous date value.
	 * @param {string} newDate   The new date value.
	 */
	const maybeUpdateDateTime = ( whichDate, oldDate, newDate ) => {
		if ( isEqual( oldDate, newDate ) ) {
			return;
		}

		if ( whichDate === 'From' ) {
			setState( { saleDateFrom: newDate } );
		}

		if ( whichDate === 'To' ) {
			setState( { saleDateTo: newDate } );
		}
	};

	/**
	 * The sale scheduling interface.
	 */
	const DateField = ( { label, value } ) => {
		const [ tempDate, setTempDate ] = useState( '' );
		const settings = getSettings();
		const displayDate = tempDate ? tempDate : value;

		// To know if the current timezone is a 12 hour time with look for an "a" in the time format.
		// We also make sure this a is not escaped by a "/".
		const is12HourTime = /a(?!\\)/i.test(
			settings.formats.time
				.toLowerCase() // Test only the lower case a
				.replace( /\\\\/g, '' ) // Replace "//" with empty strings
				.split( '' )
				.reverse()
				.join( '' ) // Reverse the string and test for "a" not followed by a slash
		);

		return (
			<Dropdown
				contentClassName="se-datetime-popover se-datetime-popover__date"
				onToggle={ () => {
					if ( tempDate ) {
						// Send to the parent scope to update state there.
						maybeUpdateDateTime( label, value, tempDate );
						setTempDate( '' );
					}
				} }
				position="bottom center"
				renderContent={ () => (
					<TimePicker
						currentTime={ displayDate }
						is12Hour={ is12HourTime }
						onChange={ ( newDate ) => setTempDate( newDate ) }
					/>
				) }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						className={
							! value ? 'se-ticket-data_datetime-placeholder' : ''
						}
						onClick={ onToggle }
						aria-expanded={ isOpen }
					>
						{ ! value
							? `${ label }…`
							: date( 'Y-m-d', displayDate ) }
					</Button>
				) }
			/>
		);
	};

	/**
	 * Remove the field at the given index.
	 * (Done in this scope to ensure that the component updates.)
	 *
	 * @param {number} i The index of the field to remove.
	 */
	const removeFieldset = ( i ) => {
		setState( {
			additionalFields: additionalFields.filter(
				( field, index ) => index !== i
			),
		} );
	};

	/**
	 * Apply changes to one fieldset.
	 *
	 * Builds a new field object and a new array rather than editing in place, so
	 * the update goes through state instead of relying on the mutation being
	 * visible to the parent.
	 *
	 * @param {number} i       The index of the field to change.
	 * @param {Object} changes The keys to overwrite on that field.
	 */
	const updateFieldset = ( i, changes ) => {
		setState( {
			additionalFields: additionalFields.map( ( field, index ) =>
				index === i ? { ...field, ...changes } : field
			),
		} );
	};

	/**
	 * Adds a new additional field to the product.
	 */
	const addFieldset = () => {
		const existingFields = additionalFields;
		const updatedFields = concat( ...existingFields, {
			label: '',
			type: '',
			required: false,
		} );

		setState( { additionalFields: updatedFields } );
	};

	/**
	 * The product information form.
	 * @param  name
	 * @param  price
	 * @param  stock
	 * @param  salePrice
	 * @param  e
	 */
	const ticketDataForm = (
		<Fragment>
			<div className="se-ticket-data_inner">
				<TextControl
					autoComplete="off"
					className="se-ticket-data_name"
					label={ __( 'Name', 'simple-events' ) }
					value={ decodeEntities( name ) }
					onChange={ ( name ) => setState( { name } ) }
				/>

				<TextControl
					autoComplete="off"
					className="se-ticket-data_price"
					label={ __( 'Price', 'simple-events' ) }
					type="number"
					min="0"
					value={ price }
					onChange={ ( price ) => setState( { price } ) }
				/>

				<TextControl
					autoComplete="off"
					className="se-ticket-data_stock"
					label={ __( 'Stock', 'simple-events' ) }
					type="number"
					min="0"
					value={ stock }
					onChange={ ( stock ) => setState( { stock } ) }
				/>

				<div className="se-ticket-data_stock-help">
					<Tooltip
						text={ __(
							'If less than 1, ticket will appear as "Sold Out".',
							'simple-events'
						) }
					>
						<div className="se-help">
							<Dashicon icon="editor-help" size={ 20 } />
						</div>
					</Tooltip>

					{ ! saleData && (
						<Button
							isLink
							onClick={ () => setState( { saleData: true } ) }
						>
							{ __( '+ Add sale price', 'simple-events' ) }
						</Button>
					) }
				</div>

				{ saleData && (
					<Fragment>
						<TextControl
							autoComplete="off"
							className="se-ticket-data_sale-price"
							label={ __( 'Sale price', 'simple-events' ) }
							type="number"
							min="0"
							value={ salePrice }
							onChange={ ( salePrice ) =>
								setState( { salePrice } )
							}
						/>

						<div className="se-ticket-data_sale-schedule">
							<p>{ __( 'Sale dates' ) }</p>

							<DateField
								label={ __( 'From', 'simple-events' ) }
								value={ saleDateFrom }
							/>

							<DateField
								label={ __( 'To', 'simple-events' ) }
								value={ saleDateTo }
							/>
						</div>

						<div className="se-ticket-data_sale-help">
							<Tooltip
								text={ __(
									'The sale will start at 00:00:00 of "From" date and end at 23:59:59 of "To" date.',
									'simple-events'
								) }
							>
								<div className="se-help">
									<Dashicon icon="editor-help" size={ 20 } />
								</div>
							</Tooltip>

							<Button
								isLink
								onClick={ () =>
									setState( {
										saleData: false,
										saleDateFrom: '',
										saleDateTo: '',
									} )
								}
							>
								{ __( 'Cancel', 'simple-events' ) }
							</Button>
						</div>
					</Fragment>
				) }

				<div className="se-ticket-data_additional-fields">
					<p>
						<strong>
							{ __( 'Ticket Fields', 'simple-events' ) }
						</strong>
					</p>
					<div className="se-ticket-data_fields">
						<div className="se-ticket-data_field-labels">
							<p>{ __( 'Label', 'simple-events' ) }</p>
							<p>{ __( 'Type', 'simple-events' ) }</p>
						</div>
						{ additionalFields.map( ( field, index ) => (
							<TicketFieldSet
								fieldset={ field }
								index={ index }
								onChange={ ( reordered ) =>
									setState( { additionalFields: reordered } )
								}
								additionalFields={ additionalFields }
								onUpdate={ updateFieldset }
								onRemove={ removeFieldset }
							/>
						) ) }
					</div>
					<Button isLink onClick={ () => addFieldset() }>
						{ __( '+ Add Field', 'simple-events' ) }
					</Button>
				</div>
			</div>
			<Button
				isPrimary
				onClick={ () => {
					setState( { loading: true } );

					saveProduct().then( ( data ) => {
						if ( ! editingProduct ) {
							const updatedSelected = attributes.selected || [];

							updatedSelected.push( data.id );

							onSave( updatedSelected );
						}

						setState( { loading: false } );

						if ( attributes.editMode ) {
							const ticketContainer = document.querySelector(
								`.se-selected-ticket[data-index="${ attributes.editMode }"]`
							);

							ticketContainer
								.querySelector( 'h2 button' )
								.click();

							setAttributes( { editMode: null } );
						}
					} );
				} }
			>
				{ __(
					editingProduct ? 'Update Ticket' : 'Create ticket',
					'simple-events'
				) }
			</Button>
			<Button
				isLink
				onClick={ ( e ) => {
					if ( attributes.editMode ) {
						const ticketContainer = e.target.closest(
							'.se-ticket-data-container'
						);

						ticketContainer.querySelector( 'h2 button' ).click();
					} else {
						setAttributes( { addMode: false } );
					}
				} }
			>
				{ __( 'Cancel', 'simple-events' ) }
			</Button>
		</Fragment>
	);

	const ticketDataFormContainer = (
		<div className="se-ticket-data">
			{ loading ? (
				<Fragment>
					<Disabled>{ ticketDataForm }</Disabled>
					<Spinner />
				</Fragment>
			) : (
				ticketDataForm
			) }
		</div>
	);

	const labelTextNode = (
		<Fragment>
			{ editingProduct ? (
				<Fragment>
					<span className="screen-reader-text">
						{ __( 'Edit ', 'simple-events' ) }
					</span>
					<span aria-hidden="true">{ decodeEntities( title ) }</span>
				</Fragment>
			) : (
				<span className="screen-reader-text">
					{ __( 'Create new ticket', 'simple-events' ) }
				</span>
			) }
			<SVG
				className="edit"
				width="20"
				height="20"
				viewBox="0 0 20 20"
				focusable="false"
				role="img"
				xmlns="http://www.w3.org/2000/svg"
			>
				<Path d="M3.012 15.92l1.212-4.36L14.834.92l3.178 3.134-10.56 10.634z" />
				<Path d="M1.988 18.276v1.453h8v-1.453z" />
			</SVG>
			<Button
				className="woocommerce-tag__remove"
				onClick={ ( e ) => {
					if (
						attributes.editMode &&
						index === attributes.editMode
					) {
						setAttributes( { editMode: null } );
					} else {
						e.stopPropagation();
					}

					onRemove();
				} }
				label={ sprintf( __( 'Remove %s', 'simple-events' ), name ) }
			>
				{ removeIcon }
			</Button>
		</Fragment>
	);

	const item = (
		<Fragment>
			{ editingProduct ? (
				<DraggableItem
					className={
						attributes.editMode && index === attributes.editMode
							? 'se-selected-ticket se-is-open'
							: 'se-selected-ticket'
					}
					index={ index }
					onChange={ onReorder }
				>
					<PanelBody
						className="se-ticket-data-container"
						initialOpen={ false }
						onToggle={ () => {
							if ( attributes.editMode ) {
								setAttributes( { editMode: null } );
							} else {
								// Ensure that the most current data is loaded in.
								setState( { dataLoaded: false } );

								setAttributes( { editMode: editingProduct } );

								// Set focus on the name field.
								const ticketContainer =
									document.activeElement.closest(
										'.se-ticket-data-container'
									);

								const focusNameInput = () => {
									const nameInput =
										ticketContainer.querySelector(
											'.se-ticket-data_name input'
										);

									if ( nameInput ) {
										nameInput.focus();
									} else {
										setTimeout( focusNameInput, 10 );
									}
								};

								focusNameInput();
							}

							setAttributes( { searchMode: false } );
						} }
						title={ labelTextNode }
					>
						{ ticketDataFormContainer }
					</PanelBody>
				</DraggableItem>
			) : (
				<PanelBody
					className="se-ticket-data-container se-new-ticket"
					initialOpen={ true }
					title={ labelTextNode }
				>
					{ ticketDataFormContainer }
				</PanelBody>
			) }
		</Fragment>
	);

	return (
		<Fragment>
			{ ( attributes.editMode &&
				editingProduct !== attributes.editMode ) ||
			( attributes.addMode && editingProduct ) ? (
				<Disabled>{ item }</Disabled>
			) : (
				item
			) }
		</Fragment>
	);
};

const initialTicketDataState = {
	dataLoaded: false,
	loading: false,
	name: '',
	price: '',
	saleData: false,
	saleDateFrom: '',
	saleDateTo: '',
	salePrice: '',
	stock: '',
	additionalFields: [
		{
			label: __( 'First Name', 'simple-events' ),
			type: 'first_name',
			required: true,
		},
		{
			label: __( 'Last Name', 'simple-events' ),
			type: 'last_name',
			required: true,
		},
		{
			label: __( 'Email Address', 'simple-events' ),
			type: 'email',
			required: true,
		},
	],
};

/**
 * Supplies the state withState used to inject, so the component body is unchanged.
 */
const TicketDataControlWithState = ( props ) => {
	const [ state, setState ] = useMergeState( initialTicketDataState );

	return <TicketDataControl { ...props } { ...state } setState={ setState } />;
};

export default TicketDataControlWithState;
