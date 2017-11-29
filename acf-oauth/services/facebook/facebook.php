<?php


// exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;




if( !class_exists( 'acf_oauth_service_facebook' ) ):



  class acf_oauth_service_facebook extends acf_oauth_service {


    protected $cryptkey = 'fq@vkdS(iw}Ry&L=n#>qbp}:t0%3j]&hbnCUYT3WR:t0?7e1(<-i&+;bAa~zqfM.';



    function __construct( $by_pass_naming = false ) {

      if( ! $by_pass_naming ) {


        // the name/slug for the service
        $this->name = 'facebook';


        // a display name
        $this->label = __( 'Facebook', 'acf-oauth' );


        // button label with icon (Fontawesome)
        $this->button_label = __('<i class="fa fa-facebook-square"></i> Login with Facebook');

      }


      // base url where the user will be redirected to
      $this->request_url_base = 'https://www.facebook.com/v2.11/dialog/oauth';


      // base exchange url where we exchange the auth code for an access_token
      $this->exchange_url_base = 'https://graph.facebook.com/v2.11/oauth/access_token';


      // do not remove this
      parent::__construct();

    }


    /*
    * modify the request args
    *
    * @param $args (array) - list of key/value request args, defaults are client_id, response_type, redirect_uri, scope
    */

    function request_url_args( $args ) {

      $args['scope'] = 'public_profile';

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
        'https://graph.facebook.com/me?fields=id,name,picture'
      );


      $response = wp_remote_get( $url );


      if( $response instanceof WP_Error )
        return $no_vcard;


      if( '200' != wp_remote_retrieve_response_code( $response ) )
        return $no_vcard;


      $user = json_decode( wp_remote_retrieve_body( $response ) );


      return $this->_create_vcard_html( $user->picture->data->url, $user->name );

    }

  }

  new acf_oauth_service_facebook();

endif;

