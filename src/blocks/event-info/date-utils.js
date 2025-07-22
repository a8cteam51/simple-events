import moment from 'moment';
import { getSettings } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import { head, last } from 'lodash';

/**
 * Date and Time Utilities
 *
 * Utility functions for handling date/time operations, timezone conversions,
 * and date formatting within the Simple Events plugin. Provides consistent
 * date handling across the event management system.
 *
 * @package SimpleEvents
 * @since   1.0.0
 */

/**
 * Date/Time Constants
 */
export const DEFAULT_START_HOUR = 9;
export const DEFAULT_END_HOUR = 10;
export const FORMAT = 'YYYY-MM-DD HH:mm';

const DATE_SETTINGS = getSettings();
export const OFFSET = Number(DATE_SETTINGS.timezone.offset);
export const TIMEZONE = DATE_SETTINGS.timezone.string;

export let TIMEZONE_NAME = TIMEZONE;
if ('' === TIMEZONE) {
	TIMEZONE_NAME = 'UTC' + (OFFSET >= 0 ? '+' : '') + OFFSET;
}

export const TIMEZONES = moment.tz
	.names()
	.map((tz) => ({ label: tz, value: tz }));

// Add an option to use the site settings
TIMEZONES.unshift({
	label: __('Same as site', 'simple-events'),
	value: '',
});

/**
 * Gets the DST offset for a given timestamp and timezone.
 *
 * Calculates the daylight saving time offset for a specific timestamp
 * within a given timezone. Handles timezone conversions and DST transitions.
 *
 * @since 1.0.0
 *
 * @param {number}      timestamp       The timestamp to check.
 * @param {string|null} timezone        The timezone to check (defaults to current timezone).
 * @param {string}      currentTimezone The current event timezone.
 * @return {number} The offset in minutes.
 */
export const getDstOffset = (timestamp, timezone = null, currentTimezone = TIMEZONE) => {
	// Return no offset if the event timezone is the same as the site.
	if (null === timezone) {
		timezone = currentTimezone;
	}

	if ('' === timezone) {
		return OFFSET;
	}

	// Get the timezone details.
	const timezoneDetails = moment.tz.zone(timezone);

	// Get the index of the current timezone offset i.e DST or non-DST. -1 at the end to account for search algorithm.
	const untilIndex = timezoneDetails.untils.findIndex(function (number) {
		return number / 1000 > timestamp;
	});

	return timezoneDetails.offsets[untilIndex] * -1;
};

/**
 * Creates a moment object in the site timezone from a unix timestamp.
 *
 * Converts a unix timestamp to a moment object using the appropriate
 * timezone offset. Can optionally return a formatted string instead.
 *
 * @since 1.0.0
 *
 * @param {string}  timestamp       Timestamp to convert to a moment.
 * @param {boolean} formatted       Whether to return a human-readable formatted string.
 * @param {string}  currentTimezone The current timezone for the event.
 * @return {moment.Moment|string} Human readable formatted string if `formatted` is true, moment object otherwise.
 */
export const getMoment = (timestamp, formatted = false, currentTimezone = TIMEZONE) => {
	const dateTime = moment
		.unix(timestamp)
		.utcOffset(getDstOffset(timestamp, null, currentTimezone));

	if (!formatted) {
		return dateTime;
	}

	return dateTime.format(FORMAT);
};

/**
 * Creates a timestamp from a date string.
 *
 * Converts a date string to a unix timestamp, applying the appropriate
 * timezone offset for accurate time representation.
 *
 * @since 1.0.0
 *
 * @param {string} dateTime         Date string to convert to a timestamp.
 * @param {string} currentTimezone  The current timezone for the event.
 * @return {string} The timestamp, cast as a string.
 */
export const getTimestamp = (dateTime, currentTimezone = TIMEZONE) => {
	return String(
		moment(dateTime)
			.utcOffset(
				getDstOffset(moment(dateTime).unix(), null, currentTimezone),
				true
			)
			.utc()
			.unix()
	);
};

/**
 * Gets the start and end date from a collection of dates.
 *
 * Analyzes a collection of event dates to determine the overall start and end
 * times, filtering out dates that have already passed. Returns the earliest
 * start date and latest end date from valid future dates.
 *
 * @since 1.0.0
 *
 * @param {Array} dates Array of date objects with all_day, start_date, and end_date properties.
 * @return {Object} Object with start_date and end_date properties (strings or null).
 */
export const getStartAndEndDate = (dates) => {
	// iterate over and remove any that has passed.
	const now = moment().utcOffset(OFFSET);
	const filteredDates = dates.filter((date) => {
		const endDate = moment.unix(date.end_date).utcOffset(OFFSET);
		return endDate.isAfter(now);
	});

	/**
	 * Gets the first and last date from the collection.
	 *
	 * Helper closure that extracts the earliest start date and
	 * latest end date from the full date collection.
	 *
	 * @since 1.0.0
	 *
	 * @return {Object} Object with start_date and end_date properties.
	 */
	const getFirstAndLastDate = () => {
		return {
			start_date: moment.unix(head(dates).start_date).utcOffset(OFFSET).unix().toString(),
			end_date: moment.unix(last(dates).end_date).utcOffset(OFFSET).unix().toString(),
		};
	};

	let startDate = null;
	let endDate = null;

	// Do not trust the order of the dates.
	if (filteredDates.length === 0) {
		// Return the earliest start date and the latest end date.
		return getFirstAndLastDate();
	}

	// Loop over the dates and set the start date as the earliest and the end as the latest.
	filteredDates.forEach((date) => {
		const startDateMoment = moment.unix(date.start_date).utcOffset(OFFSET);
		const endDateMoment = moment.unix(date.end_date).utcOffset(OFFSET);

		// If the end date has passed, skip it.
		if (endDateMoment.isBefore(now)) {
			return;
		}

		/**
		 * Sets the start or end date based on comparison logic.
		 *
		 * Helper closure for determining and setting the earliest start date
		 * and latest end date from the filtered date collection.
		 *
		 * @since 1.0.0
		 *
		 * @param {moment.Moment} startDateMoment The start date moment to evaluate.
		 * @param {moment.Moment} endDateMoment   The end date moment to evaluate.
		 */
		const setDate = (startDateMoment, endDateMoment) => {
			// If the start date is before the current start date, set it.
			if (!startDate || startDateMoment.isBefore(startDate) || (startDate.isAfter(startDateMoment) && startDate.isBefore(now))) {
				startDate = startDateMoment;
			}

			// If the end date is after the current end date, set it.
			if (!endDate || endDateMoment.isAfter(endDate)) {
				endDate = endDateMoment;
			}
		};

		// If the start date if after now
		if (startDateMoment.isAfter(now) && endDateMoment.isAfter(now)) {
			setDate(startDateMoment, endDateMoment);
		} else if (startDateMoment.isBefore(now) && endDateMoment.isAfter(now)) {
			setDate(startDateMoment, endDateMoment);
		}
	});

	// If we have no startDate or endDate, just get the first from dates.
	if (!startDate) {
		startDate = moment.unix(head(filteredDates).start_date).utcOffset(OFFSET);
	}
	if (!endDate) {
		endDate = moment.unix(last(filteredDates).end_date).utcOffset(OFFSET);
	}
	return {
		start_date: startDate.unix().toString(),
		end_date: endDate.unix().toString(),
	};
};

/**
 * Creates a default date object for new events.
 *
 * Generates a new event date object with sensible defaults based on
 * existing dates and timezone. Sets the new date to be one day after
 * the last existing date, or uses current time with default hours.
 *
 * @since 2.0.0
 *
 * @param {Array}  existingDates   Array of existing date objects.
 * @param {string} currentTimezone The current timezone identifier.
 * @return {Object} New date object with start_date, end_date, and flag properties.
 */
export const createDefaultDate = (existingDates = [], currentTimezone = TIMEZONE) => {
	// Set default date and time.
	let eventStart = moment().utcOffset(OFFSET);
	eventStart.hour(DEFAULT_START_HOUR);
	eventStart.minute(0);
	eventStart.second(0);

	let eventEnd = eventStart.clone();
	eventEnd.hour(DEFAULT_END_HOUR);

	// Override with existing date if there is one.
	if (existingDates.length) {
		eventStart = getMoment(last(existingDates).start_date, false, currentTimezone);
		eventEnd = getMoment(last(existingDates).end_date, false, currentTimezone);
	}

	// Set default date to be +1 day from the last date.
	eventStart.add(1, 'days');
	eventEnd.add(1, 'days');

	return {
		start_date: wp.date.date('U', eventStart),
		end_date: wp.date.date('U', eventEnd),
		all_day: false,
		hide_from_calendar: false,
		hide_from_feed: false,
	};
};

/**
 * Combines a date and time into a moment object.
 *
 * Takes separate date and time strings and combines them into a single
 * moment object, preserving the date from the first parameter and the
 * time from the second parameter.
 *
 * @since 1.0.0
 *
 * @param {string} date The date string to use.
 * @param {string} time The time string to use.
 * @return {moment.Moment} The combined date and time as a moment object.
 */
export const combineDateAndTime = (date, time) => {
	const timeMoment = moment(time);
	const dateMoment = moment(date);

	// Set the timeMoment's time to the dateMoment.
	return dateMoment.set({
		hour: timeMoment.get('hour'),
		minute: timeMoment.get('minute'),
	});
};

/**
 * Checks if the current time format is 12-hour.
 *
 * Analyzes the WordPress date format settings to determine if the
 * site is configured to use 12-hour time format (with AM/PM indicators).
 *
 * @since 2.0.0
 *
 * @return {boolean} True if 12-hour format is used, false otherwise.
 */
export const is12HourTime = () => {
	const timeFormat = DATE_SETTINGS.formats.datetime;
	// To know if the current timezone is a 12 hour time with look for an "a" in the time format.
	// We also make sure this a is not escaped by a "/".
	return /a(?!\\)/i.test(
		timeFormat
			.toLowerCase() // Test only the lower case a
			.replace(/\\\\/g, '') // Replace "//" with empty strings
			.split('')
			.reverse()
			.join('') // Reverse the string and test for "a" not followed by a slash
	);
};
