<?php

// exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;



// removed the class_exists check, to prevent a malicious plugin from replacing this class

  class acf_oauth_service {


    // the default oauth version
    public $oauth_version = '2';


    // placeholder for cryptkey, a service should always override this with a custom key
    // https://api.wordpress.org/secret-key/1.1/salt/
    protected $cryptkey = '[Xt:?%BTwX-4R^kN^O^0v)q9h/q,;1EjF+^{R:+|kb/DSv`3ZV-&+1-0V+BV6s-3';


    // app credentials, regulary an array of client_id and client_secret
    protected $appcredentials;



    public $name, $label, $button_label;



    function __construct( ) {

      // some services require you to register for an app or
      // developer key. allow users of your service plugin to provide
      // their appcredentials by hooking to this this filter
      //
      // the service should stay unavailable until required keys are
      // provided

      if( !$this->button_label )
        $this->button_label = sprintf( __( 'Login with %s', 'acf-oauth' ), $this->label );

      if( !$this->appcredentials )
        $this->appcredentials =  apply_filters( 'acf-oauth/service/'.$this->name.'/appcredentials', false );


      // check if service is configured
      add_filter( 'acf-oauth/service/'.$this->name.'/active', array( $this, 'is_active' ));


      // get label
      add_filter( 'acf-oauth/service/'.$this->name.'/label', array( $this, 'label' ));


      // button-label
      add_filter( 'acf-oauth/service/'.$this->name.'/button-label', array( $this, 'button_label' ));


      // return oauth version
      add_filter( 'acf-oauth/service/'.$this->name.'/oauth_version', array( $this, 'oauth_version' ));


      // register service
      add_filter( 'acf-oauth/services', array( $this, 'register_service' ) );



      // create the url the user is redirected to
      add_filter( 'acf-oauth/service/'.$this->name.'/request_url',  array( $this, 'request_url' ), 10 );


      // modify the request url arguments
      add_filter( 'acf-oauth/service/'.$this->name.'/request_url_args',  array( $this, 'request_url_args' ));


      // check for standardized oauth error
      add_filter( 'acf-oauth/service/'.$this->name.'/check_response',  array( $this, 'check_response_oauth_error' ), 5 );


      // exchange oauth_token or code for access_token
      add_filter( 'acf-oauth/service/'.$this->name.'/check_response',  array( $this, 'check_response_exchange' ), 9 );



      // modify the args passed during the exchange process
      add_filter( 'acf-oauth/service/'.$this->name.'/exchange_args',  array( $this, 'exchange_args' ), 10, 2 );



      // create credential array from the exchange response
      add_filter( 'acf-oauth/service/'.$this->name.'/create_credentials',  array( $this, 'create_credentials' ), 10, 3 );



      // create a small html representation of the users identity at your service
      if( method_exists( $this, 'vcard' ) )
        add_filter( 'acf-oauth/service/'.$this->name.'/vcard',  array( $this, 'vcard' ), 10, 2 );



      // add filter to auto-decrypt the data for the template
      add_filter( 'acf-oauth/service/'.$this->name.'/format_value',  array( $this, 'format_value' ), 10, 3 );


    }



    /*
    * check for any existing app credentials like Apikey or Secret
    */

    function is_active( $false ) {

      return !empty( $this->appcredentials ) && isset( $this->appcredentials['client_id'] ) && isset( $this->appcredentials['client_secret'] );
    }



    /*
    * return oauth version
    */

    function oauth_version( ) {

      return $this->oauth_version;
    }



    /*
    * return oauth version
    */

    function label( ) {

      return $this->label;
    }



    /*
    * return oauth version
    */

    function button_label( ) {

      return $this->button_label;
    }



    /*
    * register service
    */

    function register_service( $services ) {

      $services[ $this->name ] = $this->label;

      return $services;
    }




    /*
    * split job by version
    */

    function request_url( $status ) {

      if( '1' === $this->oauth_version() )
        return $this->request_url_v1( $status );
      else
        return $this->request_url_v2( $status );
    }




    /*
    * OAuth 1.0 flow
    *
    * create the request url for the service.
    * this url.
    *
    */


    protected function request_url_v1( $status ) {




  		// redirect params
  		$state_args = array(

  			'action' 			=> 'acf_oauth_finish_request',

  			'request_id' 	=> $status['request_id'],

        '_oauthnonce' => wp_create_nonce( acf_plugin_oauth::$nonce_slug )

  		);


      // create a state param
      // this is actually not required by 1.0 but 2.0
      // we do it for uniformity
      $state = base64_encode( json_encode( $state_args ) );


      // add state to redirect uri
      // opposite to 2.0 state is not piped through the service as request argument
      $redirect_uri = add_query_arg(
        'state',
        urlencode( $state ),
        admin_url( 'admin-ajax.php' )
      );


      // the arguments required to authenticate the request
      $oauth = $this->oauth_token_request_args( array(

        'oauth_callback'          => $redirect_uri,

        'oauth_consumer_key'      => $this->appcredentials['client_id'],

        'oauth_nonce'             => wp_create_nonce( acf_plugin_oauth::$nonce_slug ),

        'oauth_signature_method'  => 'HMAC-SHA1',

        'oauth_timestamp'         => time(),

        'oauth_version'           => '1.0'

      ) );


      // add signature
      $oauth['oauth_signature'] = $this::create_signature( $this->request_url_oauth_token, $oauth, array( $this->appcredentials['client_secret'], '' ) );


      // request the oauth_token
      $response = wp_remote_post(

        $this->request_url_oauth_token,

        array(
          'headers' => array(

            // add Authorization Header
            'Authorization' => $this::create_oauth_header( $oauth )

          )
        )

      );


      // return before adding request url
      if( $response instanceof WP_Error || '200' != wp_remote_retrieve_response_code( $response ) )
        return $status;


      // data is formatted like an url querystring
      $data_str = wp_remote_retrieve_body( $response );


      // data
      $data = array();


      // parse query string into $data as array
      parse_str( $data_str, $data );



      /*
        Sample $data
        ["oauth_token"]=>  string(27) "123"
        ["oauth_token_secret"]=>    string(32) "123"
        ["oauth_callback_confirmed"]=>  string(4) "true"
       */


      // save data for later use
      $request_data['response_v1'] = $data;


      // the data will not be exposed to the client as long it is prefixed with _
      $status['_request_data'] = $this->encrypt_array( $request_data, 'request_data' );


      // add oauth token to request url
      $args = array(
        'oauth_token' => $data['oauth_token']
      );


      $status['request_url'] = add_query_arg( urlencode_deep( $args ), $this->request_url_base );


      return $status;

    }


    /*
    *  Helper function to give child class more flexibility without exposing data to a Wordpress Filter
    */

    protected function oauth_token_request_args( $args ) {
      return $args;
    }




    /*
     *  OAuth 2.0 flow
     *
     *  create the request url for the service.
     *  this url.
     *
     */

    protected function request_url_v2( $status ) {



  		// redirect params
  		$state_args = array(

  			'action' 			=> 'acf_oauth_finish_request',

  			'request_id' 	=> $status['request_id'],

        '_oauthnonce' => wp_create_nonce( acf_plugin_oauth::$nonce_slug )

  		);


      // create state args
      $state = base64_encode( json_encode( $state_args ) );



      // request arguments
      $args = $this->request_url_args( array(



        'client_id' => $this->appcredentials['client_id'],

        'response_type' => 'code',

        'scope' => '',

        'redirect_uri' => admin_url( 'admin-ajax.php' ),

        'state' => $state

      ) );


      // create request url
      $status['request_url'] = add_query_arg( urlencode_deep( $args ), $this->request_url_base );


      return $status;

    }




    /*
    *  Helper function to give child class more flexibility without exposing data to a Wordpress Filter
    */

    protected function request_url_args( $args ) {
      return $args;
    }




    /*
    * helper to change request status
    */

    protected function _status( $status, $status_status, $status_code = '' ) {

      // set status to failed
      $status['status']       = $status_status;
      $status['status_code']  = $status_code;

      return $status;
    }




    /*
    * helper to change request status
    */

    protected function _failed( $status, $status_code ) {

      return $this->_status( $status, 'failed', $status_code );
    }




    /*
     *  Check for default oauth error
     *
     */

    function check_response_oauth_error( $status ) {


      // bail if status was already modified
      if( 'pending' !== $status['status'] )
        return $status;


      // no error found
      if( ! isset( $_GET['error'] ) && !isset( $_GET['error_reason'] ) )
        return $status;

      return $this->_failed( $status, 'denied' );

    }




    /*
    * split job by version
    */

    function check_response_exchange( $status ) {

      if( '1' === $this->oauth_version() )
        return $this->check_response_exchange_v1( $status );
      else
        return $this->check_response_exchange_v2( $status );
    }




    /*
    * check the response from the service and edit the $status accordingly.
    *
    * in case of success:
    *    - set $status['status'] = 'verified'
    *    - save encrypted API credentials to $status['credentials']
    *
    * in case of error:
    *
    *    - set $status['status'] = 'failed'
    *    - optional provide a 'status_code'
    *
    * @param $status (array) - default 'status' is 'pending'
    */

    protected function check_response_exchange_v2( $status ) {


      // bail if status was already modified
      if( 'pending' !== $status['status'] )
        return $status;
        

      if( !isset( $_GET['code'] ) )
        return $this->_failed( $status, 'bad-request' );


      $args = array(

        'client_id'     => $this->appcredentials['client_id'],

        'client_secret' => $this->appcredentials['client_secret'],

        'grant_type'    => 'authorization_code',

        'redirect_uri'  => admin_url( 'admin-ajax.php' ),

        'code'          => $_GET['code']

      );

      $exchange_args = $this->exchange_args( $args, $status );


      // request access token
      $response = wp_remote_post(

        $this->exchange_url_base,

        array(
          'body' => $exchange_args
        )

      );


      // check if remote request was not successful
      if( $response instanceof WP_Error )
        return $this->_failed( $status, 'connection-error' );

      if( $response['response']['code'] != '200' )
        return $this->_failed( $status, 'denied-request'  );


      // try to parse the response as json
      $exchange_data = json_decode( wp_remote_retrieve_body( $response ), true );


      // check if data is vaild json
      if( json_last_error() !== JSON_ERROR_NONE )
        return $this->_failed( $status, 'parse-response'  );


      $credentials = $this->create_credentials( false, $exchange_data, $status );


      if( false === $credentials )
        return $this->_failed( $status, 'no-credentials'  );


      $status['credentials'] = $credentials;


      return $this->_status( $status, 'verified', 'after-log-in' );
    }



    protected function check_response_exchange_v1( $status ) {


      // bail if status was already modified
      if( 'pending' !== $status['status'] )
        return $status;


      if( !isset( $_GET['oauth_verifier'] ) )
        return $this->_failed( $status, 'bad-request' );


      if( !isset( $status['_request_data'] ) )
        return $this->_failed( $status, 'bad-request' );


      $request_data = $this->decrypt_array( $status['_request_data'], 'request_data' );


      $oauth = array(

        'oauth_token'             => $request_data['response_v1']['oauth_token'],

        'oauth_consumer_key'      => $this->appcredentials['client_id'],

        'oauth_verifier'          => $_GET['oauth_verifier'],

        'oauth_nonce'             => wp_create_nonce( acf_plugin_oauth::$nonce_slug ),

        'oauth_signature_method'  => 'HMAC-SHA1',

        'oauth_timestamp'         => time(),

        'oauth_version'           => '1.0'

      );


      $exchange_args = $this->exchange_args( $oauth, $status );


      $oauth['oauth_signature'] = $this::create_signature( $this->exchange_url_base, $oauth,  array( $this->appcredentials['client_secret'], $status['_oauth_v1']['oauth_token_secret'] ) );



      // request access token
      $response = wp_remote_post(

        $this->exchange_url_base,

        array(
          'headers' => array(
            'Authorization' => $this::create_oauth_header( $oauth )
          ),
          'body' => array(
            'oauth_verifier'          => $_GET['oauth_verifier']
          )
        )

      );


      // check if remote request was not successful
      if( $response instanceof WP_Error )
        return $this->_failed( $status, 'connection-error' );


      if( $response['response']['code'] != '200' )
        return $this->_failed( $status, 'denied-request' );


      // data like an url querystring
      $data_str = wp_remote_retrieve_body( $response );


      $exchange_data = array();


      // try to parse the response
      parse_str( $data_str, $exchange_data );


      $credentials = $this->create_credentials( false, $exchange_data, $status );


      if( false === $credentials )
        return $this->_failed( $status, 'no-credentials' );


      $status['credentials'] = $credentials;
      $status['_exchange_data'] = $exchange_data;


      return $this->_status( $status, 'verified', 'after-log-in' );

    }




    /*
    *  Helper function to give child class more flexibility without exposing data to a Wordpress Filter
    */

    protected function exchange_args( $args, $status ) {
      return $args;
    }




    /*
    * save credentials from exchange response
    */

    protected function create_credentials( $false, $exchange_data, $status ) {



      if( '2' == $this->oauth_version() && is_array( $exchange_data ) ) {

        $credentials = array(
          'created' => time()
        );

        foreach( $exchange_data as $key => $value )
          if( in_array( $key, array( 'access_token', 'refresh_token', 'expires_in', 'id_token' ) ) )
            $credentials[ $key ] = $value;


        return $this->encrypt_array( $credentials );

      }


      elseif( '1' == $this->oauth_version() && is_array( $exchange_data ) &&  isset( $exchange_data['oauth_token'] ) && isset( $exchange_data['oauth_token_secret'] )  ) {

        return $this->encrypt_array( array(
          'created'             => time(),
          'oauth_token'         => $exchange_data['oauth_token'],
          'oauth_token_secret'  => $exchange_data['oauth_token_secret']
        ) );

      }


      return $false;

    }





    /*
    * helper creates Vcard Html from userdata
    */

    public function _create_vcard_html( $picture, $username, $displayname = false ) {



      $vcard = sprintf(
        '<img src="%1$s" class="avatar"/><span class="username">%2$s</span><span class="displayname">%3$s</span>',
        $picture,
        $username,
        $displayname ? $displayname : __( 'Account', 'acf-oauth' )
      );

      return $vcard;
    }




    /*
    * Create an OAuth v1 Signature
    *
    * @param $base_url (string) - the requested api endpoint
    * @param $args (array) - key/value request args
    * @param $secrets (string) - optional an user secret
    * @param $method (string) - the http method e.g. POST, GET, PUT, DELETE
    */

    static function create_signature( $base_url = '', $args = array(), $secrets = array(), $method = 'POST' ) {


      $keys   = array_map( 'rawurlencode', array_keys( $args ) );
      $values = array_map( 'rawurlencode', array_values( $args ) );
      $args   = array_combine( $keys, $values );

      // sort lexically
      ksort( $args );

      $pairs = array();

      foreach( $args as $key => $value )
        $pairs[] = $key .'='. $value;


      $args_output = implode( '&', $pairs );


      $signature_base_string = strtoupper( $method ) . '&' . rawurlencode( $base_url ) . '&' . rawurlencode( $args_output );


      $signing_key = implode( '&', $secrets );


      return base64_encode(
        hash_hmac( 'sha1', $signature_base_string, $signing_key, true)
      );

    }





    /*
    * Create an OAuth v1 Authentication Header
    *
    * @param $oauth_args (array) - list of key/value request args
    */

    static function create_oauth_header( $oauth_args ) {


      $pairs = array();


      foreach( $oauth_args as $key => $value )
        $pairs[] = rawurlencode( $key ) .'="'.rawurlencode( $value ).'"';


      return 'OAuth '.implode( ', ', $pairs );

    }




    /*
    * By default this function will automatically decrypt the credentials before returned by get_field
    */

    function format_value( $value, $post_id, $field ) {


      if( is_string( $value ) )
        $value = $this->decrypt_array( $value );



      return $value;

    }










    /*
    * Create a Key for encryption
    */

    static function _cryptkey( $cryptkey, $actionkey ) {

      // add installation specific key
      if( defined( 'ACF_OAUTH_KEY' ) )
        $cryptkey .= constant('ACF_OAUTH_KEY');
      elseif( defined( 'AUTH_KEY' ) )
        $cryptkey .= constant('AUTH_KEY');


      // action specific key
      $cryptkey .= $actionkey;


      return hash( 'sha256', $cryptkey );
    }


    // shorthand using cryptkey
    protected function cryptkey( $actionkey = '' ) {

      return acf_oauth_service::_cryptkey( $this->cryptkey, $actionkey );
    }


    /*
    * Encrypt a String, save it together with an IV, serialize and base64_encode to string
    *
    * @return base64 string
    */

    static function _encrypt( $string, $key ) {


      $iv = random_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );


      $ct = openssl_encrypt(
        $string,
        'aes-256-cbc',
        $key,
        0,
        $iv
      );


      $data = base64_encode( json_encode( array(
        'iv' => bin2hex( $iv ),
        'ct' => $ct
      ) ) );

      return $data;
    }




    /*
    * decrypt a String encrypted with _encrypt()
    *
    * @return base64 string
    */

    static function _decrypt( $string, $key ) {

      $cipher_data = json_decode( base64_decode( $string ), true );

      if( !is_array( $cipher_data ) || !isset( $cipher_data['iv'] ) || !isset( $cipher_data['ct'] ) )
        return;

      $data = openssl_decrypt(
        $cipher_data['ct'],
        'aes-256-cbc',
        $key,
        0,
        hex2bin( $cipher_data['iv'] )
      );

      return $data;
    }




    /*
    * encrypt an assoc array
    *
    * @return base64 string
    */

    static function _encrypt_array( $credentials, $key ) {

      if( !is_array( $credentials ) )
        return '';

      $credentials = json_encode( $credentials );
      $credentials = base64_encode( $credentials );
      $credentials = acf_oauth_service::_encrypt( $credentials, $key );

      return $credentials;
    }


    // shorthand including cryptkey
    protected function encrypt_array( $credentials, $action = 'credentials' ) {

      return acf_oauth_service::_encrypt_array( $credentials, $this->cryptkey( $action ) );
    }



    /*
    * decrypt an assoc array
    *
    * @return array
    */

    static function _decrypt_array( $credentials, $key ) {

      if( empty( $credentials ) )
        return false;

      $credentials = acf_oauth_service::_decrypt( $credentials, $key );
      $credentials = base64_decode( $credentials );
      $credentials = json_decode( $credentials, true );

      return $credentials;
    }


    // shorthand including cryptkey
    protected function decrypt_array( $credentials, $action = 'credentials' ) {

      return acf_oauth_service::_decrypt_array( $credentials, $this->cryptkey( $action ) );
    }



  }




