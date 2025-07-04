import React, { Fragment, useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import {
	BaseControl,
	Dropdown,
	Button,
	DateTimePicker,
	CheckboxControl,
	ToggleControl,
} from '@wordpress/components';
import { clone } from 'lodash';
import moment from 'moment';
import { getSettings } from '@wordpress/date';

// Import date utilities
import {
	DEFAULT_START_HOUR,
	DEFAULT_END_HOUR,
	getMoment,
	getTimestamp,
	combineDateAndTime,
	is12HourTime,
} from '../date-utils';

const DATE_SETTINGS = getSettings();

/**
 * DateTimeGroup component for managing event date and time settings.
 *
 * @param {Object} props - Component props
 * @param {Object} props.eventDateTime - The event date/time object
 * @param {boolean} props.eventDateTime.all_day - Whether the event is all day
 * @param {string} props.eventDateTime.end_date - End date timestamp as string
 * @param {string} props.eventDateTime.hash - Unique hash for the event datetime
 * @param {boolean} props.eventDateTime.hide_from_calendar - Whether to hide from calendar
 * @param {boolean} props.eventDateTime.hide_from_feed - Whether to hide from feed
 * @param {number} props.eventDateTime.id - Event datetime ID
 * @param {string} props.eventDateTime.start_date - Start date timestamp as string
 * @param {Function} props.removeDate - Function to remove this date
 * @param {boolean} props.hasMultipleDates - Whether this is a multi-day event
 * @param {string} props.currentTimezone - Current timezone
 * @param {Object} props.dateManagerInstance - Date manager instance for updating dates
 */
const DateTimeGroupNew = ({
	eventDateTime,
	removeDate,
	hasMultipleDates,
	currentTimezone,
	dateManagerInstance
}) => {
	const [tempEventDate, setTempEventDate] = useState(null);
	const [tempEventTime, setTempEventTime] = useState(null);
	// Add local state to track the current eventDateTime
	const [currentEventDateTime, setCurrentEventDateTime] = useState(eventDateTime);
	// Add state to track if this date has been removed
	const [isRemoved, setIsRemoved] = useState(false);

	// Update local state when eventDateTime prop changes (e.g., timezone update)
	useEffect(() => {
		setCurrentEventDateTime(eventDateTime);
	}, [eventDateTime]);


	const eventStart = getMoment(
		currentEventDateTime.start_date,
		true,
		currentTimezone
	);
	const eventEnd = getMoment(currentEventDateTime.end_date, true, currentTimezone);
	const timeFormat = DATE_SETTINGS.formats.datetime;

	/**
	 * Handle the set date and time button click.
	 *
	 * @param {boolean} isStartChange Whether this is start or end date change.
	 *
	 * @return {void}
	 */
	const setDateTimeHandler = (isStartChange, onClose) => {
		// Ensure we have either new date or time in state.
		if (!tempEventDate && !tempEventTime) {
			return;
		}

		const newDate =
			tempEventDate ||
			(isStartChange ? eventStart : eventEnd);
		const newTime =
			tempEventTime ||
			(isStartChange ? eventStart : eventEnd);

		// Combine the new date and time and convert to a timestamp.
		const newDateTime = getTimestamp(
			combineDateAndTime(newDate, newTime),
			currentTimezone
		);

		const newEventDateTime = clone(currentEventDateTime);

		if (isStartChange) {
			newEventDateTime.start_date = newDateTime;

			// Check if the new start time is after the current end time.
			if (
				parseInt(newEventDateTime.start_date) >=
				parseInt(newEventDateTime.end_date)
			) {
				// Set the new end time to be 1 hour after the start dateTime.
				newEventDateTime.end_date = String(
					parseInt(newEventDateTime.start_date) +
					3600
				);
			}
		} else {
			newEventDateTime.end_date = newDateTime;

			// Check if the new end time is before the current start time.
			if (
				parseInt(newEventDateTime.start_date) >=
				parseInt(newEventDateTime.end_date)
			) {
				// Set the new start time to be 1 hour before the end dateTime.
				newEventDateTime.start_date = String(
					parseInt(newEventDateTime.end_date) - 3600
				);
			}
		}

		// Reset the temp date and time.
		setTempEventDate(null);
		setTempEventTime(null);

		// Use dateManagerInstance to save the changes if available
		if (dateManagerInstance && dateManagerInstance.upsertDate) {
			dateManagerInstance.upsertDate(newEventDateTime);
		}

		// Update the current eventDateTime state
		setCurrentEventDateTime(newEventDateTime);

		// Close the appropriate dropdown
			onClose();

		if (isStartChange) {
		}
	};

	/**
	 * Handles DateTimePicker changes.
	 *
	 * @param {string} currentDateTime The current dateTime.
	 * @param {string} newDateTime     The new selected dateTime.
	 *
	 * @return {void}
	 */
	const datePickerHandler = (currentDateTime, newDateTime) => {
		// Compare the date without time to see if the time or date was changed.
		const isDateChange =
			moment(currentDateTime).format('YYYY-MM-DD') ===
			moment(newDateTime).format('YYYY-MM-DD');

		if (isDateChange) {
			setTempEventTime(newDateTime);
		} else {
			setTempEventDate(newDateTime);
		}
	};

	return (
		<div
			className={`se-datetimegroup-container ${isRemoved ? 'se-datetimegroup-removed' : ''}`}
			style={isRemoved ? { display: 'none' } : {}}
		>
			{hasMultipleDates && (
				<div className="se-datetime-control__delete">
					<Button
						isDestructive
						icon="no-alt"
						label={__(
							'Remove date',
							'simple-events'
						)}
						onClick={() => {
							setIsRemoved(true);
							dateManagerInstance.removeDate(currentEventDateTime);
						}}
					/>
				</div>
			)}
			<div className="se-datetimegroup-controls">
				<BaseControl
					label={__(
						'Start Date/Time',
						'simple-events'
					)}
				>
					<Dropdown
						contentClassName="se-datetime-popover se-datetime-popover__time"
						popoverProps={{ placement: 'bottom' }}
						renderToggle={({ isOpen, onToggle }) => (
							<Button
								className="se-datetime-popover__button"
								variant="secondary"
								onClick={() => {
									onToggle();
								}}
								aria-expanded={isOpen}
							>
								{currentEventDateTime.all_day
									? wp.date.format(
										'F j, Y',
										eventStart
									)
									: wp.date.format(
										timeFormat,
										eventStart
									)}
							</Button>
						)}
						renderContent={({  onClose }) => (
							<Fragment>
								<DateTimePicker
									currentDate={tempEventDate || tempEventTime || eventStart}
									is12Hour={is12HourTime()}
									onChange={(newDateTime) =>
										datePickerHandler(
											eventStart,
											newDateTime
										)
									}
									__nextRemoveHelpButton
									__nextRemoveResetButton
								/>
								<Button
									className="se-datetime-popover__set-datetime"
									onClick={() =>
										setDateTimeHandler(true, onClose)
									}
									variant="secondary"
								>
									{__(
										'Set time',
										'simple-events'
									)}
								</Button>
							</Fragment>
						)}
					/>
				</BaseControl>
				<BaseControl
					label={__('End Date/Time', 'simple-events')}
				>
					<Dropdown
						contentClassName="se-datetime-popover se-datetime-popover__time"
						popoverProps={{ placement: 'bottom' }}
						renderToggle={({ isOpen, onToggle }) => (
							<Button
								className="se-datetime-popover__button"
								variant="secondary"
								onClick={() => {
									onToggle();
								}}
								aria-expanded={isOpen}
								disabled={currentEventDateTime.all_day}
							>
								{currentEventDateTime.all_day
									? '--:--'
									: wp.date.format(
										timeFormat,
										eventEnd
									)}
							</Button>
						)}
						renderContent={({ onClose }) => (
							<Fragment>
								<DateTimePicker
									currentDate={tempEventDate || tempEventTime || eventEnd}
									is12Hour={is12HourTime()}
									onChange={(newDateTime) =>
										datePickerHandler(
											eventEnd,
											newDateTime
										)
									}
									__nextRemoveHelpButton
									__nextRemoveResetButton
								/>
								<Button
									className="se-datetime-popover__set-datetime"
									onClick={() =>
										setDateTimeHandler(false, onClose)
									}
									variant="secondary"
									text={__(
										'Set time',
										'simple-events'
									)}
								/>
							</Fragment>
						)}
					/>
				</BaseControl>
				<BaseControl>
					<CheckboxControl
						label={__('All day', 'simple-events')}
						className='se-all-day-checkbox'
						checked={currentEventDateTime.all_day}
						onChange={() => {
							const newEventDateTime = clone(currentEventDateTime);

							newEventDateTime.all_day = !currentEventDateTime.all_day;

							newEventDateTime.start_date = getMoment(
								newEventDateTime.start_date
							);
							newEventDateTime.end_date = getMoment(
								newEventDateTime.end_date
							);

							// If all day event, set time between 00:00 and 23:59.
							if (newEventDateTime.all_day) {
								newEventDateTime.start_date.startOf('date');
								newEventDateTime.end_date.endOf('date');
							} else {
								newEventDateTime.start_date
									.hour(DEFAULT_START_HOUR)
									.minute(0);
								newEventDateTime.end_date
									.hour(DEFAULT_END_HOUR)
									.minute(0);
							}

							newEventDateTime.start_date = getTimestamp(
								newEventDateTime.start_date
							);
							newEventDateTime.end_date = getTimestamp(
								newEventDateTime.end_date
							);

							// Use dateManagerInstance to save the changes if available
							if (dateManagerInstance && dateManagerInstance.upsertDate) {
								dateManagerInstance.upsertDate(newEventDateTime);
							}

							// Update the current eventDateTime state
							setCurrentEventDateTime(newEventDateTime);
						}}
					/>
				</BaseControl>
				<BaseControl>
					<ToggleControl
						label={__('Hide from calendar', 'simple-events')}
						className='se-hide-from-calendar-toggle'
						checked={currentEventDateTime.hide_from_calendar}
						onChange={() => {
							const newEventDateTime = clone(currentEventDateTime);
							newEventDateTime.hide_from_calendar = !currentEventDateTime.hide_from_calendar;

							// Use dateManagerInstance to save the changes if available
							if (dateManagerInstance && dateManagerInstance.upsertDate) {
								dateManagerInstance.upsertDate(newEventDateTime);
							}

							// Update the current eventDateTime state
							setCurrentEventDateTime(newEventDateTime);
						}}
					/>
				</BaseControl>
				<BaseControl>
					<ToggleControl
						label={__('Hide from feed', 'simple-events')}
						className='se-hide-from-feed-toggle'
						checked={currentEventDateTime.hide_from_feed}
						onChange={() => {
							const newEventDateTime = clone(currentEventDateTime);
							newEventDateTime.hide_from_feed = !currentEventDateTime.hide_from_feed;

							// Use dateManagerInstance to save the changes if available
							if (dateManagerInstance && dateManagerInstance.upsertDate) {
								dateManagerInstance.upsertDate(newEventDateTime);
							}

							// Update the current eventDateTime state
							setCurrentEventDateTime(newEventDateTime);
						}}
					/>
				</BaseControl>
			</div>
		</div>
	);
};

export default DateTimeGroupNew;
