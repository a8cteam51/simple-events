# Simple Events

A simple Gutenberg-first event management plugin that integrates with WooCommerce Box Office.

**If you want to install this plugin**, DON'T DOWNLOAD THIS REPO. You can download the latest stable version from the [releases page](https://github.com/a8cteam51/simple-events/releases)
or just click [here](https://github.com/a8cteam51/simple-events/releases/latest/download/simple-events.zip).

## Dependencies

Simple Events uses [Composer](https://getcomposer.org), a dependency manager for PHP. Visit the official Composer [download instructions](https://getcomposer.org/download/) to install Composer.

Then, run:

```
composer install
```

## Building

Run `npm install` to install all the Node.js dependencies.

Below you will find some information on how to run scripts.

### `npm start`
- Use to compile and run the block in development mode.
- Watches for any changes and reports back any errors in your code.

### `npm run build`
- Use to build production code for your block inside `build` folder.
- Runs once and reports back the gzip file sizes of the produced code.

## Testing

Tests run inside a Dockerized WordPress environment powered by [`@wordpress/env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/).

### Prerequisites

- Docker must be running
- Dependencies installed (`composer install` && `npm install`)

### Start the test environment

```
npx wp-env start
```

### Run PHP integration tests

```
npm run test:php
```

This executes PHPUnit inside the `tests` WordPress container. Tests live in `tests/phpunit/` and must extend `WP_UnitTestCase`. Test files should end in `Test.php`.

### Stop the test environment

```
npx wp-env stop
```

## Hooks

### Next & Previous Links

**This must be enabled in the `settings` before they are shown.**

> Change the previous link text (defaults to `<< {Event Title}`)
```php
add_filter('se_event_previous_link_text', function( string $link_text, WP_Post $event ) {
	return "Previous Event ({$event->post_title})";
}, 10, 2);
```

> Change the next link text (defaults to `{Event Title} >>`)
```php	
add_filter('se_event_next_link_text', function( string $link_text, WP_Post $event ) {
	return "Next Event ({$event->post_title})";
}, 10, 2);
```

> Change the link text to the calendar if page set in settings, if not set in settings will not show. (defaults to `View Full Calendar`)
```php
add_filter('se_event_calendar_link_text', function( string $link_text ) {
	return "View Full Calendar";
}, 10, 1);
```

### Calendar Rendering

#### Filter day events before calendar markup is built
```php
add_filter( 'simple_events_calendar_day_events', function( array $day_events, DateTime $date, int $start_timestamp, int $end_timestamp ) {
	$unique_events = array();
	$seen_keys     = array();

	foreach ( $day_events as $event ) {
		if ( ! is_object( $event ) || ! isset( $event->ID, $event->event_start_date, $event->event_end_date ) ) {
			$unique_events[] = $event;
			continue;
		}

		$key = implode(
			'|',
			array(
				(string) $event->ID,
				$event->event_start_date instanceof DateTime ? $event->event_start_date->format( 'U' ) : '',
				$event->event_end_date instanceof DateTime ? $event->event_end_date->format( 'U' ) : '',
			)
		);

		if ( isset( $seen_keys[ $key ] ) ) {
			continue;
		}

		$seen_keys[ $key ] = true;
		$unique_events[]   = $event;
	}

	return $unique_events;
}, 10, 4 );
```

### Cron Tasks for Event Start Date

> When the cron task runs to update the event start date to a future date if its passed and future dates exist.

#### How often to check events.
```php
add_filter('se_event_update_query_dates_interval', function( int $interval ) {
	return 'hourly'; // Please use the WP Cron intervals: https://developer.wordpress.org/reference/functions/wp_get_schedules/
}, 10, 1);
```

#### Age of events to check
```php
add_filter('se_event_update_dates_search_range', function( int $age ) {
	return 48 * HOUR_IN_SECONDS; // The number of days to check for events that are older than this.
}, 10, 1);
```

#### Skip event
It is possible to skip and event from being updated by adding a filter to the event.
```php
add_filter('se_event_update_query_dates_skip', function( bool $skip, intget $event ) {
	// Skip the event if it is a specific event.
	if ( $event === 1234 ) {
		return true;
	}

	return $skip;
}, 10, 2);
```

#### Post Update
When an event has been updated, the `se_event_updated_query_dates` is fired.
```php
add_action('se_event_updated_query_dates', function( int $event_id ) {
	// Do something with the event.
}, 10, 2);
```

### Calendar Export

#### Modify export query arguments
```php
add_filter('se_calendar_export_query_args', function( array $args ) {
	// Modify the query arguments for calendar export
	$args['posts_per_page'] = 50; // Export more events
	return $args;
}, 10, 1);
```

#### Modify event location for iCal
```php
add_filter('se_calendar_export_event_location', function( $location_object, int $event_id, array $event_date ) {
	// Modify the location object in iCal export
	// $location_object is an Eluceo\iCal\Domain\ValueObject\Location object
	
	// You can create a new Location object with additional details
	$venue = get_post_meta( $event_id, 'se_event_venue', true );
	$full_address = $location_object->getValue();
	if ( ! empty( $venue ) ) {
		$full_address = $venue . ', ' . $full_address;
	}
	
	return new \Eluceo\iCal\Domain\ValueObject\Location( $full_address );
}, 10, 3);
```

#### Modify individual event in export
```php
add_filter('se_calendar_export_event', function( $event, int $event_id, array $event_date ) {
	// Modify individual event before adding to calendar
	// $event is an Eluceo\iCal\Domain\Entity\Event object
	return $event;
}, 10, 3);
```

#### Modify the calendar object
```php
add_filter('se_calendar_export_calendar', function( $calendar, array $events ) {
	// Modify the calendar object before rendering
	// $calendar is an Eluceo\iCal\Domain\Entity\Calendar object
	return $calendar;
}, 10, 2);
```

#### Modify the raw iCal output
```php
add_filter('se_calendar_export_rendered', function( string $ical_content ) {
	// Modify the final iCal text output before sending
	return $ical_content;
}, 10, 1);
```

## Extensions

### Featured image with Focal Point
Simple plugin to add a focal point control to the featured post image.

**If you want to use this plugin extension**, you can find it at https://github.com/a8cteam51/bamberg-ua/tree/trunk/mu-plugins/team51-focal-point

Copy the `team51-focal-point` folder to your `mu-plugins` directory.

## Changelog

### 2.1.3

- **Calendar grid alignment:** fixed month grid date placement when WordPress **Week Starts On** is set to Sunday (or any `start_of_week` other than Monday). Dates and events now appear under the correct weekday column.

### 2.1.2

- **Calendar loading UX:** added a loading skeleton while calendar month requests are in flight.
- **Calendar mobile interaction:** fixed day selection logic on mobile breakpoints when themes customize hidden/mobile display rules.
- **Calendar navigation:** added a "Show Months in Order" option for sequential month navigation (defaults to off for existing blocks).

### 2.1.1

- **Loop Event Info block:** added a configurable HTML wrapper element (`div`/`p`/`h1`–`h6`), per-block date and time format overrides, and a query offset control.
- **Query Loop Events:** the block-editor preview now matches the published front-end for every feed order. Previously, with "Newest to Oldest" the editor preview showed the oldest events while the front-end showed the newest.
- Removed a redundant query cache-buster that wrote a timestamp into post content on every change.
- Added Playwright end-to-end tests (run in CI) covering the event-dates save flow, the Query Loop editor/front-end parity, and the Loop Event Info rendering options.
