/**
 * Simple EventsAdmin functionalities.
 *
 * @package simple-events
 */

jQuery( document ).ready( function( $ ) {
	
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
