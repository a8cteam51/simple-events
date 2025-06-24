import { sortBy } from 'lodash';
import { getStartAndEndDate } from './dates';

/**
 * Creates a meta manager for handling event meta field updates
 *
 * @param {Object} meta Current meta object
 * @param {Function} setMeta Meta setter function
 * @returns {Object} Meta management functions
 */
export const metaManager = (meta, setMeta) => {

    /**
     * Updates the event dates array and recalculates start/end dates
     *
     * @param {Array} dates Array of date objects
     * @param {Object} options Optional parameters
     * @param {string} options.timezone Optional timezone to include in update
     * @returns {void}
     */
    const updateDates = (dates, options = {}) => {
        const sortedDates = sortBy(dates, 'datetime_start');
        const { datetime_start, datetime_end } = getStartAndEndDate(sortedDates);

        console.log('sortedDates', sortedDates);
        console.log('datetime_start', datetime_start);
        console.log('datetime_end', datetime_end);

        const updates = {
            ...meta,
            se_event_dates: sortedDates,
            se_event_date_start: datetime_start,
            se_event_date_end: datetime_end,
        };

        // Optionally include timezone update
        if (options.timezone !== undefined) {
            updates.se_event_timezone = options.timezone;
        }

        setMeta(updates);
    };

    /**
     * Updates a single meta field
     *
     * @param {string} field Meta field name
     * @param {*} value New value
     * @returns {void}
     */
    const updateField = (field, value) => {
        setMeta({ ...meta, [field]: value });
    };

    /**
     * Updates multiple meta fields at once
     *
     * @param {Object} updates Object with field/value pairs
     * @returns {void}
     */
    const updateFields = (updates) => {
        setMeta({ ...meta, ...updates });
    };

    return {
        updateDates,
        updateField,
        updateFields,
    };
};
