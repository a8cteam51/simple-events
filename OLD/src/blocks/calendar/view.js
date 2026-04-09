import domReady from '@wordpress/dom-ready';
import Calendar from './calendar';

/**
 * DOM Ready
 */
domReady( () => {
	/**
	 * Init Calendar
	 *
	 * @type {Calendar}
	 */
	const calendar = new Calendar();
	calendar.init();
} );
