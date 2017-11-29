

(function($){



	// acf_oauth is already set by localize_script
	acf_oauth = $.extend( acf_oauth, {
		default_status : {
			status : 'preparing',
			status_code : '',
			service : false,
			request_id : false,
			request_url : false,
			redirect_uri : false
		}
	});




	/*
	* init
	*/

	function initialize_field( $el ) {


		// move data (from data-attr) from wrapper to the parent field
		$el.data( $el.find('.acf-oauth-wrapper').data() );


		// create default status
		var status = $el.oauthStatus( acf_oauth.default_status );


		// save status
		$el.oauthStatus( status );


		// verify existing data or prepare to create them
		acf_oauth_verify_credentials( $el );


		// update gui when something changes
		$el.on( 'change', acf_oauth_refresh_field_gui );


		// reset field
		$el.on( 'click', '.-sync, .acf-oauth-trash-account', function(){

			// remove errors
			$el.oauthError();

			// refresh field
			acf_oauth_generate_fresh_request( $el );

		});


		// start request
		$el.on( 'click', '.button.acf-oauth-verify' , function(){

			if( 'open' == $el.oauthStatus().status )
				oauth_do_request( $el );

		});


		// init gui
		$el.trigger( 'change' );

	}






	/*
	* Refresh Field GUI by current status
	*
	* Status meanings:
	* preparing (client side only) => no existing credentials, waiting for plugin to initialize the authentication process
	* open (client side only) => waiting for the user to start authentication
	* pending => user clicked the login button, waiting for a status change
	* failed => most probably user gave no consent or timed out
	* verified => working credentials
 	*/

	function acf_oauth_refresh_field_gui() {

		// triggered by event change

		var
			$el = $(this),
			status = $el.oauthStatus();


		// disable/enable button
		var $verify_button = $el.find( '.button.acf-oauth-verify' );

		$verify_button
			.toggle( 'verified' != status.status )
			.toggleClass( 'disabled', 'open' != status.status );


		$el.find('.acf-oauth-vcard').toggle( 'verified' == status.status );


		// save status as attribute for css
		$el.attr('data-status', status.status );

	}



	/*
	* Ask the Server for a Request URL and Request ID
	* works a kind of a reset function too
	*
	* Unfortunatly we can't do this when the user clicks the Login Button, as Browsers
	* will not open the window popup after an asynchronous request
	*
	* @todo: add error handling
	* @todo: generate new request after 10minutes
 	*/

	function acf_oauth_generate_fresh_request ( $el ) {


		// reset status
		var status = acf_oauth.default_status;
		$el.oauthStatus( status, true );


		// reset the input
		$el.find('input').first().val('');


		// try to use the initial uuid
		if( false !== $el.data( 'oauth-uuid' ) ) {

			$el.oauthStatus( { status : 'open', request_id : $el.data( 'oauth-uuid' ) }, true );

			$el.data( 'oauth-uuid', false );

			return;
		}


		// request new uuid from server
		$.ajax({
			url 			: acf_oauth.ajaxurl,
			dataType 	: 'json',
			data 			: {
				_oauthnonce : acf_oauth._oauthnonce,
				action 			: 'acf_oauth_generate_uuid',
				service 		: $el.data('oauth-service')
			}
		})


		.done( function( response ) {

			if( response.uuid )
				$el.oauthStatus( { status : 'open', request_id : response.uuid }, true );

		})


		.fail( function( ) {

			$el.oauthError( acf_oauth.lang.status_list['establish-request'].message );

		})
		;

	}


	/*
	* redirect user to the Service Provider for Authorization
 	*/

	function oauth_do_request( $el ) {


		$el.oauthError( acf_oauth.lang.status_list.pending.message, true );

		var

			local_request_url = acf_oauth.ajaxurl,

			args = {
				_oauthnonce : acf_oauth._oauthnonce,
				action 			: 'acf_oauth_do_request',
				field_key 	: $el.data('key'),
				request_id 	: $el.oauthStatus().request_id,
				service 		: $el.data('oauth-service')
			};


		local_request_url += '?' + $.param( args );


		// open Popup / new tab
		var win = window.open( local_request_url, "_blank", "width=600,height=600" );


		// check if the window was closed
		var stopAfter = false;
		var check = setInterval( function() {

			// if the a new request was regenerated
			if( args.request_id !== $el.oauthStatus().request_id )
				return clearInterval( check );


			// popup was closed
			if( win.closed ) {


				// stop the check
				clearInterval( check );


				// force a check, regulary a check is executed only every 4 seconds
				refresh_request_status( $el, false );


				// close the check after 30 seconds without a response
				setTimeout( function(){


					// if the a new request was already regenerated
					if( args.request_id !== $el.oauthStatus().request_id )
						return;


					// force new request if the status is still pending
					if( 'pending' === $el.oauthStatus().status ) {
						acf_oauth_generate_fresh_request( $el );
						$el.oauthError( acf_oauth.lang.status_list.timeout.message );
					}


				},
				1000 * 30 );

			}

		}, 500 );


		// set status to pending
		$el.oauthStatus( { status : 'pending' }, true );


		setTimeout( function() {

			refresh_request_status( $el );

		}, 5000 );


	}



	/*
	* Request current status from the server
	*
	* @todo: add error handling
 	*/

	function refresh_request_status( $el, repeat = true ) {


		// bail if status has changed
		if( 'pending' != $el.oauthStatus().status )
			return;


		// save request id
		var request_id = $el.oauthStatus().request_id;


		// request current status from the server
		$.ajax({
			url 			: acf_oauth.ajaxurl,
			dataType 	: 'json',
			data 			:	{
				_oauthnonce : acf_oauth._oauthnonce,
				action 			: 'acf_oauth_get_request_status',
				request_id 	: request_id
			}
		})


		// ToDo handle fail & success here
		.done( function( status ){


			// bail if status has changed
			if( 'pending' != $el.oauthStatus().status )
				return;


			// Pending
			// check again in x seconds
			if( 'pending' == status.status  ) {


				// save new status
				$el.oauthStatus( status, true );


				if( repeat )
					setTimeout( function() {


						// only check again if status was not altered in the mean time
						if( 'pending' == $el.oauthStatus().status )
							refresh_request_status( $el );


					}, 4000 );


			}



			// Success
			// great, we have credentials!
			else if( 'verified' == status.status ) {


				// save/create credential fields
				var field_key = $el.data('key');



				$el.find('input').first().val( status.credentials );


				// save new status
				$el.oauthStatus( status, false );


				// remove errors or waiting message
				$el.oauthError( );


				acf_oauth_verify_credentials( $el );

			}



			// Error
			// User probably gave no consent
			else if( status.status == 'failed' ) {


				// show error
				$el.oauthError( );

				// init for a new request
				acf_oauth_generate_fresh_request( $el );
				
			}




		} );


	}


	/*
	* Check the users credentials and return a some vcard html that shows some Details about the user
	*/
	function acf_oauth_verify_credentials( $el ) {


		// we can only check against existing credentials
		if( '' === $el.find('input').first().val() )
			return acf_oauth_generate_fresh_request( $el );


		$el.oauthError( acf_oauth.lang.status_list.checking.message, true );


		// request current status from the server
		$.ajax({
			url 			: acf_oauth.ajaxurl,
			data_type : 'html',
			data 			:	{
				_oauthnonce : acf_oauth._oauthnonce,
				action 			: 'acf_oauth_get_user_vcard',
				service			: $el.data('oauth-service'),
				credentials	: $el.find('input').first().val()
			}
		})


		.done( function( vcard ) {

			$el.find('.acf-oauth-vcard .vcard').html( vcard );

			$el.oauthStatus({ status : 'verified' }, true );

			// remove loading message
			$el.oauthError( );

		})

		.error( function() {


			$el.oauthError( acf_oauth.lang.status_list['verification-failed'].message );

			// failed to get vcard, remove data
			acf_oauth_generate_fresh_request( $el );
		});
	}

	/*
	* jQuery Helper Plugin - manages the status data per field
	*
	* @param new_status (object)[optional] - status object
	* @param trigger_change (bool)[optional] - wether or not to reflow the fields ui
	*/
	$.fn.oauthStatus = function( new_status, trigger_change ) {

		var $this = this,
				status = $this.data( 'status' ) || {};

		// setter
		if( undefined !== new_status ) {

			$.extend( status, new_status );

			$this.data('status', status );


			if( trigger_change ) {
				$this.trigger('change');
			}
		}

		return status;
	}


	/*
	* jQuery Helper Plugin - create an ACF Error
	*
	*/

	$.fn.oauthError = function( message, loading = false ) {

		var $el = this,
				$message = $(this).find('.acf-error-message'),
				$form = $(this).find('.acf-input');


		// blank message removes error
		if( undefined === message ) {

			$message.remove();
			return this;
		}


		// maybe create $message
		if( !$message.exists()) {

			$message = $('<div class="acf-error-message -dismiss"><p></p><a href="#" class="acf-icon -cancel small"></a><span class="acf-icon -sync small"></span></div>');
			$form.prepend( $message );
		}

		$message.toggleClass( '-loading', loading );
		$message.find('.-cancel').toggle( !loading );
		$message.find('.-sync').toggle( loading );


		$message.children('p').html( message );

		return this;
	}






	if( typeof acf.add_action !== 'undefined' ) {

		/*
		*  ready append (ACF5)
		*
		*  These are 2 events which are fired during the page load
		*  ready = on page load similar to $(document).ready()
		*  append = on new DOM elements appended via repeater field
		*
		*  @type	event
		*  @date	20/07/13
		*
		*  @param	$el (jQuery selection) the jQuery element which contains the ACF fields
		*  @return	n/a
		*/

		acf.add_action('ready append', function( $el ){

			// search $el for fields of type 'oauth'
			acf.get_fields({ type : 'oauth'}, $el).each(function(){

				initialize_field( $(this) );

			});

		});


	} else {


		/*
		*  acf/setup_fields (ACF4)
		*
		*  This event is triggered when ACF adds any new elements to the DOM.
		*
		*  @type	function
		*  @since	1.0.0
		*  @date	01/01/12
		*
		*  @param	event		e: an event object. This can be ignored
		*  @param	Element		postbox: An element which contains the new HTML
		*
		*  @return	n/a
		*/

		$(document).on('acf/setup_fields', function(e, postbox){

			$(postbox).find('.field[data-field_type="oauth"]').each(function(){

				initialize_field( $(this) );

			});

		});


	}


})(jQuery);
