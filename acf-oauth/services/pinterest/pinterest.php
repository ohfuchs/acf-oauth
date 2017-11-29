<?php


// exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;




if( !class_exists( 'acf_oauth_service_pinterest' ) ):



  class acf_oauth_service_pinterest extends acf_oauth_service {


    protected $cryptkey = '->v9-M3&0kjS|;IE.bHKA)B+|z$DGO>}Q&~2)EBijI?s%[*!loW5i/yYaUhTaUMl';


    function __construct( $by_pass_naming = false ) {


      if( ! $by_pass_naming ) {


        // the name/slug for the service
        $this->name = 'pinterest';


        // a display name
        $this->label = __( 'Pinterest', 'acf-oauth' );


        // button label with icon (Fontawesome)
        $this->button_label = '<i class="fa fa-pinterest"></i> '.sprintf( __('Login with %s', 'acf-oauth' ), $this->label );

      }


      // base url where the user will be redirected to
      $this->request_url_base = 'https://api.pinterest.com/oauth/';


      // base exchange url where we exchange the auth code for an access_token
      $this->exchange_url_base = 'https://api.pinterest.com/v1/oauth/token';


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

      $args['scope'] = 'read_public';

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
        array(
          'access_token' => urlencode( $credentials['access_token'] ),
          'fields' => urlencode( 'first_name,last_name,image,id,username' ),
        ),
        'https://api.pinterest.com/v1/me'
      );


      $response = wp_remote_get( $url );


      if( $response instanceof WP_Error )
        return $no_vcard;


      if( '200' != wp_remote_retrieve_response_code( $response ) )
        return $no_vcard;

      //  var_dump(  wp_remote_retrieve_body( $response ));

      $user = json_decode( wp_remote_retrieve_body( $response ) );

      $res = '60x60';
      return $this->_create_vcard_html( $user->data->image->$res->url, $user->data->username, $user->data->first_name . ' ' . $user->data->last_name );

    }



  }

  new acf_oauth_service_pinterest();

endif;

