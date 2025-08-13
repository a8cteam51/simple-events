/* global lodash, ajaxurl */
/**
 * Event Info Block
 *
 * Gutenberg block for managing event date, time, location, and venue information.
 * Provides an interface for adding multiple event dates with timezone support,
 * venue and location details, external links, and calendar integration options.
 *
 * @package SimpleEvents
 * @since   1.0.0
 */

import './editor.scss';

import moment from 'moment';
import { clone, isEqual, sortBy, head, last, pull } from 'lodash';
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { Fragment, useState, useEffect } from '@wordpress/element';
import {
	PanelRow,
	Placeholder,

	Button,
	TextControl,
	Toolbar,
	Disabled,
	CheckboxControl,
	ComboboxControl,
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
import { useEntityProp } from '@wordpress/core-data';
import { useSelect, useDispatch } from '@wordpress/data';

// Import date utilities
import {

	TIMEZONE,
	TIMEZONE_NAME,
	TIMEZONES,

} from './date-utils';

import apiFetch from '@wordpress/api-fetch';

import { dateManager } from './event-manager';

// Import the new DateTimeGroup component as DateTimeGroupNew
import DateTimeGroupNew from './components/DateTimeGroup';

const DATE_SETTINGS = getSettings(); // Still needed for timeFormat

/**
 * Fetches event dates from the custom REST API endpoint.
 *
 * Retrieves all event dates associated with the current post via the
 * Simple Events REST API. Returns an empty array if no post ID is found.
 *
 * @since 2.0.0
 *
 * @return {Promise<Array>} Promise that resolves to an array of event date objects.
 */
export const getEventDatePosts = () => {
	// Get the current post id.
	const postId = window?.wp?.data?.select('core/editor')?.getCurrentPostId();
	if (!postId) {
		// Return an empty array if no post id is found.
		return Promise.resolve([]);
	}

	// simple-events/event-dates/{event}
	return apiFetch({ path: '/simple-events/event-dates/' + postId }).then((posts) => posts
	).catch((error) => {
		console.error('Error fetching event dates:', error);
		return [];
	});

};

/**
 * Saves event dates to the custom REST API endpoint.
 *
 * Sends event dates to the server for persistence via the Simple Events
 * REST API sync endpoint. Displays success/error notifications and optionally
 * refreshes the date manager instance with updated data.
 *
 * @since 2.0.0
 *
 * @param {Array}       dates               Array of event date objects to save.
 * @param {Object|null} dateManagerInstance Optional date manager instance to refresh after save.
 * @return {Promise<Object>} Promise that resolves to the API response object.
 */
export const saveEventDates = (dates, dateManagerInstance = null) => {
	// Get the current post id.
	const postId = window?.wp?.data?.select('core/editor')?.getCurrentPostId();
	if (!postId) {
		return Promise.reject(new Error('No post ID found'));
	}

	// simple-events/event-dates/{event}
	return apiFetch({
		path: '/simple-events/event-dates/' + postId + '/sync',
		method: 'POST',
		data: {
			dates: dates,
			event_id: postId,
			nonce: seSettings.syncDatesNonce
		}
	}).then((response) => {
		// Show notification message if available
		if (response.message) {
			// Show success notification in bottom left
			window.wp.data.dispatch('core/notices').createSuccessNotice(
				response.message,
				{
					type: 'snackbar',
					isDismissible: true,
					id: 'event-dates-saved'
				}
			);
		}

		// Refresh dateManager with new dates if available and dateManager instance is provided
		if (response.dates && dateManagerInstance && dateManagerInstance.refreshWithNewDates) {
			dateManagerInstance.refreshWithNewDates(response.dates);
		}



		return response;
	}).catch((error) => {
		console.error('Error saving event dates:', error);

		// Show error notification
		window.wp.data.dispatch('core/notices').createErrorNotice(
			__('Failed to save event dates. Please try again.', 'simple-events'),
			{
				type: 'snackbar',
				isDismissible: true,
				id: 'event-dates-error'
			}
		);

		throw error;
	});
};

/**
 * Auto-saves event dates when they change.
 *
 * Wrapper function for saveEventDates that handles automatic saving
 * of event dates during user interactions.
 *
 * @since 2.0.0
 *
 * @param {Array}       dates               Array of event date objects to auto-save.
 * @param {Object|null} dateManagerInstance Optional date manager instance to refresh.
 * @return {Promise<Object>} Promise that resolves to the saved event dates response.
 */
export const autoSaveEventDates = async (dates, dateManagerInstance = null) => {
	try {
		// Save to REST API
		const savedDates = await saveEventDates(dates, dateManagerInstance);

		return savedDates;
	} catch (error) {
		console.error('Error auto-saving event dates:', error);
		throw error;
	}
};

/**
 * Saves event dates when the post is being saved.
 *
 * Handles saving event dates during WordPress post save operations.
 * Also updates block attributes to ensure the saved dates with IDs
 * are properly persisted in the block editor.
 *
 * @since 2.0.0
 *
 * @param {Array}       dates               Array of event date objects to save.
 * @param {Object|null} dateManagerInstance Optional date manager instance to refresh.
 * @return {Promise<Object>} Promise that resolves to the saved event dates response.
 */
export const saveEventDatesOnPostSave = async (dates, dateManagerInstance = null) => {
	try {
		// Save to REST API
		const savedDates = await saveEventDates(dates, dateManagerInstance);

		// Update the post meta to ensure the updated dates (with IDs) are persisted
		const postId = window?.wp?.data?.select('core/editor')?.getCurrentPostId();
		if (postId && savedDates) {

			// Update the block attributes to ensure the updated dates (with IDs) are persisted
			const blocks = window.wp.data.select('core/block-editor').getBlocks();
			const eventInfoBlock = blocks.find(block => block.name === 'simple-events/event-info');

			if (eventInfoBlock) {
				window.wp.data.dispatch('core/block-editor').updateBlockAttributes(
					eventInfoBlock.clientId,
					{
						eventDates: savedDates.dates || savedDates,
					}
				);
			}
		}

		return savedDates;
	} catch (error) {
		console.error('Error saving event dates on post save:', error);
		throw error;
	}
};


// Initialize date manager instance outside the component
let dateManagerInstance = null;
let gettingDates = false;

/**
 * Initializes the date manager with resolved event date posts.
 *
 * Creates and configures a date manager instance with event dates fetched
 * from the REST API. Handles post meta synchronization and timezone settings.
 * Uses singleton pattern to avoid multiple initializations.
 *
 * @since 2.0.0
 *
 * @return {Promise<Object|null>} Promise that resolves to the date manager instance or null on error.
 */
const initializeDateManager = async () => {
	if (gettingDates || dateManagerInstance) {
		return dateManagerInstance;
	}

	gettingDates = true;
	try {
		const eventDatePosts = await getEventDatePosts();

		// Get current post meta to pass to dateManager for sync
		const currentPostId = window?.wp?.data?.select('core/editor')?.getCurrentPostId();
		const currentMeta = currentPostId ? window?.wp?.data?.select('core/editor')?.getEditedPostAttribute('meta') : {};
		const currentTimezone = currentMeta?.se_event_timezone || '';

		// Create meta sync object
		const metaSync = {
			meta: currentMeta,
			setMeta: (updates) => {
				window.wp.data.dispatch('core/editor').editPost({
					meta: updates
				});
			}
		};

		dateManagerInstance = dateManager(eventDatePosts, currentTimezone, metaSync);
		return dateManagerInstance;
	} catch (error) {
		console.error('Error initializing date manager:', error);
		return null;
	} finally {
		gettingDates = false;
	}
};

/**
 * Registers the Event Info Gutenberg block.
 *
 * Creates a custom block for event information management including dates,
 * times, locations, venues, and related settings. The block provides both
 * edit and preview modes with extensive customization options.
 *
 * @since 1.0.0
 *
 * @see https://wordpress.org/gutenberg/handbook/block-api/
 */
registerBlockType('simple-events/event-info', {
	/**
	 * Block edit function.
	 *
	 * Defines the structure and behavior of the Event Info block in the editor.
	 * Manages event dates through a date manager instance, handles post meta
	 * synchronization, and provides interfaces for editing event information
	 * including dates, times, locations, venues, and display options.
	 *
	 * @since 1.0.0
	 *
	 * @param {Object} props               Block properties.
	 * @param {Object} props.attributes    Block attributes including editMode and showOnFrontEnd.
	 * @param {Function} props.setAttributes Function to update block attributes.
	 * @return {JSX.Element} The block editor interface.
	 */
	edit: (props) => {
		const { attributes, setAttributes } = props;
		const { editMode, showOnFrontEnd } = attributes;

		const [meta, setMeta] = useEntityProp(
			'postType',
			'se-event',
			'meta'
		);

		// Add state for loading indication
		const [isGettingDates, setIsGettingDates] = useState(false);
		const [dateManagerReady, setDateManagerReady] = useState(false);
		const [dateManagerState, setDateManagerState] = useState(null);
		// Add refresh counter to force re-renders when dateManager state changes
		const [refreshCounter, setRefreshCounter] = useState(0);

		// Watch for post save events
		const { isSavingPost, isAutosavingPost } = useSelect((select) => {
			const { isSavingPost, isAutosavingPost } = select('core/editor');
			return {
				isSavingPost: isSavingPost(),
				isAutosavingPost: isAutosavingPost(),
			};
		}, []);

		// Save dates before post save begins
		useEffect(() => {
			let wasSaving = false;
			let dateSavePromise = null;

			const unsubscribe = window.wp.data.subscribe(() => {
				const { isSavingPost, isAutosavingPost } = window.wp.data.select('core/editor');
				const currentSaving = isSavingPost();
				const currentAutosaving = isAutosavingPost();

				// Detect when save is about to start (transition from false to true)
				if (currentSaving && !currentAutosaving && !wasSaving && dateManagerState?.getCurrentDates()?.dates) {
					// Save dates immediately before post save continues
					dateSavePromise = saveEventDatesOnPostSave(dateManagerState.getCurrentDates().dates, dateManagerState)
						.then((savedDates) => {
							if (savedDates && savedDates.dates) {
								setAttributes({
									eventDates: savedDates.dates
								});
							}
							dateSavePromise = null;
						})
						.catch((error) => {
							console.error('Failed to save event dates before post save:', error);
							dateSavePromise = null;
						});
				}

				// Detect when save finishes but date sync is still in progress
				if (!currentSaving && wasSaving && dateSavePromise) {
					// Wait for date sync to complete, then save again
					dateSavePromise.then(() => {
						// Trigger another post save to include the updated dates
						window.wp.data.dispatch('core/editor').savePost();
					});
				}

				wasSaving = currentSaving;
			});

			return () => unsubscribe();
		}, [dateManagerState, setAttributes]);

		// Sync dateManagerState dates to block attributes
		useEffect(() => {
			if (dateManagerState?.getCurrentDates()?.dates) {
				// Get current block attributes
				const currentEventDates = attributes.eventDates || [];
				const newEventDates = dateManagerState.getCurrentDates().dates;

				// Compare the dates to see if they're actually different
				const datesChanged = JSON.stringify(currentEventDates) !== JSON.stringify(newEventDates);

				if (datesChanged) {
					setAttributes({
						eventDates: newEventDates
					});
				}
			}
		}, [dateManagerState, refreshCounter, setAttributes, attributes.eventDates]);

		// Check if we should be in edit mode based on missing data
		useEffect(() => {
			// Only check after dateManager is ready to avoid premature decisions
			if (dateManagerReady && dateManagerState) {
				const hasDates = dateManagerState?.getCurrentDates()?.dates &&
					dateManagerState.getCurrentDates().dates.length > 0;

				// Enter edit mode if we don't have either location/venue AND dates
				const shouldBeInEditMode =  !hasDates;

				if (shouldBeInEditMode && !editMode) {
					setAttributes({ editMode: true });
				}
			}
		}, [dateManagerReady, dateManagerState, meta?.se_event_location, meta?.se_event_venue, editMode, setAttributes]);

		// Initialize date manager on component mount
		useEffect(() => {
			const initManager = async () => {
				setIsGettingDates(true);
				try {
					const manager = await initializeDateManager();
					setDateManagerReady(true);
					setDateManagerState(manager);
				} catch (error) {
					console.error('❌ Failed to initialize date manager:', error);
				} finally {
					setIsGettingDates(false);
				}
			};

			if (!dateManagerReady && !isGettingDates) {
				initManager();
			}
		}, [dateManagerReady, isGettingDates]);

		/**
		 * Handles the Done button click event.
		 *
		 * Exits edit mode by setting the editMode attribute to false.
		 *
		 * @since 1.0.0
		 */
		const onDone = () => {
			setAttributes({ editMode: false });

			// Update the date attriutes from dateManagerState
			if (dateManagerState?.getCurrentDates()?.dates) {
				setAttributes({
					eventDates: dateManagerState.getCurrentDates().dates,
				});
			}
		};

		/**
		 * Handles event location input changes.
		 *
		 * Updates the se_event_location meta field when the location input changes.
		 *
		 * @since 1.0.0
		 *
		 * @param {string} value The new location value.
		 */
		const onChangeEventLocation = (value) => {
			setMeta({
				...meta,
				se_event_location: value,
			});
		};

		/**
		 * Renders the block toolbar controls.
		 *
		 * Creates the edit and visibility toggle buttons that appear in the
		 * block toolbar when the block is selected.
		 *
		 * @since 1.0.0
		 *
		 * @return {JSX.Element} The BlockControls component with toolbar buttons.
		 */
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

		// Wrapper functions to trigger re-renders when dateManager state changes
		/**
		 * Handles adding a new date to the event.
		 *
		 * Calls the date manager's addDate method and triggers a re-render
		 * by incrementing the refresh counter.
		 *
		 * @since 2.0.0
		 */
		const handleAddDate = () => {
			if (dateManagerState?.addDate) {
				dateManagerState.addDate();
				setRefreshCounter(prev => prev + 1);
			}
		};

		/**
		 * Handles reverting date changes.
		 *
		 * Calls the date manager's revertDates method and triggers a re-render
		 * by incrementing the refresh counter.
		 *
		 * @since 2.0.0
		 */
		const handleRevertDates = () => {
			if (dateManagerState?.revertDates) {
				dateManagerState.revertDates();
				setRefreshCounter(prev => prev + 1);
			}
		};

		// Create enhanced dateManagerInstance that triggers re-renders
		const enhancedDateManagerInstance = dateManagerState ? {
			...dateManagerState,
			upsertDate: (date) => {
				const result = dateManagerState.upsertDate(date);
				setRefreshCounter(prev => prev + 1);
				return result;
			},
			removeDate: (date) => {
				const result = dateManagerState.removeDate(date);
				setRefreshCounter(prev => prev + 1);
				return result;
			},
			addDate: () => {
				const result = dateManagerState.addDate();
				setRefreshCounter(prev => prev + 1);
				return result;
			},
			revertDates: () => {
				const result = dateManagerState.revertDates();
				setRefreshCounter(prev => prev + 1);
				return result;
			},
			refreshWithNewDates: (newDates) => {
				if (dateManagerState.refreshWithNewDates) {
					dateManagerState.refreshWithNewDates(newDates);
					setRefreshCounter(prev => prev + 1);
				}
			},
			updateTimezone: (newTimezone) => {
				const result = dateManagerState.updateTimezone(newTimezone);
				setRefreshCounter(prev => prev + 1);
				return result;
			}
		} : null;

		/**
		 * Renders the unsaved changes warning component.
		 *
		 * Displays a warning message when the date manager has unsaved changes,
		 * informing the user that they need to save the post to persist changes.
		 *
		 * @since 2.0.0
		 *
		 * @return {JSX.Element|null} Warning component or null if no unsaved changes.
		 */
		const UnsavedChangesWarning = () => {
			const isDirty = dateManagerState?.getCurrentDates()?.isDirty;

			if (!isDirty) {
				return null;
			}

			return (
				<div className="se-unsaved-changes-message" style={{
					background: '#fff3cd',
					border: '1px solid #ffeaa7',
					borderRadius: '4px',
					padding: '12px 16px',
					margin: '0 0 20px 0',
					display: 'flex',
					alignItems: 'center',
					gap: '8px',
					color: '#856404',
					width: 'fit-content'
				}}>
					<span className="dashicons dashicons-warning" style={{
						fontSize: '16px',
						color: '#f39c12'
					}}></span>
					<div>
						<strong>{__('Unsaved Changes', 'simple-events')}</strong>
						<br />
						<span style={{ fontSize: '13px' }}>
							{__('You have unsaved date and timezone changes. Save the post to persist these changes.', 'simple-events')}
						</span>
					</div>
				</div>
			);
		};

		/**
		 * Renders the event date/time component.
		 *
		 * Creates a list of DateTimeGroupNew components for each event date,
		 * sorted by start date. Each component allows editing of individual
		 * date and time information.
		 *
		 * @since 2.0.0
		 *
		 * @param {Object} props              Component properties.
		 * @param {Array}  props.dates        Array of event date objects to render.
		 * @param {number} props.refreshCounter Counter to force component re-renders.
		 * @return {JSX.Element} Fragment containing the event dates interface.
		 */
		const EventDateTime = ({ dates, refreshCounter }) => {

			const datesOutput = [];

			sortBy(dates, 'start_date').forEach((date, index) => {
				datesOutput.push(
					<DateTimeGroupNew
						key={`${index}-${refreshCounter}`}
						eventDateTime={date}
						removeDate={null}
						hasMultipleDates={dates.length > 1}
						currentTimezone={dateManagerState?.getCurrentDates()?.timezone ?? meta?.se_event_timezone ?? TIMEZONE}
						dateManagerInstance={enhancedDateManagerInstance}
					/>
				);
			});

			return (
				<Fragment>
					<span className="se-datetimegroup-controls-label">
						{__('Event Dates (New)', 'simple-events')}
					</span>
					{datesOutput}

				</Fragment>
			);
		};

		/**
		 * Renders the block preview mode.
		 *
		 * Displays the front-end representation of the block using ServerSideRender
		 * with the current event data. Includes block controls and unsaved changes warning.
		 *
		 * @since 1.0.0
		 *
		 * @return {JSX.Element} The preview component with ServerSideRender.
		 */
		const renderPreview = () => {
			const attributes = {
				eventVenue: meta?.se_event_venue,
				eventLocation: meta?.se_event_location,
				eventDates: dateManagerState?.getCurrentDates()?.dates,
				eventTimezone: dateManagerState?.getCurrentDates()?.timezone ?? meta?.se_event_timezone,
				externalLink: meta?.se_event_external_link,
				externalLinkLabel: meta?.se_event_external_link_label,
				addCalendarLinks: meta?.se_event_add_calendar_links,
			};

			return (
				<div {...useBlockProps()}>
					{getBlockControls()}
					<UnsavedChangesWarning />
					<Disabled>
						<ServerSideRender
							block="simple-events/event-info"
							additionalQueryArgs={{
								context: 'edit'
							}}
							attributes={attributes}
						/>
					</Disabled>
				</div>
			);
		};

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
					<UnsavedChangesWarning />

					<EventDateTime
						dates={dateManagerState?.getCurrentDates()?.dates}
						refreshCounter={refreshCounter}
					/>
					{/* Button container with 50/50 layout */}
					<div style={{
						display: 'flex',
						gap: '12px',
						width: '100%',
						marginBottom: '16px'
					}}>
						<Button
							className="se-add-date-button"
							variant="primary"
							onClick={handleAddDate}
							text={__('Add Date', 'simple-events')}
							style={{ flex: 1 }}
						/>
						<Button
							className="se-revert-changes-button"
							variant="secondary"
							onClick={handleRevertDates}
							disabled={!dateManagerState?.getCurrentDates()?.isDirty}
							text={__('Revert Changes', 'simple-events')}
							style={{ flex: 1 }}
						/>
					</div>
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
							value={dateManagerState?.getCurrentDates()?.timezone ?? meta?.se_event_timezone ?? TIMEZONE}
							options={TIMEZONES}
							onChange={(value) => {
								if (enhancedDateManagerInstance?.updateTimezone) {
									enhancedDateManagerInstance.updateTimezone(value);
									setRefreshCounter(prev => prev + 1);
								}
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
	 * Block save function.
	 *
	 * Defines how the block content is saved to the database. This block
	 * uses dynamic rendering (ServerSideRender), so the save function
	 * returns null and all content is generated server-side via PHP.
	 *
	 * @since 1.0.0
	 *
	 * @return {null} Returns null as this is a dynamic block.
	 */
	save: () => {
		return null;
	},
});
