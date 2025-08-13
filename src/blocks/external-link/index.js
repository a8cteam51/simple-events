import './editor.scss';
import metadata from './block.json';

import { useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import ServerSideRender from '@wordpress/server-side-render';

registerBlockType( metadata, {
	edit: ({ attributes: { thePostId }, context: { postId } }) => {
		const blockProps = useBlockProps();

		return (
			<div {...blockProps}>
				<ServerSideRender
					block={metadata.name}
					attributes={{
						thePostId: postId, // Passes the current post ID to the render callback, even if in a query loop.
					}}
				/>
			</div>
		);
	},
	save: (props) => null,
});
