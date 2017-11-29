<?php


// exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;




if( !class_exists( 'acf_oauth_service_instagram' ) ):



  class acf_oauth_service_instagram extends acf_oauth_service {


    protected $cryptkey = 'VV.DC]!6*VmXz>YDDD9G-J|fk2Jt%SB.?sp2&dr|^-WpHw*VQH4tUwP8_j.!y=gr';


    function __construct( $by_pass_naming = false ) {

      if( ! $by_pass_naming ) {


        // the name/slug for the service
        $this->name = 'instagram';


        // a display name
        $this->label = __( 'Instagram', 'acf-oauth' );


        // button label with icon (Fontawesome)
        $this->button_label = __('<i class="fa fa-instagram"></i> Login with Instagram');
      }

      // base url where the user will be redirected to
      $this->request_url_base = 'https://api.instagram.com/oauth/authorize/';


      // base exchange url where we exchange the auth code for an access_token
      $this->exchange_url_base = 'https://api.instagram.com/oauth/access_token';


      // do not remove this
      parent::__construct();


    }




    /*
    * modify the request args
    *
    * [optional]
    *
    * @param $args (array) - list of key/value request args, defaults are client_id, response_type, redirect_uri, scope
    */

    function request_url_args( $args ) {

      $args['scope'] = 'basic';

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

      if( !isset( $credentials['access_token'] ) )
        return $no_vcard;


      $url = add_query_arg(
        'access_token',
        urlencode( $credentials['access_token'] ),
        'https://api.instagram.com/v1/users/self/'
      );


      $response = wp_remote_get( $url );


      if( $response instanceof WP_Error )
        return $no_vcard;


      if( '200' != wp_remote_retrieve_response_code( $response ) )
        return $no_vcard;


      $user = json_decode( wp_remote_retrieve_body( $response ) );


      return $this->_create_vcard_html( $user->data->profile_picture, $user->data->username, $user->data->full_name );

    }



  }

  new acf_oauth_service_instagram();

endif;

