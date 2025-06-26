import moment from 'moment';
import { getSettings } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import { head, last } from 'lodash';

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
 * Get the DST offset for a given timestamp and timezone
 *
 * @param {number} timestamp The timestamp to check
 * @param {string|null} timezone The timezone to check (defaults to current timezone)
 * @param {string} currentTimezone The current event timezone
 * @returns {number} The offset in minutes
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
 * Creates a moment in the site timezone from the provided unix timestamp.
 *
 * @param {string} timestamp Timestamp to convert to a moment.
 * @param {boolean} formatted Whether to return a human-readable formatted string.
 * @param {string} currentTimezone The current timezone for the event
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
 * Creates a timestamp from the provided date string.
 *
 * @param {string} dateTime Date string to convert to a timestamp.
 * @param {string} currentTimezone The current timezone for the event
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
 * Get the start and end date from a collection of dates.
 * Will remove any event that has passed.
 *
 * @param {{all_day: boolean, datetime_start: string, datetime_end: string}[]} dates The dates to check.
 * @returns {{ datetime_start: string|null, datetime_end: string|null }}
 */
export const getStartAndEndDate = (dates) => {
	// iterate over and remove any that has passed.
	const now = moment().utcOffset(OFFSET);
	const filteredDates = dates.filter((date) => {
		const endDate = moment.unix(date.datetime_end).utcOffset(OFFSET);
		return endDate.isAfter(now);
	});

	// Closure that gets the first and last date.
	const getFirstAndLastDate = () => {
		return {
			datetime_start: moment.unix(head(dates).datetime_start).utcOffset(OFFSET).unix().toString(),
			datetime_end: moment.unix(last(dates).datetime_end).utcOffset(OFFSET).unix().toString(),
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
		const startDateMoment = moment.unix(date.datetime_start).utcOffset(OFFSET);
		const endDateMoment = moment.unix(date.datetime_end).utcOffset(OFFSET);

		// If the end date has passed, skip it.
		if (endDateMoment.isBefore(now)) {
			return;
		}

		/**
		 * Closure for setting the start or end date.
		 * @param {moment.Moment} startDateMoment
		 * @param {moment.Moment} endDateMoment
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
		startDate = moment.unix(head(filteredDates).datetime_start).utcOffset(OFFSET);
	}
	if (!endDate) {
		endDate = moment.unix(last(filteredDates).datetime_end).utcOffset(OFFSET);
	}
	return {
		datetime_start: startDate.unix().toString(),
		datetime_end: endDate.unix().toString(),
	};
};

/**
 * Creates a default date object for new events
 *
 * @param {Array} existingDates Array of existing dates
 * @param {string} currentTimezone The current timezone
 * @returns {Object} New date object
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

	console.log('eventStart', eventStart);
	console.log('eventEnd', eventEnd);

	return {
		start_date: wp.date.date('U', eventStart),
		end_date: wp.date.date('U', eventEnd),
		all_day: false,
		hide_from_calendar: false,
		hide_from_feed: false,
	};
};

/**
 * Combines a given date and time into a moment object.
 *
 * @param {string} date The date to combine.
 * @param {string} time The time to combine.
 * @return {moment.Moment} The combined date and time.
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
 * Check if the current time format is 12-hour
 *
 * @returns {boolean} True if 12-hour format
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
