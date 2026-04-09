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

## Extensions

### Featured image with Focal Point
Simple plugin to add a focal point control to the featured post image.

**If you want to use this plugin extension**, you can find it at https://github.com/a8cteam51/bamberg-ua/tree/trunk/mu-plugins/team51-focal-point

Copy the `team51-focal-point` folder to your `mu-plugins` directory.
