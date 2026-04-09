import { getBlockType, unregisterBlockType, registerBlockType } from '@wordpress/blocks';
import domReady from '@wordpress/dom-ready';

domReady( function () {
	if ( window?.seSettings?.postType && 'se-event' !== window.seSettings.postType ) {
		const blockType = getBlockType( 'simple-events/event-tickets' );
		if ( blockType ) {
			unregisterBlockType( 'simple-events/event-tickets' );
			registerBlockType( 'simple-events/event-tickets', {
				...blockType,
				supports: {
					...blockType.supports,
					inserter: false,
				},
			} );
		}
	}
} );
