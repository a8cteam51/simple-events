/**
 * BLOCK: Upcoming Events
 *
 * Displays upcoming event posts.
 */
import './index.scss';

import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import {
	Disabled,
	PanelBody,
	RangeControl,
	ToolbarGroup,
	SelectControl,
	TimePicker,
	BaseControl,
	Button,
	Dropdown,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import {
	InspectorControls,
	BlockControls,
	useBlockProps,
	InnerBlocks,
} from '@wordpress/block-editor';
import { list, grid, edit } from '@wordpress/icons';
import { useState } from '@wordpress/element';

registerBlockType( 'simple-events/upcoming-events', {
	edit: ( { attributes, setAttributes } ) => {
		const { count, layout, columns, feedType, feedOrder, overrideFeedOrder, showYearDividers, dateRange } = attributes;

		const [ noEvents, setNoEvents ] = useState( false );

		return (
			<div { ...useBlockProps() }>
				<InspectorControls>
					<PanelBody
						title={ __( 'Display Options', 'simple-events' ) }
						className="panelbody-custom-latest-posts"
					>
						<SelectControl
							label={ __( 'Feed layout', 'simple-events' ) }
							value={ layout }
							options={ [
								{ label: 'List', value: 'list' },
								{ label: 'Grid', value: 'grid' },
							] }
							onChange={ ( value ) =>
								setAttributes( { layout: value } )
							}
							__nextHasNoMarginBottom
						/>
						{ 'grid' === layout && (
							<RangeControl
								label={ __( 'Grid columns', 'simple-events' ) }
								value={ columns }
								onChange={ ( value ) =>
									setAttributes( { columns: value } )
								}
								min={ 1 }
								max={ 4 }
							/>
						) }
						<Button
							variant="primary"
							onClick={ () => setNoEvents( ! noEvents ) }
						>
							{ noEvents
								? __(
										'Hide "no results" view',
										'simple-events'
								  )
								: __(
										'Edit "no results" view',
										'simple-events'
								  ) }
						</Button>
					</PanelBody>
					<PanelBody
						title={ __( 'Event Options', 'simple-events' ) }
						className="panelbody-custom-latest-posts"
					>
						<SelectControl
							label={ __( 'Feed type', 'simple-events' ) }
							value={ feedType }
							options={ [
								{ label: 'Future Events', value: 'upcoming' },
								{ label: 'Past Events', value: 'past' },
								{
									label: 'Past & Future Events',
									value: 'mixed',
								},
								{
									label: 'Events in a Date Range',
									value: 'range',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { feedType: value } )
							}
						/>
						{ 'range' === feedType && (
							<>
								<BaseControl
									label={
										'From Date: ' +
										new Date(
											dateRange.from
										).toLocaleString( 'en-US' )
									}
								>
									<Dropdown
										position="bottom left"
										renderToggle={ ( {
											isOpen,
											onToggle,
										} ) => (
											<Button
												isSecondary
												onClick={ onToggle }
												aria-expanded={ isOpen }
											>
												{ isOpen
													? 'Close Calendar'
													: "Pick 'From' Date" }
											</Button>
										) }
										renderContent={ () => {
											return (
												<TimePicker
													currentDate={
														dateRange.from
													}
													onChange={ ( value ) =>
														setAttributes( {
															dateRange: {
																...dateRange,
																from: value,
															},
														} )
													}
												/>
											);
										} }
									/>
								</BaseControl>
								<BaseControl
									label={
										'To Date: ' +
										new Date( dateRange.to ).toLocaleString(
											'en-US'
										)
									}
								>
									<Dropdown
										position="bottom left"
										renderToggle={ ( {
											isOpen,
											onToggle,
										} ) => (
											<Button
												isSecondary
												onClick={ onToggle }
												aria-expanded={ isOpen }
											>
												{ isOpen
													? 'Close Calendar'
													: "Pick 'To' Date" }
											</Button>
										) }
										renderContent={ () => {
											return (
												<TimePicker
													currentDate={ dateRange.to }
													onChange={ ( value ) =>
														setAttributes( {
															dateRange: {
																...dateRange,
																to: value,
															},
														} )
													}
												/>
											);
										} }
									/>
								</BaseControl>
							</>
						) }
						<RangeControl
							label={ __( 'Number of Events', 'simple-events' ) }
							value={ count }
							onChange={ ( value ) =>
								setAttributes( { count: value } )
							}
							min={ 1 }
							max={ 50 }
						/>
						<ToggleControl
							label="Show Year Dividers"
							checked={ showYearDividers }
							onChange={ ( value ) => setAttributes( { showYearDividers: value } ) }
						/>
						<ToggleControl
							label="Override Default Order"
							checked={ overrideFeedOrder }
							onChange={ ( value ) => setAttributes( { overrideFeedOrder: value } ) }
						/>
						{ overrideFeedOrder && <SelectControl
							label="Feed Order"
							value={ feedOrder }
							options={ [
								{ label: 'Oldest First', value: 'ASC' },
								{ label: 'Newest First', value: 'DESC' },
							] }
							onChange={ ( value ) => setAttributes( { feedOrder: value } ) }
						/> }
					</PanelBody>
				</InspectorControls>
				<BlockControls>
					<ToolbarGroup
						controls={ [
							{
								icon: list,
								title: __( 'List view', 'simple-events' ),
								onClick: () =>
									setAttributes( { layout: 'list' } ),
								isActive: layout === 'list',
							},
							{
								icon: grid,
								title: __( 'Grid view', 'simple-events' ),
								onClick: () =>
									setAttributes( { layout: 'grid' } ),
								isActive: layout === 'grid',
							},
							{
								icon: edit,
								title: __(
									'Edit "no results" view',
									'simple-events'
								),
								onClick: () => setNoEvents( ! noEvents ),
								isActive: noEvents,
							},
						] }
					/>
				</BlockControls>
				{ noEvents ? (
					<InnerBlocks />
				) : (
					<Disabled>
						<ServerSideRender
							block="simple-events/upcoming-events"
							attributes={ attributes }
						/>
					</Disabled>
				) }
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
	save: ( props ) => <InnerBlocks.Content />,
} );
