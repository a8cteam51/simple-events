import { sortBy, isEqual, clone } from 'lodash';
import { getStartAndEndDate, createDefaultDate, getDstOffset, TIMEZONE, OFFSET } from './date-utils';
import moment from 'moment';

/**
 * Creates a date manager service for handling event dates with change tracking
 *
 * @param {Array} initialDates Initial array of date objects
 * @param {string} timezone Current timezone for the event
 * @param {Object} metaSync Optional meta sync object with meta and setMeta
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

export const dateManager = (initialDates = [], timezone = '', metaSync = null) => {

	// lOOP through dates and add a hash to each date
	initialDates.dates.forEach(date => {
		date.hash = createDateHash(date.start_date, date.end_date);
	});

	// Internal state
	let originalDates = clone(initialDates.dates || []);
	let currentDates = clone(initialDates.dates || []);
	let originalTimezone = timezone || TIMEZONE;
	let currentTimezone = timezone || TIMEZONE;
	let isDirty = false;

	// Meta sync helpers
	const { meta, setMeta } = metaSync || {};

	console.log({
		'originalDates': originalDates,
		'currentDates': currentDates,
		'originalTimezone': originalTimezone,
		'currentTimezone': currentTimezone,
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
			originalTimezone,
			currentTimezone,
			isDirty
		});

		return getCurrentDates();
	};

	/**
	 * Get the current dates and timezone info
	 *
	 * @return {Object}
	 *
	 */
	const getCurrentDates = () => {
		const timezoneChanged = currentTimezone !== originalTimezone;
		return {
			dates: currentDates,
			timezone: currentTimezone,
			isDirty: isDirty || timezoneChanged,
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
	 * Update the timezone and convert all dates accordingly
	 *
	 * @param {string} newTimezone The new timezone to set
	 * @returns {Object} Updated date management service
	 */
	const updateTimezone = (newTimezone) => {
		const updatedDates = clone(currentDates);

		// Ensure that the value is a string.
		newTimezone = !Boolean(newTimezone) ? '' : newTimezone;
		let targetTimezone = newTimezone;

		if ('' === newTimezone) {
			targetTimezone = TIMEZONE;
		}

		updatedDates.forEach((eventDateTime) => {
			[
				'datetime_start',
				'datetime_end',
			].forEach((key) => {
				const dateTime = moment
					.unix(eventDateTime[key])
					.utcOffset(
						getDstOffset(
							eventDateTime[key],
							currentTimezone,
							currentTimezone
						)
					);

				const newOffset =
					'' !== targetTimezone
						? getDstOffset(
							eventDateTime[key],
							targetTimezone,
							targetTimezone
						)
						: OFFSET;

				eventDateTime[key] = String(
					dateTime
						.utcOffset(newOffset, true)
						.utc()
						.unix()
				);
			});
		});

		// Update internal state
		currentDates = updatedDates;
		currentTimezone = newTimezone;
		isDirty = true; // Mark as dirty since timezone changed

		// Sync to meta if available
		if (setMeta && meta) {
			setMeta({
				...meta,
				se_event_timezone: newTimezone
			});
		}

		console.log('Timezone updated:', {
			newTimezone,
			updatedDates,
			currentTimezone,
			isDirty
		});

		return getCurrentDates();
	};

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
		const newDate = createDefaultDate(currentDates, currentTimezone);
		upsertDate(newDate);
		console.log('isDirty', isDirty);
		return getCurrentDates();
	}

	/**
	 * Revert the dates and timezone to the original state.
	 *
	 * @returns {Object} Updated date management service
	 */
	const revertDates = () => {
		currentDates = clone(originalDates);
		currentTimezone = originalTimezone;
		isDirty = false;

		// Sync timezone revert to meta if available
		if (setMeta && meta) {
			setMeta({
				...meta,
				se_event_timezone: originalTimezone
			});
		}

		console.log('Reverted to original state:', {
			originalDates,
			originalTimezone,
			isDirty
		});

		return getCurrentDates();
	}

	// Return the public interface
	return {
		getCurrentDates,
		updateTimezone,
		upsertDate,
		removeDate,
		addDate,
		revertDates,
		refreshWithNewDates,
		// Expose internal state getters for external access
		get originalDates() { return originalDates; },
		get currentDates() { return currentDates; },
		get originalTimezone() { return originalTimezone; },
		get currentTimezone() { return currentTimezone; },
		get isDirty() { return isDirty; },
		// Expose internal state setters for external access
		set originalDates(value) { originalDates = clone(value); },
		set currentDates(value) { currentDates = clone(value); },
		set originalTimezone(value) { originalTimezone = value; },
		set currentTimezone(value) { currentTimezone = value; },
		set isDirty(value) { isDirty = value; }
	};


};
