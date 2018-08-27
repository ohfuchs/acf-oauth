<?php

/*
Plugin Name: Advanced Custom Fields: OAuth
Plugin URI: https://github.com/ohfuchs/acf-oauth
Description: Adds new Fieldtype that allows to connect to an OAuth Service
Version: 1.0.0
Author: Oh!Fuchs
Author URI: https://www.ohfuchs.com
License:
License URI:
*/

// exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;


// check if class already exists
if( !class_exists('acf_plugin_oauth') ) :


class acf_plugin_oauth {




	/*
	*  __construct
	*
	*  This function will setup the class functionality
	*
	*/

	public static $nonce_slug = 'acf-oauth-1';


	function __construct() {

		// vars
		$this->settings = array(
			'version'	=> '1.0.0',
			'url'		=> plugin_dir_url( __FILE__ ),
			'path'		=> plugin_dir_path( __FILE__ )
		);


		// set text domain
		// https://codex.wordpress.org/Function_Reference/load_plugin_textdomain
		load_plugin_textdomain( 'acf-oauth', false, plugin_basename( dirname( __FILE__ ) ) . '/lang' );


		// include buildin services
		add_action('acf-oauth/include_services', array($this, 'include_services'), 9 );


		// include field
		add_action('acf/include_field_types', array($this, 'include_field_types'), 10 );


    add_action( 'init', 																array( $this, '_decode_state' ), 9 );
		add_action( 'wp_ajax_acf_oauth_generate_uuid', 			array( $this, 'generate_uuid' ) );
		add_action( 'wp_ajax_acf_oauth_do_request', 				array( $this, 'do_request' ) );
		add_action( 'wp_ajax_acf_oauth_get_request_status', array( $this, 'get_request_status' ) );
		add_action( 'wp_ajax_acf_oauth_get_user_vcard', 		array( $this, 'get_user_vcard' ) );
		add_action( 'wp_ajax_acf_oauth_finish_request', 		array( $this, 'finish_request' ) );
		add_action( 'wp_ajax_acf_oauth_status_page', 				array( $this, 'status_page' ) );
	}


	/*
	* helper to create uuid
	*/

	static function _uuid() {

		return bin2hex( random_bytes( 16 ) );
	}



	/*
	*  Creates a request_id, request_url and redirect_uri as an initial
	*  request status object.
	*
	*  @todo: maybe save the data some other way than using transients api
	*  @request_param	'service' - the slug of an registered OAuth Service
	*  @echo (json) $status or text message in case of an error
	*/

	function generate_uuid(){


		// check
		if( !check_ajax_referer( $this::$nonce_slug, '_oauthnonce', false ) )
			acf_plugin_oauth::_die( 'bad-request' );


		// check
		if( !current_user_can( 'edit_posts' ) )
			acf_plugin_oauth::_die( 'not-authorized' );


		$uuid = array( 'uuid' => $this->_uuid() );


		// return status json
		$this->json_response( $uuid, 200 );


	}



	/*
	* Decodes Data from $state query arg and adds them to $_REQUEST. This must happen
	* before admin-ajax.php checks for the 'action' value
	*
	*/

	function _decode_state(){

		if( !is_admin()  || !defined('DOING_AJAX') || !is_user_logged_in())
			return;

		if( !isset( $_REQUEST['state'] ) || !is_string( $_REQUEST['state'] ) )
			return;

		$data = base64_decode( $_REQUEST['state'] );

		if( false === $data )
			return;

		$data = json_decode( $data, true );

		if( !is_array( $data ) )
			return;

		$_REQUEST = array_merge( $_REQUEST, $data );

	}


	/*
	*  Creates a request_url and redirect_uri as an initial
	*  request status object. Redirects directly to request_url
	*
	*  @todo: maybe save the data some other way than using transients api
	*  @request_param	'service' - the slug of an registered OAuth Service
	*  @request_param	'request_id' - an UUID identifying the request
	*  @echo (json) $status or text message in case of an error
	*/

	function do_request(){


		// check
		if( !check_ajax_referer( $this::$nonce_slug, '_oauthnonce', false ) || !current_user_can( 'edit_posts' ) )
			acf_plugin_oauth::display_status( 'bad-request', 'CSRF' );


		// check
		if( !isset( $_REQUEST['service'] ) || !isset( $_REQUEST['request_id'] ) )
			acf_plugin_oauth::display_status( 'bad-request', 'Missing Data' );


		// basic status
		$status = array(
			'status' 			=> 'pending',
			'status_code' 			=> 'pending',
			'service' 		=> sanitize_key( $_REQUEST['service'] ),
			'request_id' 	=> sanitize_key( $_REQUEST['request_id'] )
		);


		// filter status
		$status = apply_filters( 'acf-oauth/service/'.$status['service'].'/status', $status );
		$status = apply_filters( 'acf-oauth/status', $status );



		// Service should add request url
		$status = apply_filters( 'acf-oauth/service/'.$status['service'].'/request_url', $status );


		if( ! set_transient( acf_plugin_oauth::transientkey( $status['request_id'] ), $status, HOUR_IN_SECONDS / 4 ) )
			acf_plugin_oauth::display_status( 'establish-request' );


		// save status
		if( !isset( $status['request_url']) )
			acf_plugin_oauth::display_status( 'could-not-redirect-serivce', 'Missing request_url' );


		// save status
		if( headers_sent() )
			acf_plugin_oauth::display_status( 'could-not-redirect-serivce', 'Headers sent' );


		// redirect
    header ('Location: ' . $status['request_url'] );
    die();


	}





	/*
	*  Returns the current request status from the database
	*
	*  @request_param	'request_id' - the slug of an registered OAuth Service
	*  @echo (json) $status or text message in case of an error
	*/

	function get_request_status(){

		// check
		if( !check_ajax_referer( $this::$nonce_slug, '_oauthnonce', false ) )
			acf_plugin_oauth::_die( 'bad-request', 'Code 1' );


		// check
		if( !current_user_can( 'edit_posts' ) )
			acf_plugin_oauth::_die( 'not-authorized' );


		// check
		if( !isset( $_REQUEST['request_id'] ) )
			acf_plugin_oauth::_die( 'bad-request', 'Code 2' );


		// get current status
		$status = get_transient( $this::transientkey( $_REQUEST['request_id'] ));


		// throw error if status is not available anymore
		if( false === $status )
			acf_plugin_oauth::_die( 'missing-request' );

		// success
		$this->json_response( $status );

	}





	/*
	*  This function will create a small bit of html that shows
	*  the users identity at the corresponding service.
	*
	*  This is also a check if the credentials work. Any response with
	*  a http_response_code other than 200 will lead to the credentials
	*  field data becoming deleted.
	*
	*  @request_param	'service' - the slug of an registered OAuth Service
	*  @request_param	'credentials' - the Credentials the Service requires to make API calls
	*  @echo vCard html or text message in case of an error
	*/

	function get_user_vcard(){


		// check
		if( !check_ajax_referer( $this::$nonce_slug, '_oauthnonce', false ) )
			acf_plugin_oauth::_die( 'bad-request', 'Code 1' );


		// check
		if( !current_user_can( 'edit_posts' ) )
			acf_plugin_oauth::_die( 'not-authorized' );


		// check
		if( !isset( $_REQUEST['service'] ) or !isset( $_REQUEST['credentials'] ) )
			acf_plugin_oauth::_die( 'bad-request', 'Code 2' );


		// let service create vcard html in case of success
		$vcard = apply_filters( 'acf-oauth/service/'.sanitize_key( $_REQUEST['service'] ).'/vcard', false, $_REQUEST['credentials'] );


		// throw an error if the credentials could not be used to request user data
		if( false === $vcard )
			acf_plugin_oauth::_die( 'no-userdata' );

		// success
		http_response_code( 200 );
		echo $vcard;
		die();
	}





	/*
	*  This function will pass the request response, after the user is
	*  redirected back from the OAuth service, to the service plugin.
	*
	*  Service should also handle situations where the user does not give
	*	 consent.
	*
	*  @todo: this function currently uses admin_ajax.php to display
	*  html, which is not ideal.
	*
	*  @param	n/a
	*  @echo a html page that explains the current status
	*/

	static function finish_request(){


		// check
		if( !check_ajax_referer( acf_plugin_oauth::$nonce_slug, '_oauthnonce', false ) )
			acf_plugin_oauth::display_status( 'bad-request', 'CSRF' );



		// check
		if( !current_user_can( 'edit_posts' ) )
			acf_plugin_oauth::display_status( 'not-authorized', 'Capability' );


		// check
		if( !isset( $_REQUEST['request_id'] ) )
			acf_plugin_oauth::display_status( 'bad-request', 'Missing request_id' );


		// get current status
		$status = get_transient( acf_plugin_oauth::transientkey( $_REQUEST['request_id'] ));


		// make service accessible by status functions
		$_REQUEST['service'] = $status['service'];


		// check if status exists
		if( false === $status )
			acf_plugin_oauth::display_status( 'missing-request', 'Could not load Status from DB' );


		// only verify pending status
		if( 'pending' !== $status['status'] )
			acf_plugin_oauth::display_status( 'not-pending' );


		// let service check if the user gave consent
		$status = apply_filters( 'acf-oauth/service/'.$status['service'].'/check_response', $status );


		// if the status is still pending, make it an error
		if( 'pending' === $status['status'] ) {

			$status['status'] = 'error';
			$status['status_code'] = 'no-consent';
		}



		// save the current status
		set_transient(
			acf_plugin_oauth::transientkey( $status['request_id'] ),
			$status,
			HOUR_IN_SECONDS / 6
		);



		// display final status
		acf_plugin_oauth::display_status( $status['status_code'] );


 	}

	/*
	*  This function will include the field type class and the OAuth services
	*
	*  @param	$version (int) major ACF version. Defaults to false
	*  @return	n/a
	*/

	function include_field_types( $version = false ) {


		if( !$version === 5 )
			return;


		// load the service prototype class
		include_once( $this->settings['path'].'service-class.php');


		// you can include/override a service here
		do_action('acf-oauth/include_services');


		// include field
		include_once('fields/acf-oauth-v5.php');


	}



	function include_services() {


		// buildin services
		include_once( $this->settings['path'].'services/instagram/instagram.php');
		include_once( $this->settings['path'].'services/facebook/facebook.php');
		include_once( $this->settings['path'].'services/google/google.php');
		include_once( $this->settings['path'].'services/twitter/twitter.php');
		include_once( $this->settings['path'].'services/pinterest/pinterest.php');

	}




	/*
	* echo error message by code
	*/
	static function _die( $code, $debug = '' ) {


		$status = acf_plugin_oauth::get_status( $code, $_REQUEST['service'] );


		http_response_code( $status['httpcode'] );


		echo $status['message'];

		if( $debug )
			echo ' '.$debug;


		die();

	}


	/*
	*  helper function to send a json response
	*/

	function json_response( $data, $http_response_code = 200 ) {


		// filter fields with prefix _ from status array,
		// these fields should never be exposed to the client
		if( isset( $data['status'] ) ) {

			foreach( $data as $key => $value ) {
				if( '_' === substr( $key, 0, 1 ) ) {
					unset( $data[ $key ] );
				}
			}
		}


		header('Content-Type: application/json');

		echo json_encode($data);

		die();

	}




	/*
	* Redirects to the HTML Status Page. The Redirect is required to prevent third-party
	* Scripts to read the Authcode
	*/

	static function display_status( $code, $debug = '', $service = false ) {


		// extract current service from request if possible
		if( ! $service && isset( $_REQUEST['service'] ) )
		 	$service = sanitize_key( $_REQUEST['service'] );


		$url = add_query_arg(

			array(

				'action' 			=> 'acf_oauth_status_page',

				'_oauthnonce' => wp_create_nonce( acf_plugin_oauth::$nonce_slug ),

				'code' 				=> $code,

				'service' 		=> $service,

				'debug' 			=> $debug

			),

			admin_url( 'admin-ajax.php' )

		);


		// redirect
    header ('Location: ' . $url );

    die();

	}



	/*
	* Retrieve the list of possible response statuses
	*/

	static function get_status_list() {

		return apply_filters( 'acf-oauth/status_list', array(

			'default-error' => array(
				'type' 		 => 'error',
				'message'  => __( 'Sorry, an unknown error occurred!', 'acf-oauth' ),
				'httpcode' => 500
			),
			'bad-request' => array(
				'type' 		 => 'error',
				'message'  => __( 'Bad request', 'acf-oauth' ),
				'httpcode' => 400
			),
			'establish-request' => array(
				'type' 		 => 'error',
				'message'  => __( 'Could not establish request', 'acf-oauth' ),
				'httpcode' => 500
			),
			'could-not-redirect-serivce' => array(
				'type' 		 => 'error',
				'message'  => __( 'Could not redirect to Service', 'acf-oauth' ),
				'message_service'  => _x( 'Could not redirect to %s', 'Service name e.g. Instagram', 'acf-oauth' ),
				'httpcode' => 500
			),
			'not-authorized' => array(
				'type' 		 => 'error',
				'message'  => __( 'Not authorized', 'acf-oauth' ),
				'httpcode' => 401
			),
			'missing-request' => array(
				'type' 		 => 'error',
				'message'  => __( 'Could not find request', 'acf-oauth' ),
				'httpcode' => 400
			),
			'no-userdata' => array(
				'type' 		 => 'error',
				'message'  => __( 'Could not read Userdata', 'acf-oauth' ),
				'httpcode' => 500
			),
			'connection-error' => array(
				'type' 		 => 'error',
				'message'  => __( 'Could not connect to Service', 'acf-oauth' ),
				'message_service'  => _x( 'Could not connect to %s', 'Service name e.g. Instagram', 'acf-oauth' ),
				'httpcode' => 500
			),
			'not-pending' => array(
				'type' 		 => 'error',
				'message'  => __( 'Sorry, this is not a pending request', 'acf-oauth' ),
				'httpcode' => 400
			),
			'denied-request' => array(
				'type' 		 => 'error',
				'message'  => __( 'The Service denied the request', 'acf-oauth' ),
				'message_service'  => _x( '%s denied the request', 'Service name e.g. Instagram', 'acf-oauth' ),
				'httpcode' => 500
			),
			'no-consent' => array(
				'type' 		 => 'error',
				'message'  => __( 'Could not verify your consent', 'acf-oauth' ),
				'httpcode' => 400
			),
			'parse-response' => array(
				'type' 		 => 'error',
				'message'  => __( 'Could not parse server response', 'acf-oauth' ),
				'httpcode' => 500
			),
			'no-credentials' => array(
				'type' 		 => 'error',
				'message'  => __( 'Could not read your Credentials', 'acf-oauth' ),
				'httpcode' => 500
			),
			'timeout' => array(
				'type' 		 => 'error',
				'message'  => __( 'The Service took to long to respond', 'acf-oauth' ),
				'message_service'  => _x( '%s took to long to respond', 'Service name e.g. Instagram', 'acf-oauth' ),
				'httpcode' => 500
			),

			'denied' => array(
				'type' 		 => 'error',
				'message'  => __( 'Oooops, you denied the request :(', 'acf-oauth' ),
				'httpcode' => 400
			),

			'verification-failed' => array(
				'type' 		 => 'error',
				'message'  => __( 'Could not verify your Credentials', 'acf-oauth' ),
				'httpcode' => 500
			),



			'pending' => array(
				'message'  				 => __( 'Waiting for your consent…', 'acf-oauth' ),
				'httpcode' 				 => 200
			),

			'checking' => array(
				'message'  				 => __( 'Checking Credentials…', 'acf-oauth' ),
				'httpcode' 				 => 200
			),

			'after-log-in' => array(
				'type' 		 				 => 'success',
				'message'  				 => __( 'Thanks for logging in!', 'acf-oauth' ),
				'message_service'  => _x( 'Thanks for logging in with %s!', 'Service name e.g. Instagram', 'acf-oauth' ),
				'httpcode' 				 => 200
			),

		));

	}





	/*
	*  Get a Status by Code. If Code does not exist, return default Error Status
	*
	* @return $status Array
	*/


	static function get_status( $code, $service = false ) {


		$status_list = acf_plugin_oauth ::get_status_list();


		$status = isset( $status_list[ sanitize_key( $code ) ] )
			? $status_list[ sanitize_key( $code ) ]
			: $status_list[ 'default-error' ];


		// inherit title
		if( !isset( $status['title'] ) )
			$status['title'] = $status['message'];


		// add Service name if possible
		if( acf_plugin_oauth::is_service( $service ) )
			foreach( array( 'title', 'message' ) as $key )
				if( isset( $status[ $key . '_service' ] ) )
					$status[ $key ] = sprintf( $status[ $key . '_service' ], acf_plugin_oauth::service_label( $service ) );


		return $status;

	}





	/*
	*  display the HTML Status Page
	*/

	static function status_page() {


		$status = acf_plugin_oauth::get_status( $_REQUEST['code'], $_REQUEST['service'] );

		$debug = isset( $_REQUEST['debug'] ) && is_string( $_REQUEST['debug'] && !empty( $_REQUEST['debug'] ) )
		 	? sanitize_text_field( $_REQUEST['debug'] )
			: false;

		http_response_code( $status['httpcode'] );


		// services can use these hooks to display their own html page
		// services should make sure to exit code execution with die() or
		// similar
 		do_action('acf-oauth/service/'.(string)$_REQUEST['service'].'/finish_request', $status );
		do_action('acf-oauth/finish_request', $status );


		// include default template
		include( plugin_dir_path( __FILE__ ) . 'template-status.php' );


		die();


	}





	/*
	*  helper to create transient key
	*/

	public static function transientkey( $request_id ) {

		return 'oauth-'.$request_id;
	}





	/*
	* Helper to check if Service is active
	*/

	static function is_service( $service ) {

		return apply_filters('acf-oauth/service/'.(string) $service.'/active', false );
	}




	/*
	* Helper to get Service Label
	*/

	static function service_label( $service ) {

		return apply_filters('acf-oauth/service/'.(string) $service.'/label', $service );
	}



}


// initialize
new acf_plugin_oauth();


// class_exists check
endif;

