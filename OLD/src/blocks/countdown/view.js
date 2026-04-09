/* eslint-disable no-bitwise */
function simpleEventsCountdownTimer() {
	const eventTimerElement = document.getElementById( 'event-timer' );

	if ( ! eventTimerElement || ! eventTimerElement.dataset.eventStartDate ) {
		if ( eventTimerElement ) {
			eventTimerElement.remove();
		}

		return;
	}

	const startDate = +eventTimerElement.dataset.eventStartDate;
	let diff, days, hours, minutes, seconds;
	const interval = setInterval( eventsTimer, 1000 );
	const daysElement = eventTimerElement.querySelector(
		'.event-timer__time-days'
	);
	const hoursElement = eventTimerElement.querySelector(
		'.event-timer__time-hours'
	);
	const minutesElement = eventTimerElement.querySelector(
		'.event-timer__time-minutes'
	);
	const secondsElement = eventTimerElement.querySelector(
		'.event-timer__time-seconds'
	);

	function eventsTimer() {
		// get the number of seconds that have elapsed since
		diff = ( ( startDate - Date.now() ) / 1000 ) | 0;

		if ( diff <= 0 ) {
			clearInterval( interval );
			diff = 0;
		}

		days = ( diff / ( 3600 * 24 ) ) | 0;
		diff -= days * 3600 * 24;

		hours = ( diff / 3600 ) | 0;
		diff -= hours * 3600;

		minutes = ( diff / 60 ) | 0;
		seconds = diff % 60 | 0;

		days = days < 10 ? '0' + days : days;
		hours = hours < 10 ? '0' + hours : hours;
		minutes = minutes < 10 ? '0' + minutes : minutes;
		seconds = seconds < 10 ? '0' + seconds : seconds;

		daysElement.textContent = daysElement && days;
		hoursElement.textContent = hoursElement && hours;
		minutesElement.textContent = minutesElement && minutes;
		secondsElement.textContent = secondsElement && seconds;
	}

	// we don't want to wait a full second before the timer starts
	eventsTimer();
}

simpleEventsCountdownTimer();
