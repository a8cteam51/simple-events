// my-plugin.js
import { unregisterBlockType } from '@wordpress/blocks';
import domReady from '@wordpress/dom-ready';

domReady( function () {
	if ( window?.seSettings?.postType && 'se-event' !== window.seSettings.postType ) {
		unregisterBlockType( 'simple-events/event-tickets' );
	}
} );
