import { sortBy, isEqual, clone } from 'lodash';
import { getStartAndEndDate, createDefaultDate } from './dates';

/**
 * Creates a date manager service for handling event dates with change tracking
 *
 * @param {Array} initialDates Initial array of date objects
 * @param {string} timezone Current timezone for the event
 * @returns {Object} Date management service
 */

/**
	 * Creates a hash for a date object based on its start and end times
	 *
	 * @param string start The start time of the date
	 * @param string end The end time of the date
	 * @returns {string} A unique hash for the date
	 */
	const createDateHash = (start, end) => {
		// Get the current timestamp.
		const timestamp = Date.now();
		// Create a hash using the start and end times along with the timestamp.
		const hash = `${start}-${end}-${timestamp}`;
		return hash;
	}

export const dateManager = (initialDates = [], timezone = '') => {

	// lOOP through dates and add a hash to each date
	initialDates.dates.forEach(date => {
		date.hash = createDateHash(date.start_date, date.end_date);
	});


	// Internal state
	let originalDates = clone(initialDates.dates || []);
	let currentDates = clone(initialDates.dates || []);
	let isDirty = false;

	console.log({
		'originalDates': originalDates,
		'currentDates': currentDates,
		'isDirty': isDirty,
	});

	/**
	 * Refresh the date manager with new dates
	 *
	 * @param {Array} newDates - The new dates to set
	 * @returns {Object} Updated date management service
	 */
	const refreshWithNewDates = (newDates) => {
		// Add hash to each date if not present
		newDates.forEach(date => {
			if (!date.hash) {
				date.hash = createDateHash(date.start_date, date.end_date);
			}
		});

		// Update internal state
		originalDates = clone(newDates);
		currentDates = clone(newDates);
		isDirty = false;

		console.log('DateManager refreshed with new dates:', {
			originalDates,
			currentDates,
			isDirty
		});

		return getCurrentDates();
	};

	/**
	 * Get the current dates
	 *
	 * @return {Object}
	 *
	 */
	const getCurrentDates = () => {
		return {
			dates: currentDates,
			isDirty: isDirty,
		};
	}

	/**
	 * Find a date by its hash
	 *
	 * @param {string} hash The hash of the date to find
	 * @returns {Object} The date object
	 */
	const findDateByHash = (hash) => {
		return currentDates.find(d => d.hash === hash);
	}

	/**
	 * Upsert a date to the dates.
	 *
	 * Date
	 * {
	 * 	id: null|int,
	 *  hash: string,
	 *  start: string,
	 * 	end: string,
	 * 	allDay: boolean,
	 * 	showOnFeed: boolean,
	 * 	showOnCalendar: boolean
	 * }
	 *
	 * @param {Object} date Date object to add
	 * @returns {Object} Updated date management service
	 */
	const upsertDate = (date) => {
		// If the date doesnt contain a hash, generate one
		if (!date.hash) {
			date.hash = createDateHash(date.start_date, date.end_date);
		}

		// Check if the hash exists in the current dates
		const existingIndex = currentDates.findIndex(d => d.hash === date.hash);
		if (existingIndex !== -1) {
			// If it exists, update the date
			currentDates[existingIndex] = date;
			console.log('updated date', date);
		} else {
			// If it doesn't exist, add the new date
			currentDates.push(date);
			console.log('new date', date);
		}
		// Mark as dirty
		isDirty = true;

		// Sort the dates by start date
		currentDates = sortBy(currentDates, 'start_date');

		console.log('currentDates', currentDates);
		console.log('originalDates', originalDates);

		return getCurrentDates();
	}

	/**
	 * Remove a date from the dates.
	 *
	 * @param {Object} date Date object to remove
	 * @returns {Object} Updated date management service
	 */
	const removeDate = (date) => {
		console.log('removeDate', date);
		// Find the index of the date
		const index = currentDates.findIndex(d => d.hash === date.hash);
		if (index !== -1) {
			console.log('removing date', date, 'index', index);
			// Remove the date
			currentDates.splice(index, 1);
			// Mark as dirty
			isDirty = true;
			// Sort the dates by start date
			currentDates = sortBy(currentDates, 'start_date');
		}
		return getCurrentDates();
	}

	/**
	 * Add a new date to the dates.
	 *
	 * @returns {Object} Updated date management service
	 */
	const addDate = () => {
		const newDate = createDefaultDate(currentDates);
		upsertDate(newDate);
		console.log('isDirty', isDirty);
		return getCurrentDates();
	}

	/**
	 * Revert the dates to the original dates.
	 *
	 * @returns {Object} Updated date management service
	 */
	const revertDates = () => {
		currentDates = clone(originalDates);
		isDirty = false;
		return getCurrentDates();
	}

	// Return the public interface
	return {
		getCurrentDates,
		upsertDate,
		removeDate,
		addDate,
		revertDates,
		refreshWithNewDates,
		// Expose internal state getters for external access
		get originalDates() { return originalDates; },
		get currentDates() { return currentDates; },
		get isDirty() { return isDirty; },
		// Expose internal state setters for external access
		set originalDates(value) { originalDates = clone(value); },
		set currentDates(value) { currentDates = clone(value); },
		set isDirty(value) { isDirty = value; }
	};


};
