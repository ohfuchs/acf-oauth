<?php


// exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;




if( !class_exists( 'acf_oauth_service_twitter' ) ):



  class acf_oauth_service_twitter extends acf_oauth_service {


    protected $cryptkey = '=;+otC@|+>T[CyQO:5w[cu^xobvq+c)|+ dM]53RlVZclV*JsZI!IuE=k0s2{SAZ';


    function __construct( $by_pass_naming = false ) {

      if( ! $by_pass_naming ) {


        // the name/slug for the service
        $this->name = 'twitter';


        // a display name
        $this->label = __( 'Twitter', 'acf-oauth' );

        
        // button label with icon (Fontawesome)
        $this->button_label = __('<i class="fa fa-twitter"></i> Login with Twitter');


      }


      // v1 only token request
      $this->request_url_oauth_token = 'https://api.twitter.com/oauth/request_token';


      // base url where the user will be redirected to
      $this->request_url_base = 'https://api.twitter.com/oauth/authorize';


      // base exchange url where we exchange the auth code for an access_token
      $this->exchange_url_base = 'https://api.twitter.com/oauth/access_token';


      $this->oauth_version = '1';


      // do not remove this
      parent::__construct();

    }




    /*
    * modify the request args
    *
    * @param $args (array) - list of key/value request args, defaults are client_id, response_type, redirect_uri, scope
    */

    function request_url_args( $args ) {

      return $args;

    }




    /*
    * make an basic API call to retrieve some information about the user
    *
    * @param $no_vcard (false,string) - default to false, HTML String with user vcard
    * @param $credentials (array) - the saved credentials you need to make an API call
    */

    function vcard( $no_vcard, $credentials  ) {


      $credentials = $this->decrypt_array( $credentials );

      if( false === $credentials )
        return $no_vcard;


      if( !isset( $credentials['oauth_token'] ) )
        return $no_vcard;


      $url = 'https://api.twitter.com/1.1/account/verify_credentials.json';


      $oauth = array(

        //'realm'                   => 'https://api.twitter.com/',


        'oauth_consumer_key'      => $this->appcredentials['client_id'],

        'oauth_nonce'             => wp_create_nonce( acf_plugin_oauth::$nonce_slug ),

        'oauth_signature_method'  => 'HMAC-SHA1',

        'oauth_timestamp'         => time(),

        'oauth_token'             => $credentials['oauth_token'],

        'oauth_version'           => '1.0'

      );


      $oauth['oauth_signature'] = $this::create_signature(
        $url,
        $oauth,
        array( $this->appcredentials['client_secret'], $credentials['oauth_token_secret'] ),
        'GET'
      );


      $response = wp_remote_get( $url, array(
        'headers' => array(
          'Authorization' => $this::create_oauth_header( $oauth )
        ),
      ) );



      if( $response instanceof WP_Error )
        return $no_vcard;


      if( '200' != wp_remote_retrieve_response_code( $response ) )
        return $no_vcard;


      $user = json_decode( wp_remote_retrieve_body( $response ) );


      return $this->_create_vcard_html( $user->profile_image_url_https, '@'.$user->screen_name, $user->name );

    }

  }

  new acf_oauth_service_twitter();

endif;

