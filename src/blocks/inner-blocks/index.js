/**
 * BLOCK: Inner Blocks
 *
 * Single block that nests other blocks using the InnerBlocks component.
 */

import './style.scss';

import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

registerBlockType( 'simple-events/inner-blocks', {
	edit: () => {
		return (
			<div { ...useBlockProps() }>
				<InnerBlocks templateLock={ false } />
			</div>
		);
	},

	save: () => {
		return <InnerBlocks.Content />;
	},
} );
