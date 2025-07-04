/**
 * Simple EventsAdmin functionalities.
 *
 * @package simple-events
 */

jQuery( document ).ready( function( $ ) {
	// Handle Migrate Events.
	$( '#se_migrate_events_btn' ).on( 'click', function() {
		startMigrationProcess();
	} );

	/**
	 * Start the migration process and continue until all events are processed
	 */
	function startMigrationProcess() {
		// Show warning notice and disable button
		showMigrationNotice();
		$( '#se_migrate_events_btn' ).prop( 'disabled', true );

		// Start processing batches
		processMigrationBatch();
	}

	/**
	 * Show the migration warning notice
	 */
	function showMigrationNotice() {
		// Remove existing notice if any
		$( '#se_migration_notice' ).remove();

		// Create and insert the notice
		const notice = `
			<div id="se_migration_notice" style="background: #d63384; color: #fff; padding: 15px; margin: 15px 0; border-radius: 6px; border: 1px solid #b02a37; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
				<div style="display: flex; align-items: center; gap: 10px; font-weight: 600;">
					<span style="font-size: 20px;">⚠️</span>
					<span style="font-size: 14px;">MIGRATION IN PROGRESS - DO NOT CLOSE YOUR BROWSER</span>
				</div>
				<p style="margin: 8px 0 0 30px; font-size: 13px; opacity: 0.9;">
					Please keep this page open while events are being migrated. Closing the browser will interrupt the process.
				</p>
			</div>
		`;

		$( '#se_migrate_events_wrapper' ).before( notice );
	}

	/**
	 * Hide the migration warning notice
	 */
	function hideMigrationNotice() {
		$( '#se_migration_notice' ).fadeOut( 300, function() {
			$( this ).remove();
		} );
		// Keep the button disabled - no retry functionality
		$( '#se_migrate_events_btn' ).prop( 'disabled', true );
	}

	/**
	 * Hide the migration warning notice but keep button disabled (for completion)
	 */
	function hideMigrationNoticeCompleted() {
		$( '#se_migration_notice' ).fadeOut( 300, function() {
			$( this ).remove();
		} );
		// Keep the button disabled when migration is completed
		$( '#se_migrate_events_btn' ).prop( 'disabled', true );
	}

	/**
	 * Process a single batch of migration events
	 */
	function processMigrationBatch() {
		// Track events processed.
		const perBatch = 1;

		// Get the next events that are still pending
		const nextEvents = $( '#se_migrate_events_wrapper .se_migrate_event' ).filter( '[data-status="pending"]' ).slice( 0, perBatch );

		// If no more events to process, we're done
		if ( nextEvents.length === 0 ) {
			hideMigrationNoticeCompleted();
			console.log( 'Migration completed - all events processed!' );
			return;
		}

		// Get all the event ids.
		const eventIds = nextEvents.map( function() {
			return $( this ).data( 'event-id' );
		} );

		// Set events to processing status before making the request
		nextEvents.each( function() {
			const eventElement = $( this );
			eventElement.attr( 'data-status', 'processing' );
			const statusElement = eventElement.find( '.se_migrate_event_status' );
			statusElement.css({
				'background': '#007cba',
				'color': '#fff'
			}).text( 'Processing...' );
		} );

		// Make REST API call to migrate events
		$.ajax( {
			url: '/wp-json/simple-events/migrate-events',
			type: 'POST',
			data: {
				events: JSON.stringify( eventIds.get() )
			},
			success: function( response ) {
				// Update the status of processed events
				if ( response.data ) {
					Object.keys( response.data ).forEach( function( eventId ) {
						const resultData = response.data[eventId];
						const eventElement = $( `[data-event-id="${eventId}"]` );
						const statusElement = eventElement.find( '.se_migrate_event_status' );
						const versionElement = eventElement.find( 'span[style*="monospace"]' );

						// Handle the new response format
						if ( typeof resultData === 'object' && resultData !== null ) {
							const success = resultData.success;
							const version = resultData.version;

							// Update status
							eventElement.attr( 'data-status', success ? 'completed' : 'error' );

							if ( success ) {
								statusElement.css({
									'background': '#00a32a',
									'color': '#fff'
								}).text( 'Completed' );
							} else {
								statusElement.css({
									'background': '#d63638',
									'color': '#fff'
								}).text( 'Error' );
							}

							// Update version if provided
							if ( version && versionElement.length ) {
								versionElement.text( 'v' + version );
							}
						} else {
							// Fallback for old response format (boolean)
							const success = Boolean( resultData );
							eventElement.attr( 'data-status', success ? 'completed' : 'error' );

							if ( success ) {
								statusElement.css({
									'background': '#00a32a',
									'color': '#fff'
								}).text( 'Completed' );
							} else {
								statusElement.css({
									'background': '#d63638',
									'color': '#fff'
								}).text( 'Error' );
							}
						}
					} );
				}

				// Process the next batch after a short delay
				setTimeout( function() {
					processMigrationBatch();
				}, 500 );
			},
			error: function( xhr, status, error ) {
				console.error( 'Migration failed:', error );
				// Reset processing events back to pending on error
				nextEvents.each( function() {
					const eventElement = $( this );
					if ( eventElement.attr( 'data-status' ) === 'processing' ) {
						eventElement.attr( 'data-status', 'pending' );
						const statusElement = eventElement.find( '.se_migrate_event_status' );
						statusElement.css({
							'background': '#ffc107',
							'color': '#856404'
						}).text( 'Pending' );
					}
				} );

				// Hide notice and re-enable button on error
				hideMigrationNotice();
			}
		} );
	}

	// Handle Ticket Only Order Completion..
	$( '#se_ajax_btn' ).on( 'click', function() {
		// Disable button and extract action.
		$( this ).prop( 'disabled', true );
		const action = $( this ).data( 'action' );

		// Perform AJAX request.
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: action,
			},
			success: function( response ) {
				$( '#se_ajax_response' ).html( `<p>${response?.data}</p>` );
			},
			error: function() {
				$( '#se_ajax_response' ).html( '<p>Something went wrong!</p>' );
			},
			complete: function() {
				$( '#se_ajax_btn' ).prop( 'disabled', false );
				setTimeout( () => {
					$( '#se_ajax_response' ).html( '' );
				}, 2000 );
			},
		} );
	} );

	// Handle Clear Orphaned Events button
	$( '#se_clear_orphaned_btn' ).on( 'click', function() {
		// Disable button and extract action.
		$( this ).prop( 'disabled', true );
		const action = $( this ).data( 'action' );

		// Perform AJAX request.
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: action,
			},
			success: function( response ) {
				$( '#se_clear_orphaned_response' ).html( `<p>${response?.data}</p>` );
			},
			error: function() {
				$( '#se_clear_orphaned_response' ).html( '<p>Something went wrong!</p>' );
			},
			complete: function() {
				$( '#se_clear_orphaned_btn' ).prop( 'disabled', false );
				setTimeout( () => {
					$( '#se_clear_orphaned_response' ).html( '' );
				}, 2000 );
			},
		} );
	} );

	// Handle Skip Cart and Empty Cart options.
	const skipCart = $( 'input[name="se_options[skip_cart]"]' );
	const emptyCartBeforeAddingTickets = $( 'input[name="se_options[empty_cart_before_adding_tickets]"]' );

	// If Skip Cart is not enabled, disable Empty Cart Before Adding Tickets.
	$( window ).on( 'load', function () {
		if( skipCart && ! skipCart.is( ':checked' ) ) {
			emptyCartBeforeAddingTickets.prop( 'checked', false );
			emptyCartBeforeAddingTickets.closest( 'tr' ).hide();
		}
	} );

	// Handle Skip Cart option change.
	skipCart.on( 'input', function() {
		if( $( this ).is( ':checked' ) ) {
			emptyCartBeforeAddingTickets.closest( 'tr' ).show();
		} else {
			emptyCartBeforeAddingTickets.prop( 'checked', false );
			emptyCartBeforeAddingTickets.closest( 'tr' ).hide();
		}
	} );
} );
