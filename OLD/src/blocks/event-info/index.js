/* global lodash, ajaxurl */
/**
 * BLOCK: Events Info
 *
 * Event date and location management.
 */

import './editor.scss';

import moment from 'moment';
import { clone, isEqual, sortBy, head, last, pull } from 'lodash';
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { Fragment } from '@wordpress/element';
import {
	PanelRow,
	Placeholder,
	BaseControl,
	Dropdown,
	Button,
	TextControl,
	Toolbar,
	Disabled,
	CheckboxControl,
	ComboboxControl,
	DateTimePicker,
	PanelBody,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { getSettings } from '@wordpress/date';
import {
	BlockControls,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import { withState } from '@wordpress/compose';
import { useEntityProp } from '@wordpress/core-data';

/**
 * Constants
 */
const DEFAULT_START_HOUR = 9;
const DEFAULT_END_HOUR = 10;
const DATE_SETTINGS = getSettings(); // eslint-disable-line no-restricted-syntax

const OFFSET = Number(DATE_SETTINGS.timezone.offset);
const TIMEZONE = DATE_SETTINGS.timezone.string;
let TIMEZONE_NAME = TIMEZONE;
if ('' === TIMEZONE) {
	TIMEZONE_NAME = 'UTC' + (OFFSET >= 0 ? '+' : '') + OFFSET;
}
const FORMAT = 'YYYY-MM-DD HH:mm';
const TIMEZONES = moment.tz
	.names()
	.map((tz) => ({ label: tz, value: tz }));

// Add an option to use the site settings.
// (This label is as helpful as we can be since manual offsets have no string.)
TIMEZONES.unshift({
	label: __('Same as site', 'simple-events'),
	value: '',
});

/**
 * Get the start and end date from a collection of dates.
 * Will remove any event that has passed.
 *
 * @param {{all_day: boolean, datetime_start: string, datetime_end: string}[]} dates The dates to check.
 *
 * returns {{ datetime_start: string, datetime_end: string }}
 */
const getStartAndEndDate = (dates) => {
	// iterate over and remove any that has passed.
	const now = moment().utcOffset(OFFSET);
	const filteredDates = dates.filter((date) => {

		const endDate = moment.unix(date.datetime_end).utcOffset(OFFSET);
		return endDate.isAfter(now);
	});

	// If we have no filtered dates, but we had dates, before.
	if (filteredDates.length === 0 && dates.length > 0) {
		// Extract all start dates with the offset.
		const allStartDates = dates.map((date) =>
			moment.unix(date.datetime_start).utcOffset(OFFSET)
		);
		const allEndDates = dates.map((date) =>
			moment.unix(date.datetime_end).utcOffset(OFFSET)
		);

		// Return the latest start date and earliest end date.
		return {
			datetime_start: moment.max(allStartDates).unix().toString(),
			datetime_end: moment.max(allEndDates).unix().toString(),
		}
	}

	let startDate = null;
	let endDate = null;

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
}


/**
 * Register: a Gutenberg Block.
 *
 * Registers a new block provided a unique name and an object defining its
 * behavior. Once registered, the block is made editor as an option to any
 * editor interface where blocks are implemented.
 *
 * @link https://wordpress.org/gutenberg/handbook/block-api/
 * @param {string} name     Block name.
 * @param {Object} settings Block settings.
 * @return {?WPBlock}          The block, if it has been successfully
 *                             registered; otherwise `undefined`.
 */
registerBlockType('simple-events/event-info', {
	/**
	 * The edit function describes the structure of your block in the context of the editor.
	 * This represents what the editor will render when the block is used.
	 *
	 * The "edit" property must be a valid function.
	 *
	 * @link https://wordpress.org/gutenberg/handbook/block-api/block-edit-save/
	 *
	 * @param {Object} props Props.
	 * @return {JSX.Element} JSX Component.
	 */
	edit: (props) => {
		const { attributes, setAttributes } = props;
		const { editMode, showOnFrontEnd } = attributes;

		const [meta, setMeta] = useEntityProp(
			'postType',
			'se-event',
			'meta'
		);


		// Sets the default timezone for calculations.

		let currentTimezone = meta?.se_event_timezone;
		if ('' === currentTimezone) {
			currentTimezone = TIMEZONE;
		}

		const getDstOffset = (timestamp, timezone = null) => {
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
			const untilIndex = timezoneDetails.untils.findIndex(function (
				number
			) {
				return number / 1000 > timestamp;
			});

			return timezoneDetails.offsets[untilIndex] * -1;
		};

		/**
		 * Creates a moment in the site timezone from the provided unix timestamp.
		 *
		 * @param {string}  timestamp Timestamp to convert to a moment.
		 * @param {boolean} formatted Whether to return a human-readable formatted string.
		 * @return {Mixed}             Human readable formatted string if `formatted` is true,
		 *                             moment object otherwise.
		 */
		const getMoment = (timestamp, formatted = false) => {
			const dateTime = moment
				.unix(timestamp)
				.utcOffset(getDstOffset(timestamp));

			if (!formatted) {
				return dateTime;
			}

			return dateTime.format(FORMAT);
		};

		/**
		 * Creates a timestamp from the provided date string.
		 *
		 * @param {string} dateTime Date string to convert to a timestamp.
		 * @return {string}          The timestamp, cast as a string.
		 */
		const getTimestamp = (dateTime) => {
			return String(
				moment(dateTime)
					.utcOffset(
						getDstOffset(moment(dateTime).unix()),
						true
					)
					.utc()
					.unix()
			);
		};

		const onDone = () => {
			setAttributes({ editMode: false });
		};

		const onChangeEventLocation = (value) => {
			setMeta({
				...meta,
				se_event_location: value,
			});
		};

		const maybeUpdateEventDateTime = (oldDate, newDate) => {
			if (!isEqual(oldDate, newDate)) {
				const updatedDates = sortBy(
					meta?.se_event_dates.map((item) =>
						item === oldDate ? newDate : item
					),
					'datetime_start'
				);

				setMeta({
					...meta,
					se_event_dates: updatedDates,
					se_event_date_start: getStartAndEndDate(updatedDates).datetime_start,
					se_event_date_end: getStartAndEndDate(updatedDates).datetime_end,
				});
			}
		};

		const getBlockControls = () => (
			<BlockControls>
				<Toolbar
					controls={[
						{
							icon: 'edit',
							title: __('Edit', 'simple-events'),
							onClick: () =>
								setAttributes({ editMode: !editMode }),
							isActive: editMode,
						},
						{
							icon: showOnFrontEnd ? 'visibility' : 'hidden',
							title: __('Show on Front-End?', 'simple-events'),
							onClick: () => {
								setAttributes({
									showOnFrontEnd: !showOnFrontEnd,
								});
								setMeta({
									se_event_show_on_frontend: !showOnFrontEnd,
								});
							},
							isActive: showOnFrontEnd,
						},
					]}
				/>
			</BlockControls>
		);

		const DateTimeGroup = withState({
			tempEventDate: null,
			tempEventTime: null,
		})(
			({
				eventDateTime,
				removeDate,
				multiDay,
				tempEventDate,
				tempEventTime,
				setState,
			}) => {
				const eventStart = getMoment(
					eventDateTime.datetime_start,
					true
				);
				const eventEnd = getMoment(eventDateTime.datetime_end, true);
				const timeFormat = DATE_SETTINGS.formats.datetime;

				// To know if the current timezone is a 12 hour time with look for an "a" in the time format.
				// We also make sure this a is not escaped by a "/".
				const is12HourTime = /a(?!\\)/i.test(
					timeFormat
						.toLowerCase() // Test only the lower case a
						.replace(/\\\\/g, '') // Replace "//" with empty strings
						.split('')
						.reverse()
						.join('') // Reverse the string and test for "a" not followed by a slash
				);

				/**
				 * Combines a given date and time into a moment object.
				 *
				 * @param {string} date The date to combine.
				 * @param {string} time The time to combine.
				 *
				 * @return {moment} The combined date and time.
				 */
				const combineDateAndTime = (date, time) => {
					const timeMoment = moment(time);
					const dateMoment = moment(date);

					// Set the timeMoment's time to the dateMoment.
					return dateMoment.set({
						hour: timeMoment.get('hour'),
						minute: timeMoment.get('minute'),
					});
				};

				/**
				 * Handle the set date and time button click.
				 *
				 * @param {boolean} isStartChange Whether this is start or end date change.
				 *
				 * @return {void}
				 */
				const setDateTimeHandler = (isStartChange) => {
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
						combineDateAndTime(newDate, newTime)
					);

					const newEventDateTime = clone(eventDateTime);

					if (isStartChange) {
						newEventDateTime.datetime_start = newDateTime;

						// Check if the new start time is after the cuurent end time.
						if (
							parseInt(newEventDateTime.datetime_start) >=
							parseInt(newEventDateTime.datetime_end)
						) {
							// Set the new end time to be 1 hour after the start dateTime.
							newEventDateTime.datetime_end = String(
								parseInt(newEventDateTime.datetime_start) +
								3600
							);
						}
					} else {
						newEventDateTime.datetime_end = newDateTime;

						// Check if the new end time is before the current start time.
						if (
							parseInt(newEventDateTime.datetime_start) >=
							parseInt(newEventDateTime.datetime_end)
						) {
							// Set the new start time to be 1 hour before the end dateTime.
							newEventDateTime.datetime_start = String(
								parseInt(newEventDateTime.datetime_end) - 3600
							);
						}
					}

					// Reset the temp date and time.
					setState({
						tempEventDate: null,
						tempEventTime: null,
					});


					maybeUpdateEventDateTime(eventDateTime, newEventDateTime);
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
					const stateUpdate = isDateChange
						? { tempEventTime: newDateTime }
						: { tempEventDate: newDateTime };
					setState(stateUpdate);
				};

				return (
					<div className="se-datetimegroup-container">
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
											{eventDateTime.all_day
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
									renderContent={() => (
										<Fragment>
											<DateTimePicker
												currentDate={eventStart}
												is12Hour={is12HourTime}
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
													setDateTimeHandler(true)
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
											disabled={eventDateTime.all_day}
										>
											{eventDateTime.all_day
												? '--:--'
												: wp.date.format(
													timeFormat,
													eventEnd
												)}
										</Button>
									)}
									renderContent={() => (
										<Fragment>
											<DateTimePicker
												currentDate={eventEnd}
												is12Hour={is12HourTime}
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
													setDateTimeHandler(false)
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
									checked={eventDateTime.all_day}
									onChange={() => {
										const newEventDateTime =
											clone(eventDateTime);

										newEventDateTime.all_day =
											!eventDateTime.all_day;

										newEventDateTime.datetime_start =
											getMoment(
												newEventDateTime.datetime_start
											);
										newEventDateTime.datetime_end =
											getMoment(
												newEventDateTime.datetime_end
											);

										// If all day event, set time between 00:00 and 23:59.
										if (newEventDateTime.all_day) {
											newEventDateTime.datetime_start.startOf(
												'date'
											);
											newEventDateTime.datetime_end.endOf(
												'date'
											);
										} else {
											newEventDateTime.datetime_start
												.hour(DEFAULT_START_HOUR)
												.minute(0);
											newEventDateTime.datetime_end
												.hour(DEFAULT_END_HOUR)
												.minute(0);
										}

										newEventDateTime.datetime_start =
											getTimestamp(
												newEventDateTime.datetime_start
											);
										newEventDateTime.datetime_end =
											getTimestamp(
												newEventDateTime.datetime_end
											);

										const updatedDates = sortBy(
											meta?.se_event_dates.map((item) =>
												item === eventDateTime
													? newEventDateTime
													: item
											),
											'datetime_start'
										);

										setMeta({
											...meta,
											se_event_dates: updatedDates,
											se_event_date_start: getStartAndEndDate(updatedDates).datetime_start,
											se_event_date_end: getStartAndEndDate(updatedDates).datetime_end,
										});
									}}
								/>
							</BaseControl>
							{multiDay && (
								<div className="se-datetime-control__delete">
									<Button
										isDestructive
										icon="no-alt"
										label={__(
											'Remove date',
											'simple-events'
										)}
										onClick={() =>
											removeDate(eventDateTime)
										}
									/>
								</div>
							)}
						</div>
					</div>
				);
			}
		);

		const EventDateTime = ({ dates }) => {
			const addNewDate = () => {
				const existingDates =
					!dates || 0 === dates.length ? [] : dates;

				// Set default date and time.
				let eventStart = moment().utcOffset(OFFSET);

				eventStart.hour(DEFAULT_START_HOUR);
				eventStart.minute(0);
				eventStart.second(0);

				let eventEnd = eventStart.clone();

				eventEnd.hour(DEFAULT_END_HOUR);

				// Override with existing date if there is one.
				if (existingDates.length) {
					eventStart = getMoment(
						last(existingDates).datetime_start
					);
					eventEnd = getMoment(last(existingDates).datetime_end);
				}

				// Set default date to be +1 day from the last date.
				eventStart.add(1, 'days');
				eventEnd.add(1, 'days');

				const updatedDates = sortBy(
					[
						...existingDates,
						{
							datetime_start: wp.date.date('U', eventStart),
							datetime_end: wp.date.date('U', eventEnd),
							all_day: false,
						},
					],
					'datetime_start'
				);

				setMeta({
					...meta,
					se_event_dates: updatedDates,
					se_event_date_start: getStartAndEndDate(updatedDates).datetime_start,
					se_event_date_end: getStartAndEndDate(updatedDates).datetime_end,
				});
			};

			const removeDate = (date) => {
				if (!dates.length) {
					return;
				}

				const updatedDates = pull(dates, date);

				setMeta({
					...meta,
					se_event_dates: updatedDates,
					se_event_date_start: getStartAndEndDate(updatedDates).datetime_start,
					se_event_date_end: getStartAndEndDate(updatedDates).datetime_end,
				});
			};

			// If no dates, add a date.
			if (!dates || 0 === dates.length) {
				addNewDate();
			}

			const datesOutput = [];

			sortBy(dates, 'datetime_start').forEach((date, index) => {
				datesOutput.push(
					<DateTimeGroup
						key={index}
						eventDateTime={date}
						removeDate={removeDate}
						multiDay={dates.length > 1}
					/>
				);
			});

			return (
				<Fragment>
					<span className="se-datetimegroup-controls-label">
						{__('Date & Time', 'simple-events')}
					</span>
					{datesOutput}
					<div className="se-datetime-addmore">
						<Button isLink onClick={() => addNewDate()}>
							{__('+ Add another date', 'simple-events')}
						</Button>
					</div>
				</Fragment>
			);
		};

		const renderPreview = () => (
			<div {...useBlockProps()}>
				{getBlockControls()}
				<Disabled>
					<ServerSideRender
						block="simple-events/event-info"
						attributes={{
							eventVenue: meta?.se_event_venue,
							eventLocation: meta?.se_event_location,
							eventDates: meta?.se_event_dates,
							eventTimezone: meta?.se_event_timezone,
							externalLink: meta?.se_event_external_link,
							externalLinkLabel: meta?.se_event_external_link_label,
							addCalendarLinks: meta?.se_event_add_calendar_links,
						}}
					/>
				</Disabled>
			</div>
		);

		// Show editMode if no location or date set.
		if (
			meta?.se_event_location.length === 0 &&
			(!meta?.se_event_dates || !meta?.se_event_dates?.length)
		) {
			setAttributes({ editMode: true });
		}

		if (!editMode) {
			return renderPreview();
		}

		return (
			<div {...useBlockProps()}>
				{getBlockControls()}
				<Placeholder
					label={__('Event Information', 'simple-events')}
					icon="calendar"
					isColumnLayout
					className={props.className}
				>
					<EventDateTime dates={meta?.se_event_dates} />
					<TextControl
						className="se-location-label"
						label={__('Venue', 'simple-events')}
						value={meta?.se_event_venue}
						onChange={(value) => setMeta({ ...meta, se_event_venue: value })}
						type="text"
					/>
					<TextControl
						className="se-location-label"
						label={__('Location', 'simple-events')}
						value={meta?.se_event_location}
						onChange={onChangeEventLocation}
						type="text"
					/>
					<TextControl
						className="se-location-label"
						label={__('External Link', 'simple-events')}
						value={meta?.se_event_external_link}
						onChange={(url) =>
							setMeta({ ...meta, se_event_external_link: url })
						}
						type="url"
					/>
					{meta?.se_event_external_link && (
						<>
							<TextControl
								className="se-location-label"
								label={__('External Link Label', 'simple-events')}
								value={meta?.se_event_external_link_label}
								onChange={(value) =>
									setMeta({
										...meta,
										se_event_external_link_label: value,
									})
								}
								type="text"
							/>
							<CheckboxControl
								label={__(
									'Open external link from calendar',
									'simple-events'
								)}
								checked={meta?.se_open_external_link ?? false}
								onChange={(value) => {
									setMeta({
										...meta,
										se_open_external_link: value,
									});
								}}
							/>
						</>

					)}

					<Button
						className="se__button-done"
						variant="primary"
						onClick={onDone}
						text={__('Done', 'simple-events')}
					/>
				</Placeholder>
				<InspectorControls>
					<PanelBody title={__('Settings', 'simple-events')}>
						<PanelRow className="se-site-timezone-label">
							{__('Site TimeZone', 'simple-events')}:{' '}
							<strong>{TIMEZONE_NAME}</strong>{' '}
							<a
								target="_blank"
								href={ajaxurl.replace(
									'admin-ajax.php',
									'options-general.php#timezone_string'
								)}
								rel="noreferrer"
							>
								({__('Change', 'simple-events')})
							</a>
						</PanelRow>
						<ComboboxControl
							className="se-timezone-label"
							label={__('Time Zone', 'simple-events')}
							help={__(
								"Events default to the site's time zone as configured in the WordPress settings. If this event is happening in a different region, manually set the time zone here.",
								'simple-events'
							)}
							value={meta?.se_event_timezone ?? TIMEZONE}
							options={TIMEZONES}
							onChange={(value) => {
								const updatedDates = clone(
									meta?.se_event_dates ?? []
								);

								// Ensure that the value is a string.
								value = !Boolean(value) ? '' : value;
								currentTimezone = value;

								if ('' === value) {
									currentTimezone = TIMEZONE;
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
													meta?.se_event_timezone
												)
											);

										const newOffset =
											'' !== currentTimezone
												? getDstOffset(
													eventDateTime[key],
													currentTimezone
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

								setMeta({
									...meta,
									se_event_dates: updatedDates,
									se_event_date_start: getStartAndEndDate(updatedDates).datetime_start,
									se_event_date_end: getStartAndEndDate(updatedDates).datetime_end,
									se_event_timezone: value,
								});
							}}
						/>
						<ToggleControl
							label={__(
								'Display Time Zone in Front-end',
								'simple-events'
							)}
							checked={meta?.se_event_display_timezone ?? false}
							onChange={(value) =>
								setMeta({
									...meta,
									se_event_display_timezone: value,
								})
							}
						/>
						<ToggleControl
							label={__(
								'Group event dates with matching times',
								'simple-events'
							)}
							checked={meta?.se_event_display_grouped ?? false}
							onChange={(value) =>
								setMeta({
									...meta,
									se_event_display_grouped: value,
								})
							}
						/>
						<ToggleControl
							label={__('Hide Start Time', 'simple-events')}
							help={__(
								'Hides the Start Time on the Front-end'
							)}
							checked={meta?.se_event_hide_start_time ?? false}
							onChange={(value) =>
								setMeta({
									...meta,
									se_event_hide_start_time: value,
								})
							}
						/>
						<ToggleControl
							label={__('Hide End Timer', 'simple-events')}
							help={__('Hides the End Time on the Front-end')}
							checked={meta?.se_event_hide_end_time ?? false}
							onChange={(value) =>
								setMeta({
									...meta,
									se_event_hide_end_time: value,
								})
							}
						/>
						<ToggleControl
							label={__('Show "Add to calendar" links', 'simple-events')}
							help={__('Shows the "Add to calendar" links on the Front-end', 'simple-events')}
							checked={meta?.se_event_add_calendar_links ?? false}
							onChange={(value) =>
								setMeta({
									...meta,
									se_event_add_calendar_links: value,
								})
							}
						/>
						<ToggleControl
							label={__(
								'Open event in new window',
								'simple-events'
							)}
							help={__(
								'Open the event in new window from calender',
								'simple-events'
							)}
							checked={
								meta?.se_event_open_in_new_window ?? false
							}
							onChange={(value) =>
								setMeta({
									...meta,
									se_event_open_in_new_window: value,
								})
							}
						/>
					</PanelBody>
					<PanelBody title={__('Calendar Modal Configuration', 'simple-events')}>
						<ToggleControl
							label={__('Enable Event Modal', 'simple-events')}
							help={__(
								'Enable modal for this event.',
								'simple-events'
							)}
							checked={meta?.se_event_modal_access ?? true}
							onChange={(value) => setMeta({ ...meta, se_event_modal_access: value })}
						/>
						<ToggleControl
							label={__('Show Event Title in Modal', 'simple-events')}
							help={__(
								'Toggle to show/hide the title of the event in the modal.',
								'simple-events'
							)}
							checked={meta?.se_show_modal_title ?? true}
							onChange={(value) => setMeta({ ...meta, se_show_modal_title: value })}
						/>
						<ToggleControl
							label={__('Show Event Excerpt in Modal', 'simple-events')}
							help={__(
								'Toggle to show/hide the excerpt of the event in the modal.',
								'simple-events'
							)}
							checked={meta?.se_show_modal_excerpt ?? true}
							onChange={(value) => setMeta({ ...meta, se_show_modal_excerpt: value })}
						/>
					</PanelBody>
				</InspectorControls>
			</div>
		);
	},

	/**
	 * The save function defines the way in which the different attributes should be combined
	 * into the final markup, which is then serialized by Gutenberg into post_content.
	 *
	 * The "save" property must be specified and must be a valid function.
	 *
	 * @link https://wordpress.org/gutenberg/handbook/block-api/block-edit-save/
	 *
	 * @param {Object} props Props.
	 * @return {Mixed} JSX Frontend HTML.
	 */
	save: () => {
		return null;
	},
});
