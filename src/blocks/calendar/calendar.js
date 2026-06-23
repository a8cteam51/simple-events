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
	 * @return {boolean}
	 */
	isMobile() {
		if ( window.matchMedia ) {
			return window.matchMedia( '(max-width: 767px)' ).matches;
		}

		return window.innerWidth <= 767;
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
					if ( ! this.isMobile() ) {
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
	 * Show loading skeleton and hide current calendar content.
	 *
	 * @param {Element} calendarItem Calendar container element.
	 */
	showLoading( calendarItem ) {
		const skeleton = calendarItem.querySelector(
			'[data-js="simple-events-calendar-skeleton"]'
		);
		const content = calendarItem.querySelector(
			'[data-js="simple-events-calendar-content"]'
		);

		// Remember the outgoing calendar grid height so the skeleton grid
		// fills the same space, avoiding a jarring jump when navigating months.
		if ( skeleton && content ) {
			const month = content.querySelector(
				'.simple-events-calendar-month'
			);
			const skeletonBody = skeleton.querySelector(
				'.simple-events-calendar-skeleton__body'
			);

			if ( month && skeletonBody ) {
				const gridHeight = month.offsetHeight;

				if ( gridHeight ) {
					skeletonBody.style.minHeight = `${ gridHeight }px`;
				}
			}
		}

		if ( skeleton ) {
			skeleton.classList.remove(
				'simple-events-calendar-skeleton--hidden'
			);
		}

		if ( content ) {
			content.classList.add( 'simple-events-calendar-content--hidden' );
		}
	}

	/**
	 * Update the visible calendar markup after request completes.
	 *
	 * @param {Element} calendarItem Calendar container element.
	 * @param {string}  html         Updated calendar main HTML.
	 */
	updateContent( calendarItem, html ) {
		const content = calendarItem.querySelector(
			'[data-js="simple-events-calendar-content"]'
		);

		if ( content ) {
			content.innerHTML = html;
			return;
		}

		calendarItem.innerHTML = html;
	}

	/**
	 * Hide loading skeleton and re-show calendar content.
	 *
	 * @param {Element} calendarItem Calendar container element.
	 */
	hideLoading( calendarItem ) {
		const skeleton = calendarItem.querySelector(
			'[data-js="simple-events-calendar-skeleton"]'
		);
		const content = calendarItem.querySelector(
			'[data-js="simple-events-calendar-content"]'
		);

		if ( skeleton ) {
			skeleton.classList.add( 'simple-events-calendar-skeleton--hidden' );
			// Release the remembered height so the next load measures fresh.
			const skeletonBody = skeleton.querySelector(
				'.simple-events-calendar-skeleton__body'
			);

			if ( skeletonBody ) {
				skeletonBody.style.minHeight = '';
			}
		}

		if ( content ) {
			content.classList.remove( 'simple-events-calendar-content--hidden' );
		}
	}

	/**
	 * Send calendar API request
	 *
	 * @param date
	 * @param calendarItem
	 */
	sendCalendarRequest( date, calendarItem ) {
		this.showLoading( calendarItem );

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
				this.updateContent( calendarItem, result.html );
			} else {
				console.log( result );
			}
		} )
			.catch( () => {
				// Keep the existing calendar content if request fails.
			} )
			.finally( () => {
				this.hideLoading( calendarItem );
				this.initListeners( calendarItem );
			} );
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
