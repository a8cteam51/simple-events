/* global attributes */
import apiFetch from '@wordpress/api-fetch';

export default class Calendar {
	constructor() {
		this.DOM = {
			calendars: '.simple-events-calendar',
			desktopElements: '.simple-events-hidden-mobile',
			navigationItems: 'simple-events-navigation-item',
			calendarDay: 'simple-events-calendar-day',
			mobileEventContainer: 'simple-events-calendar-month-mobile-events',
			status: {
				dayActive: 'simple-events-calendar-month__day--active',
				mobileDayActive: 'simple-events-calendar-month-mobile-events__mobile-day--active',
			},
			calendarModal: '.se-event-modal',
			calendarModalContainer: '.simple-events-calendar-month__day'
		};

		this.calendars = document.querySelectorAll( this.DOM.calendars );
	}

	/**
	 * Init Class
	 */
	init() {
		if ( this.calendars.length ) {
			this.calendars.forEach( ( calendarItem ) => {
				this.initListeners( calendarItem );
			} );
		}
	}

	/**
	 * Init listeners
	 *
	 * @param calendarItem
	 */
	initListeners( calendarItem ) {
		this.addNavigationItemsListeners( calendarItem );
		this.addCalendarDayListeners( calendarItem );
		this.handleModalFunctionality();
	}

	/**
	 * Check if mobile view
	 *
	 * @param  calendarItem
	 * @return {boolean}
	 */
	isMobile( calendarItem ) {
		const mobileElements = calendarItem.querySelectorAll( this.DOM.desktopElements );
		if ( mobileElements.length ) {
			if ( 'none' === window.getComputedStyle( mobileElements[ 0 ], null ).display ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add Calendar day listeners
	 *
	 * @param calendarItem
	 */
	addCalendarDayListeners( calendarItem ) {
		const calendarDays = calendarItem.querySelectorAll( `[data-js="${ this.DOM.calendarDay }"]` );

		if ( calendarDays.length ) {
			calendarDays.forEach( ( item ) => {
				item.addEventListener( 'click', ( event ) => {
					if ( ! this.isMobile( calendarItem ) ) {
						return;
					}

					event.preventDefault();
					let isActive = false;
					const mobileDaysContainer = calendarItem.querySelector( `[data-js="${ this.DOM.mobileEventContainer }"]` );

					if ( event.currentTarget.classList.contains( this.DOM.status.dayActive ) ) {
						isActive = true;
					}

					if ( mobileDaysContainer ) {
						const mobileDay = mobileDaysContainer.querySelector( '#' + event.currentTarget.dataset.mobileControl );
						const activeMobileDays = mobileDaysContainer.querySelectorAll( '.' + this.DOM.status.mobileDayActive );
						const activeDays = calendarItem.querySelectorAll( '.' + this.DOM.status.dayActive );

						if ( mobileDay ) {
							if ( activeMobileDays.length ) {
								activeMobileDays.forEach( ( item ) => {
									item.classList.remove( this.DOM.status.mobileDayActive );
								} );
							}
							if ( activeDays.length ) {
								activeDays.forEach( ( item ) => {
									item.classList.remove( this.DOM.status.dayActive );
								} );
							}

							if ( ! isActive ) {
								event.currentTarget.classList.add( this.DOM.status.dayActive );
								mobileDay.classList.add( this.DOM.status.mobileDayActive );
							}
						}
					}
				} );
			} );
		}
	}

	/**
	 * Add navigation items listeners
	 *
	 * @param calendarItem
	 */
	addNavigationItemsListeners( calendarItem ) {
		const navigation = calendarItem.querySelectorAll( `[data-js="${ this.DOM.navigationItems }"]` );

		if ( navigation.length ) {
			navigation.forEach( ( item ) => {
				item.addEventListener( 'click', ( event ) => {
					event.preventDefault();

					if ( event.currentTarget.classList.contains( 'disabled' ) ) {
						return;
					}

					const date = event.currentTarget.closest( `[data-js="${ this.DOM.navigationItems }"]` ).dataset.date;
					this.sendCalendarRequest( date, calendarItem );
				} );
			} );
		}
	}

	/**
	 * Send calendar API request
	 *
	 * @param date
	 * @param calendarItem
	 */
	sendCalendarRequest( date, calendarItem ) {
		/**
		 * Convert GET request to POST
		 * Implemented to send block attributes in body instead of URL.
		 */
		apiFetch( {
			path: '/simple-events/calendar',
			method: 'POST',
			data: {
				date,
				attributes,
			},
		} ).then( ( result ) => {
			if ( result.html ) {
				calendarItem.innerHTML = result.html;
				this.initListeners( calendarItem );
			} else {
				console.log( result );
			}
		});
	}

	/**
	 * Sets a timeout to hide the modal after 500 milliseconds.
	 *
	 * @param {Element} modal - The modal element to hide.
	 * @return {number} The ID of the timeout that can be used to clear it.
	 */
	handleHideTimeout( modal ) {
		return setTimeout( () => {
			modal.classList.add( 'hidden' );
		}, 150 );
	}

	/**
	 * Handles the modal functionality for the calendar.
	 *
	 * @return {void}
	 */
	handleModalFunctionality() {
		// Target all modal containers.
		const modalContainer = document.querySelectorAll( this.DOM.calendarModalContainer );
		modalContainer.forEach( ( element, idx ) => {
			let modal = null;
			// Target event titles.
			const titles = element.querySelectorAll( '.simple-events-calendar-month__calendar-event-title' );

			if ( ! titles || ! titles.length ) {
				return;
			}

			let hideTimeout = null;

			titles.forEach( ( title ) => {
				// On hovering an event's title, show its corresponding modal.
				title.addEventListener( 'mouseenter', ( e ) => {
					const article = e.currentTarget.closest( 'article' );
					modal = article.nextElementSibling;

					if ( ! modal ) {
						return;
					}

					// Keep the modal open when hovered, remove timeout.
					modal.addEventListener( 'mouseenter', () => {
						clearTimeout( hideTimeout );
					} );

					// Hide the modal after cursor leaves the modal.
					modal.addEventListener( 'mouseleave', () => {
						hideTimeout = this.handleHideTimeout( modal );
					} );

					modal.classList.remove( 'hidden' );

					// Position of the event in the calendar.
					const position = ( idx + 1 ) % 7;

					// Set the modal's position based on its position in the calendar.
					if ( position !== 0 && position < 4 ) {
						modal.style.left = '80px';
					} else {
						modal.style.right = '80px';
					}
					if ( ( idx + 1 ) > 22 ) {
						modal.style.top = '-220px';
					}
				} );

				title.addEventListener( 'mouseleave', () => {

					if ( ! modal ) {
						return;
					}

					// Hide the modal on leaving the title.
					hideTimeout = this.handleHideTimeout( modal );
				} );
			} );

		} );
	}
}
