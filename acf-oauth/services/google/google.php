<?php


// exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;




if( !class_exists( 'acf_oauth_service_google' ) ):



  class acf_oauth_service_google extends acf_oauth_service {


    protected $cryptkey = 'o#H-El@,x-ws(odd)mz8iG$t8,$j/yAnZaN/+&v-BsAd%0*wgkqt:3_w BW(@4_U';


    function __construct( $by_pass_naming = false ) {

      if( ! $by_pass_naming ) {

        // the name/slug for the service
        $this->name = 'google';


        // a display name
        $this->label = __( 'Google', 'acf-oauth' );


        // button label with icon (Fontawesome)
        $this->button_label = __('<i class="fa fa-google"></i> Login with Google');

      }


      // base url where the user will be redirected to
      $this->request_url_base = 'https://accounts.google.com/o/oauth2/v2/auth';


      // base exchange url where we exchange the auth code for an access_token
      $this->exchange_url_base = 'https://www.googleapis.com/oauth2/v4/token';



      // check for expired token
      add_filter( 'acf-oauth/service/'.$this->name.'/load_value', array( $this, 'load_value' ), 10, 3 );


      // do not remove this
      parent::__construct();

    }



    /*
    * modify the request args
    *
    * @param $args (array) - list of key/value request args, defaults are client_id, response_type, redirect_uri, scope
    */

    function request_url_args( $args ) {

      $args['scope']            = 'profile';
      $args['access_type']      = 'offline';
      $args['prompt']           = 'consent';

      return $args;

    }



    /*
    * refresh access_token
    */

    function refresh_access_token( $credentials ) {


      if( !isset( $credentials['refresh_token'] ) )
        return false;



      $args = array(

        'client_id'     => $this->appcredentials['client_id'],

        'client_secret' => $this->appcredentials['client_secret'],

        'grant_type'    => 'refresh_token',

        'refresh_token'  => $credentials['refresh_token']

      );


      // request access token
      $response = wp_remote_post(

        $this->exchange_url_base,

        array(
          'body' => $args
        )

      );


      // check if remote request was not successful
      if( $response instanceof WP_Error )
        return false;


      if( $response['response']['code'] != '200' )
        return false;


      // try to parse the response as json
      $exchange_data = json_decode( wp_remote_retrieve_body( $response ), true );


      // check if data is vaild json
      if( json_last_error() !== JSON_ERROR_NONE )
        return false;


      $credentials['access_token'] = $exchange_data['access_token'];
      $credentials['expires_in']   = $exchange_data['expires_in'];
      $credentials['created']      = time();



      return $credentials;

    }



    /*
    *  Check if the Access_token requires a refresh
    */

    function load_value( $value, $post_id, $field ) {


      if( !is_admin() || empty( $value ) )
        return $value;


      $credentials = $this->decrypt_array( $value );

      if( empty( $credentials ) )
        return '';

      $expires_at = $credentials['created'] + $credentials['expires_in'];


      // access_token is most likely expired
      if( time() > $expires_at ) {

        $credentials = $this->refresh_access_token( $credentials );

        if( false === $credentials )
          $credentials = '';
        else
          $credentials = $this->encrypt_array( $credentials );

        update_field( $field['key'], $credentials, $post_id );
        return $credentials;
      }


      return $value;
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
        'https://www.googleapis.com/oauth2/v3/userinfo'
      );


      $response = wp_remote_get( $url );


      if( $response instanceof WP_Error )
        return $no_vcard;


      if( '200' != wp_remote_retrieve_response_code( $response ) )
        return $no_vcard;


      $user = json_decode( wp_remote_retrieve_body( $response ) );


      return $this->_create_vcard_html( $user->picture, $user->name );

    }


  }

  new acf_oauth_service_google();

endif;

