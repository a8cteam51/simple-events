import './index.scss';
import './editor.scss';
import metadata from './block.json';

import { __, sprintf } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components'
import ServerSideRender from '@wordpress/server-side-render';
import {
	AlignmentControl,
	useBlockProps,
	InspectorControls,
	BlockControls,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { dateI18n, getSettings as getDateSettings } from '@wordpress/date';

registerBlockType(metadata, {
	edit: ({ attributes: { metaName, metaPrefix, thePostId, textAlign, addCalendarLinks, feedType, order, dateFormat, timeFormat, tagName }, setAttributes, context: { postId }, clientId }) => {

		const siteFormats = getDateSettings().formats;
		const siteDateFormat = siteFormats.date;
		const siteTimeFormat = siteFormats.time;
		const showDateFormat = metaName === 'dates' || metaName === 'date';
		const showTimeFormat = metaName === 'dates' || metaName === 'time';

		const formatPreview = (format) => {
			if (!format) {
				return '';
			}
			try {
				return dateI18n(format, new Date());
			} catch (e) {
				return '';
			}
		};

		// Get query loop data from our custom store
		const queryData = useSelect((select) => {
			const blockEditor = select('core/block-editor');
			const parents = blockEditor.getBlockParents(clientId);

			// Find the query block parent
			for (const parentId of parents) {
				const parentBlock = blockEditor.getBlock(parentId);
				if (parentBlock && parentBlock.name === 'core/query') {
					const storeData = select('se-events/query-data').getQueryData(parentId);
					return storeData || {};
				}
			}
			return {};
		}, [clientId]);

		const { feedType: contextFeedType = feedType, order: contextOrder = order } = queryData;

		// Update block attributes when context values change
		useEffect(() => {
			if (contextFeedType !== feedType || contextOrder !== order) {
				setAttributes({
					feedType: contextFeedType,
					order: contextOrder,
				});
			}
		}, [contextFeedType, contextOrder, feedType, order, setAttributes]);

		return (
			<>
				<InspectorControls>
					<PanelBody
						title={__('Display Options', 'simple-events')}
					>
						<SelectControl
							label={__('HTML element', 'simple-events')}
							value={tagName}
							options={[
								{ label: __( 'Default (div)', 'simple-events' ), value: 'div' },
								{ label: __( 'Paragraph (p)', 'simple-events' ), value: 'p' },
								{ label: __( 'Heading 1 (h1)', 'simple-events' ), value: 'h1' },
								{ label: __( 'Heading 2 (h2)', 'simple-events' ), value: 'h2' },
								{ label: __( 'Heading 3 (h3)', 'simple-events' ), value: 'h3' },
								{ label: __( 'Heading 4 (h4)', 'simple-events' ), value: 'h4' },
								{ label: __( 'Heading 5 (h5)', 'simple-events' ), value: 'h5' },
								{ label: __( 'Heading 6 (h6)', 'simple-events' ), value: 'h6' },
							]}
							onChange={(value) =>
								setAttributes({ tagName: value })
							}
							__nextHasNoMarginBottom
						/>
						<SelectControl
							label={__('Show what event info?', 'simple-events')}
							value={metaName}
							options={[
								{ label: __( 'Date & Time', 'simple-events' ), value: 'dates' },
								{ label: __( 'Location', 'simple-events' ), value: 'location' },
								{ label: __( 'Venue', 'simple-events' ), value: 'venue' },
								{ label: __( 'Date Only', 'simple-events' ), value: 'date' },
								{ label: __( 'Time Only', 'simple-events' ), value: 'time' },
							]}
							onChange={(value) =>
								setAttributes({ metaName: value })
							}
							__nextHasNoMarginBottom
						/>
						<TextControl
							label={__('Prefix', 'simple-events')}
							value={metaPrefix}
							onChange={(value) =>
								setAttributes({ metaPrefix: value })
							}
							__nextHasNoMarginBottom
						/>
						<ToggleControl
							label={ __( 'Show "Add to calendar" links', 'simple-events' ) }
							checked={ addCalendarLinks }
							onChange={ (value) =>
								setAttributes({ addCalendarLinks: value } )
							}
						/>
						{ showDateFormat && (
							<TextControl
								label={__('Date format override', 'simple-events')}
								help={
									dateFormat
										? sprintf(
											/* translators: %s: rendered date example. */
											__('Preview: %s', 'simple-events'),
											formatPreview(dateFormat)
										)
										: sprintf(
											/* translators: %s: site default date format. */
											__('Leave empty to use the site default (%s).', 'simple-events'),
											siteDateFormat
										)
								}
								placeholder={siteDateFormat}
								value={dateFormat}
								onChange={(value) =>
									setAttributes({ dateFormat: value })
								}
								__nextHasNoMarginBottom
							/>
						) }
						{ showTimeFormat && (
							<TextControl
								label={__('Time format override', 'simple-events')}
								help={
									timeFormat
										? sprintf(
											/* translators: %s: rendered time example. */
											__('Preview: %s', 'simple-events'),
											formatPreview(timeFormat)
										)
										: sprintf(
											/* translators: %s: site default time format. */
											__('Leave empty to use the site default (%s).', 'simple-events'),
											siteTimeFormat
										)
								}
								placeholder={siteTimeFormat}
								value={timeFormat}
								onChange={(value) =>
									setAttributes({ timeFormat: value })
								}
								__nextHasNoMarginBottom
							/>
						) }
					</PanelBody>
				</InspectorControls>
				<BlockControls group="block">
					<AlignmentControl
						value={textAlign}
						onChange={(nextAlign) => {
							setAttributes({ textAlign: nextAlign });
						}}
					/>
				</BlockControls>
				<div {...useBlockProps()}>
					<ServerSideRender
						block={metadata.name}
						attributes={{
							metaName,
							metaPrefix,
							textAlign,
							thePostId: postId, // Passes the current post ID to the render callback, even if in a query loop.
							addCalendarLinks,
							feedType, // Use block attribute values
							order, // Use block attribute values
							dateFormat,
							timeFormat,
							tagName,
						}}
					/>
				</div>
			</>
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
	save: (props) => null,
});
