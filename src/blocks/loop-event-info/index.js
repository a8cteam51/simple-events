import './index.scss';
import './editor.scss';
import metadata from './block.json';

import { __ } from '@wordpress/i18n';
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

registerBlockType(metadata, {
	edit: ({ attributes: { metaName, metaPrefix, thePostId, textAlign, addCalendarLinks }, setAttributes, context: { postId } }) => {
		return (
			<>
				<InspectorControls>
					<PanelBody
						title={__('Display Options', 'simple-events')}
					>
						<SelectControl
							label={__('Show what event info?', 'simple-events')}
							value={metaName}
							options={[
								{ label: __( 'Dates & Time', 'simple-events' ), value: 'dates' },
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
