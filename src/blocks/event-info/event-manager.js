import { sortBy, isEqual, clone } from 'lodash';
import { getStartAndEndDate, createDefaultDate, getDstOffset, TIMEZONE, OFFSET } from './date-utils';
import moment from 'moment';

/**
 * Date Manager Service
 *
 * Creates a date manager service for handling event dates with change tracking,
 * timezone management, and state synchronization. Provides a centralized way to
 * manage event dates with automatic dirty state tracking and meta synchronization.
 *
 * @package SimpleEvents
 * @since   2.0.0
 */

/**
 * Creates a hash for a date object based on its start and end times.
 *
 * Generates a unique identifier for a date object using start time, end time,
 * and current timestamp to ensure uniqueness across date operations.
 *
 * @since 2.0.0
 *
 * @param {string} start The start time of the date.
 * @param {string} end   The end time of the date.
 * @return {string} A unique hash for the date.
 */
const createDateHash = (start, end) => {
	// Get the current timestamp.
	const timestamp = Date.now();
	// Create a hash using the start and end times along with the timestamp.
	const hash = `${start}-${end}-${timestamp}`;
	return hash;
}

/**
 * Creates a date manager instance for handling event dates.
 *
 * Provides a comprehensive date management system with change tracking,
 * timezone conversion, and state synchronization. Manages both original
 * and current date states with automatic dirty flag tracking.
 *
 * @since 2.0.0
 *
 * @param {Array}  initialDates Array of initial date objects with dates property.
 * @param {string} timezone     Current timezone for the event.
 * @param {Object} metaSync     Optional meta sync object with meta and setMeta properties.
 * @return {Object} Date management service with public interface.
 */
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

	/**
	 * Refreshes the date manager with new dates.
	 *
	 * Updates both original and current date states with new data,
	 * adds hashes to dates if missing, and resets the dirty flag.
	 *
	 * @since 2.0.0
	 *
	 * @param {Array} newDates Array of new date objects to set.
	 * @return {Object} Updated date management service state.
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

		// If orginal timezone is not the same as current timezone, update current timezone
		if (originalTimezone !== currentTimezone && '' !== currentTimezone) {
			originalTimezone = currentTimezone;
		}


		return getCurrentDates();
	};

	/**
	 * Gets the current dates and timezone information.
	 *
	 * Returns the current state including dates, timezone, and dirty flag.
	 * Considers timezone changes when determining dirty state.
	 *
	 * @since 2.0.0
	 *
	 * @return {Object} Current dates object with dates, timezone, and isDirty properties.
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
	 * Finds a date by its hash identifier.
	 *
	 * Searches through current dates to find a date object
	 * matching the provided hash.
	 *
	 * @since 2.0.0
	 *
	 * @param {string} hash The hash identifier of the date to find.
	 * @return {Object|undefined} The date object if found, undefined otherwise.
	 */
	const findDateByHash = (hash) => {
		return currentDates.find(d => d.hash === hash);
	}

	/**
	 * Updates the timezone and converts all dates accordingly.
	 *
	 * Changes the event timezone and adjusts all date timestamps to
	 * maintain the same local time in the new timezone. Updates meta
	 * if sync is available.
	 *
	 * @since 2.0.0
	 *
	 * @param {string} newTimezone The new timezone identifier to set.
	 * @return {Object} Updated date management service state.
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
				'start_date',
				'end_date',
			].forEach((key) => {
				// Get the current DST offset
				const currentOffset = getDstOffset(
					eventDateTime[key],
					currentTimezone,
					currentTimezone
				);

				// Get the target DST offset
				const targetOffset = '' !== targetTimezone
					? getDstOffset(
						eventDateTime[key],
						targetTimezone,
						targetTimezone
					)
					: OFFSET;

				// Apply target timezone offset and keep same local time
				eventDateTime[key] = String(
					moment
						.unix(eventDateTime[key])
						.utcOffset(currentOffset)
						.utcOffset(targetOffset, true)
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

		return getCurrentDates();
	};

	/**
	 * Upserts a date to the event dates collection.
	 *
	 * Updates an existing date if the hash matches, otherwise adds a new date.
	 * Automatically generates hash if missing and maintains sorted order by start date.
	 *
	 * @since 2.0.0
	 *
	 * @param {Object} date Date object to add or update with properties:
	 *                      - id: null|int
	 *                      - hash: string
	 *                      - start_date: string
	 *                      - end_date: string
	 *                      - all_day: boolean
	 *                      - hide_from_feed: boolean
	 *                      - hide_from_calendar: boolean
	 * @return {Object} Updated date management service state.
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
		} else {
			// If it doesn't exist, add the new date
			currentDates.push(date);
		}
		// Mark as dirty
		isDirty = true;

		// Sort the dates by start date
		currentDates = sortBy(currentDates, 'start_date');

		return getCurrentDates();
	}

	/**
	 * Removes a date from the event dates collection.
	 *
	 * Finds and removes a date object by its hash identifier,
	 * then maintains sorted order and marks state as dirty.
	 *
	 * @since 2.0.0
	 *
	 * @param {Object} date Date object to remove (must contain hash property).
	 * @return {Object} Updated date management service state.
	 */
	const removeDate = (date) => {
		// Find the index of the date
		const index = currentDates.findIndex(d => d.hash === date.hash);
		if (index !== -1) {
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
	 * Adds a new default date to the event dates collection.
	 *
	 * Creates a new date object with default values based on existing dates
	 * and current timezone, then adds it to the collection.
	 *
	 * @since 2.0.0
	 *
	 * @return {Object} Updated date management service state.
	 */
	const addDate = () => {
		const newDate = createDefaultDate(currentDates, currentTimezone);
		upsertDate(newDate);
		return getCurrentDates();
	}

	/**
	 * Reverts dates and timezone to their original state.
	 *
	 * Restores both dates and timezone to their initial values,
	 * clears dirty flag, and syncs timezone back to meta if available.
	 *
	 * @since 2.0.0
	 *
	 * @return {Object} Reverted date management service state.
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
