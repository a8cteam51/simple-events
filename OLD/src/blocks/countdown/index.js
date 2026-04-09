/**
 * BLOCK: Countdown
 *
 * Displays a countdown to the next upcoming post.
 */

import './style.scss';

// Import JS dependencies.
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import ServerSideRender from '@wordpress/server-side-render';
import { useBlockProps } from '@wordpress/block-editor';


registerBlockType( 'simple-events/countdown', {
	edit: ( { attributes } ) => {
		// Fire `simpleEventsCountdownTimer()` once the `#event-timer` element loads.
		const loadTimerScript = () => {
			if ( document.getElementById( 'event-timer' ) ) {
				if ( typeof simpleEventsCountdownTimer === 'function' ) {
					simpleEventsCountdownTimer(); // eslint-disable-line no-undef
				}
			} else {
				setTimeout( () => loadTimerScript(), 100 );
			}
		};

		loadTimerScript();

		return (
			<div { ...useBlockProps() }>
				<ServerSideRender
					block="simple-events/countdown"
					attributes={ attributes }
				/>
			</div>
		);
	},

	save: () => {
		return null;
	},
} );
