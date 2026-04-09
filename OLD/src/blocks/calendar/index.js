/**
 * BLOCK: Calendar
 *
 * Calendar view.
 */

import './style.scss';
import './editor.scss';

import { registerBlockType } from '@wordpress/blocks';
import ServerSideRender from '@wordpress/server-side-render';
import { PanelBody, ToggleControl, SelectControl } from '@wordpress/components';
import {
	BlockControls,
	useBlockProps,
	AlignmentToolbar,
	InspectorControls,
	PanelColorSettings,
} from '@wordpress/block-editor';
import ColorPanel from './color-panel';
import { __ } from '@wordpress/i18n';

registerBlockType( 'simple-events/calendar', {
	edit: ( { attributes, setAttributes } ) => {
		const onChangeAlignment = ( newAlignment ) => {
			setAttributes( {
				alignment: newAlignment === undefined ? 'none' : newAlignment,
			} );
		};

		return (
			<>
				<BlockControls>
					<AlignmentToolbar
						value={ attributes.alignment }
						onChange={ onChangeAlignment }
						alignmentControls={ [] }
					/>
				</BlockControls>
				<InspectorControls>
					<PanelBody title="Event Configuration" initialOpen={ true }>
						<ToggleControl  
							label={ __(
								'Hide Events on Neighbouring Months',
								'simple-events'
							) }
							help={ __(
								'Used to hide events on the previous and next month for a particular month.',
								'simple-events'
							) }
							checked={ attributes?.hideNeighbourEvents }
							onChange={ ( value ) =>
								setAttributes( { hideNeighbourEvents: value } )
							}
						/>
						<ToggleControl
							label={ __(
								'Show Dot for Events ( Mobile )',
								'simple-events'
							) }
							help={ __(
								'Toggle to show / hide the dot to indicate events for smaller devices.',
								'simple-events'
							)}
							checked={ attributes?.showDot }
							onChange={ ( value ) =>
								setAttributes( { showDot: value } )
							}
						/>
						<PanelColorSettings
							colorSettings={ [
								{
									label: __(
										'Event Dot Color ( Mobile )',
										'simple-events'
									),
									value: attributes.eventDotColor,
									onChange: ( value ) =>
										setAttributes( {
											eventDotColor: value,
										} ),
									clearable: true,
								},
							] }
						/>
					</PanelBody>
					<PanelBody title="Event Modal Configuration" initialOpen={true}>
						<ToggleControl  
							label={ __( 'Enable Event Modal', 'simple-events' ) }
							help={ __(
								'Enables modal for all events. Modals for specific events can be disabled on the event edit page',
								'simple-events'
							) }
							checked={ attributes?.eventModalAccess }
							onChange={ ( value ) => setAttributes( { eventModalAccess: value } ) }
						/>
						<ToggleControl  
							label={ __( 'Show Event Title in Modal', 'simple-events' ) }
							help={ __(
								'Toggle to show/hide the title of the event in the modal. Applicable to all events.',
								'simple-events'
							) }
							checked={ attributes?.showModalTitle }
							onChange={ ( value ) => setAttributes( { showModalTitle: value } ) }
						/>
						<ToggleControl  
							label={ __( 'Show Event Excerpt in Modal', 'simple-events' ) }
							help={ __(
								'Toggle to show/hide the excerpt of the event in the modal. Applicable to all events.',
								'simple-events'
							) }
							checked={ attributes?.showModalExcerpt }
							onChange={ ( value ) => setAttributes( { showModalExcerpt: value } ) }
						/>
						<ToggleControl  
							label={ __( 'Show Event Modal when no thumbnail is defined', 'simple-events' ) }
							help={ __(
								'Toggle to show/hide the modal even if no thumbnail is defined.',
								'simple-events'
							) }
							checked={ attributes?.showModalWhenNoThumbnails }
							onChange={ ( value ) => setAttributes( { showModalWhenNoThumbnails: value } ) }
						/>
						<h3>{ __( 'Color Configuration', 'simple-events' ) }</h3>
						<PanelColorSettings
							colorSettings={ [
								{
									label: __( 'Background Color', 'simple-events' ),
									value: attributes?.modalBgColor,
									onChange: ( value ) => setAttributes( { modalBgColor: value } ),
									clearable: true,
								},
								{
									label: __( 'Text Color', 'simple-events' ),
									value: attributes?.modalTextColor,
									onChange: ( value ) => setAttributes( { modalTextColor: value } ),
									clearable: true,
								},
								{
									label: __( 'Icon Color', 'simple-events' ),
									value: attributes?.modalIconColor,
									onChange: ( value ) => setAttributes( { modalIconColor: value } ),
									clearable: true,
								}
							] }
						/>
					</PanelBody>
					<ColorPanel
						attributes={ attributes }
						setAttributes={ setAttributes }
						title={ __( "Today's Date", 'simple-events' ) }
						bgAttr="presentDayBg"
						colorAttr="presentDayColor"
						borderAttr="presentDayBorder"
					/>
					<ColorPanel
						attributes={ attributes }
						setAttributes={ setAttributes }
						title={ __( 'Days with Events', 'simple-events' ) }
						bgAttr="eventDaysBg"
						colorAttr="eventDaysColor"
						borderAttr="eventDaysBorder"
					/>
					<ColorPanel
						attributes={ attributes }
						setAttributes={ setAttributes }
						title={ __( 'Past Dates', 'simple-events' ) }
						bgAttr="pastDaysBg"
						colorAttr="pastDaysColor"
						borderAttr="pastDaysBorder"
					/>
					<ColorPanel
						attributes={ attributes }
						setAttributes={ setAttributes }
						title={ __( 'Upcoming Dates', 'simple-events' ) }
						bgAttr="upcomingDaysBg"
						colorAttr="upcomingDaysColor"
						borderAttr="upcomingDaysBorder"
					/>
					<PanelBody
						title={ __( 'Month and Year', 'simple-events' ) }
						initialOpen={ false }
					>
						<PanelColorSettings
							colorSettings={ [
								{
									label: __( 'Text Color', 'simple-events' ),
									value: attributes.monthYearColor,
									onChange: ( value ) =>
										setAttributes( {
											monthYearColor: value,
										} ),
									clearable: true,
								},
							] }
						/>
					</PanelBody>
					<PanelBody
						title={ __( 'Arrows', 'simple-events' ) }
						initialOpen={ false }
					>
						<PanelColorSettings
							colorSettings={ [
								{
									label: __( 'Arrow Color', 'simple-events' ),
									value: attributes.arrowColor,
									onChange: ( value ) =>
										setAttributes( { arrowColor: value } ),
									clearable: true,
								},
							] }
						/>
						<br />
						<SelectControl
							label={ __( 'Arrow Position', 'simple-events' ) }
							value={ attributes.arrowPosition }
							onChange={ ( value ) =>
								setAttributes( { arrowPosition: value } )
							}
							options={ [
								{
									label: __( 'Top', 'simple-events' ),
									value: 'top',
								},
								{
									label: __( 'Bottom', 'simple-events' ),
									value: 'bottom',
								},
							] }
						/>
						<SelectControl
							label={ __(
								'Arrow Position ( Mobile )',
								'simple-events'
							) }
							value={ attributes.mobileArrowPosition }
							onChange={ ( value ) =>
								setAttributes( { mobileArrowPosition: value } )
							}
							options={ [
								{
									label: __( 'Top', 'simple-events' ),
									value: 'top',
								},
								{
									label: __( 'Bottom', 'simple-events' ),
									value: 'bottom',
								},
							] }
						/>
					</PanelBody>
				</InspectorControls>
				<div { ...useBlockProps() }>
					<ServerSideRender
						block='simple-events/calendar'
						attributes={ attributes }
					/>
				</div>
			</>
		);
	},

	save: () => {
		return null;
	},
} );
