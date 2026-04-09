/* global seSettings */
import { registerBlockType } from "@wordpress/blocks";
import { InnerBlocks } from "@wordpress/block-editor";

import metadata from "./block.json";

registerBlockType( metadata, {
	edit: () => (
		<InnerBlocks
			template={ [
				[
					"core/paragraph",
					{ content: seSettings?.pastEventsNotice ?? "Event has passed" },
				],
			] }
		/>
	),
	save: () => (
		<InnerBlocks.Content />
	)
} );
