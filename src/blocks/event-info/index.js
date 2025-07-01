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
 * Get the event dates from the custom rest API endpoint.
 *
 * @returns {Promise<Array>} A promise that resolves to an array of event dates.
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
 * Save the event dates to the custom rest API endpoint.
 *
 * @param {Array} dates - The array of event dates to save.
 * @param {Object} dateManagerInstance - The date manager instance to refresh.
 * @returns {Promise<Array>} A promise that resolves to the saved event dates.
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
		console.log('Event dates saved successfully:', response);

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
 * Auto-save event dates when they change.
 *
 * @param {Array} dates - The array of event dates to auto-save.
 * @param {Object} dateManagerInstance - The date manager instance to refresh.
 * @returns {Promise<Array>} A promise that resolves to the saved event dates.
 */
export const autoSaveEventDates = async (dates, dateManagerInstance = null) => {
	try {
		// Save to REST API
		const savedDates = await saveEventDates(dates, dateManagerInstance);

		// Also update the post meta to keep it in sync
		const postId = window?.wp?.data?.select('core/editor')?.getCurrentPostId();
		if (postId) {
			window.wp.data.dispatch('core/editor').editPost({
				meta: {
					se_event_dates: savedDates.dates || savedDates,
				},
			});
		}

		return savedDates;
	} catch (error) {
		console.error('Error auto-saving event dates:', error);
		throw error;
	}
};

/**
 * Save event dates when the post is being saved.
 *
 * @param {Array} dates - The array of event dates to save.
 * @param {Object} dateManagerInstance - The date manager instance to refresh.
 * @returns {Promise<Array>} A promise that resolves to the saved event dates.
 */
export const saveEventDatesOnPostSave = async (dates, dateManagerInstance = null) => {
	try {
		// Save to REST API
		const savedDates = await saveEventDates(dates, dateManagerInstance);
		console.log('Event dates saved on post save:', savedDates);

		// Update the post meta to ensure the updated dates (with IDs) are persisted
		const postId = window?.wp?.data?.select('core/editor')?.getCurrentPostId();
		if (postId && savedDates) {
			window.wp.data.dispatch('core/editor').editPost({
				meta: {
					se_event_dates: [],
				},
			});
			console.log('Post meta updated with saved dates:', savedDates.dates || savedDates);

			// Also update the block attributes to ensure the updated dates (with IDs) are persisted
			const blocks = window.wp.data.select('core/block-editor').getBlocks();
			const eventInfoBlock = blocks.find(block => block.name === 'simple-events/event-info');

			if (eventInfoBlock) {
				window.wp.data.dispatch('core/block-editor').updateBlockAttributes(
					eventInfoBlock.clientId,
					{
						eventDates: savedDates.dates || savedDates,
					}
				);
				console.log('Block attributes updated with saved dates:', savedDates.dates || savedDates);
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
 * Initialize the date manager with resolved event date posts
 */
const initializeDateManager = async () => {
	if (gettingDates || dateManagerInstance) {
		return dateManagerInstance;
	}

	gettingDates = true;
	try {
		const eventDatePosts = await getEventDatePosts();
		console.log('eventDatePosts from getEventDatePosts:', eventDatePosts);
		console.log('dateManager function:', dateManager);

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
		console.log('dateManagerInstance after creation:', dateManagerInstance);
		return dateManagerInstance;
	} catch (error) {
		console.error('Error initializing date manager:', error);
		return null;
	} finally {
		gettingDates = false;
	}
};

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

		// Trigger date save when post is being saved
		useEffect(() => {
			const saveDatesOnPostSave = async () => {
				if (isSavingPost && !isAutosavingPost && dateManagerState?.getCurrentDates()?.dates) {
					console.log('Post is being saved, saving event dates...');
					try {
						await saveEventDatesOnPostSave(dateManagerState.getCurrentDates().dates, dateManagerState);
						console.log('Event dates saved successfully on post save');
					} catch (error) {
						console.error('Failed to save event dates on post save:', error);
					}
				}
			};

			saveDatesOnPostSave();
		}, [isSavingPost, isAutosavingPost, dateManagerState]);

		// Sync dateManagerState dates to block attributes
		useEffect(() => {
			if (dateManagerState?.getCurrentDates()?.dates) {
				setAttributes({
					eventDates: dateManagerState.getCurrentDates().dates
				});
			}
		}, [dateManagerState, refreshCounter, setAttributes]);

		// Check if we should be in edit mode based on missing data
		useEffect(() => {
			// Only check after dateManager is ready to avoid premature decisions
			if (dateManagerReady && dateManagerState) {
				const hasLocation = meta?.se_event_location && meta.se_event_location.length > 0;
				const hasVenue = meta?.se_event_venue && meta.se_event_venue.length > 0;
				const hasDates = dateManagerState?.getCurrentDates()?.dates &&
					dateManagerState.getCurrentDates().dates.length > 0;

				// Enter edit mode if we don't have either location/venue AND dates
				const shouldBeInEditMode = (!hasLocation && !hasVenue) || !hasDates;

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
					console.error('Failed to initialize date manager:', error);
				} finally {
					setIsGettingDates(false);
				}
			};

			if (!dateManagerReady && !isGettingDates) {
				initManager();
			}
		}, [dateManagerReady, isGettingDates]);

		const onDone = () => {
			setAttributes({ editMode: false });
		};

		const onChangeEventLocation = (value) => {
			setMeta({
				...meta,
				se_event_location: value,
			});
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

		// Wrapper functions to trigger re-renders when dateManager state changes
		const handleAddDate = () => {
			if (dateManagerState?.addDate) {
				dateManagerState.addDate();
				setRefreshCounter(prev => prev + 1);
			}
		};

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

		// Unsaved Changes Warning Component
		const UnsavedChangesWarning = () => {
			if (!dateManagerState?.getCurrentDates()?.isDirty) {
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

		const EventDateTime = ({ dates, refreshCounter }) => {
			console.log('dates ', dates);

			const datesOutput = [];

			sortBy(dates, 'start_date').forEach((date, index) => {
				datesOutput.push(
					<DateTimeGroupNew
						key={`${index}-${refreshCounter}`}
						eventDateTime={date}
						removeDate={null}
						hasMultipleDates={dates.length > 1}
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

		const renderPreview = () => (
			<div {...useBlockProps()}>
				{getBlockControls()}
				<UnsavedChangesWarning />
				<Disabled>
					<ServerSideRender
						block="simple-events/event-info"
						attributes={{
							eventVenue: meta?.se_event_venue,
							eventLocation: meta?.se_event_location,
							eventDates: dateManagerState?.getCurrentDates()?.dates,
							eventTimezone: dateManagerState?.getCurrentDates()?.timezone ?? meta?.se_event_timezone,
							externalLink: meta?.se_event_external_link,
							externalLinkLabel: meta?.se_event_external_link_label,
							addCalendarLinks: meta?.se_event_add_calendar_links,
						}}
					/>
				</Disabled>
			</div>
		);

		if (!editMode) {
			return renderPreview();
		}

		console.log('dateManagerState', dateManagerState);

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
