<?php


// exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;




if( !class_exists( 'acf_oauth_service_template' ) ):




  final class acf_oauth_service_template extends acf_oauth_service {




    function __construct( ) {


      // the name/slug for the service
      $this->name = 'service-template';


      // a display name
      $this->label = __( 'Service Template', 'acf-oauth' );


      // a key unique to this class
      // https://api.wordpress.org/secret-key/1.1/salt/
      $this->cryptkey = 'add-your-cryptkey-here';


      // the OAuth Protocol Version. 1 or 2
      $this->oauth_version = '2';


      // v1 only, url for initial token request
      //$this->request_url_oauth_token = 'https://example.com/oauth/request_token';


      // base url where the user will be redirected to
      $this->request_url_base = 'https://example.com/oauth/authorize/';


      // base exchange url where we exchange the auth code for an access_token
      $this->exchange_url_base = 'https://example.com/oauth/access_token';


      // setup your app/developer credentials
      $this->appcredentials = array(
        'client_id'     => '1234123412341234123412341234123412341234',
        'client_secret' => '123412341-234123412341234123-4123412341234'
      );


      // do not remove this
      parent::__construct();


      // remove the default decryption, instead use a custom function
      //remove_all_filters( 'acf-oauth/service/'.$this->name.'/format_value' );


    }


    /*
    * override parent format_value so another instance of this class could not use this function
    * to decrypt credentials
    */
    /*
    function format_value( $value, $post_id, $field ) {

      return $value;
    }
    */


    /*
    * return true|false whether or not the service is sufficiently
    * configured
    *
    * by default the service will check if $this->appcredentials contains
    * client_id and client_secret
    *
    * [optional]
    *
    * @param $false (bool) - the current activation state, default is false (not active)
    */

    /*
    function is_active( $false ) {
      // check if a apikey is set
      return isset( $this->appcredentials['api-key'] );
    }
    */




    /*
    * modify the request args, this is the right place to modify the requested scopes
    *
    * [optional]
    *
    * @param $args (array) - list of key/value request args, defaults are client_id, response_type, redirect_uri, scope
    */

    /*
    function request_url_args( $args ) {

      $args['scope'] = 'basic';

      return $args;

    }
    */




    /*
    *  modify the exchange args
    */

    /*
    protected function exchange_args( $args, $status ) {
      return $args;
    }
    */




    /*
    * Extract the required Credentials (access_token) from the exchanges response data
    *
    * @return false, encrypted credentials
    */

    /*
    protected function create_credentials( $false, $exchange_data, $status ) {



      if( is_array( $exchange_data ) ) {

        $credentials = array(
          'created' => time()
        );

        foreach( $exchange_data as $key => $value )
          if( in_array( $key, array( 'access_token', 'refresh_token', 'expires_in', 'id_token' ) ) )
            $credentials[ $key ] = $value;


        return $this->encrypt_array( $credentials );

      }

      return $false;

    }
    */



    /*
    * make an basic API call to retrieve some information about the user
    *
    * should return unmodified $no_vcard in case of any error and a small
    * html representation of the user in case of success
    *
    * @param $no_vcard (false,string) - default to false, HTML String with user vcard
    * @param $credentials (array) - the saved credentials you need to make an API call
    *
    * @return false, html string
    */

    function vcard( $no_vcard, $credentials  ) {


      $credentials = $this->decrypt_array( $credentials );


      if( false === $credentials )
        return $no_vcard;


      if( !isset( $credentials['access_token'] ) )
        return $no_vcard;



      // make a server request to gain some user information


      // use the Built-in function to generate vCard Html or create it by yourself
      //return $this->_create_vcard_html( $user->data->profile_picture, $user->data->username, $user->data->full_name );



      return $no_vcard;
    }



  }


  /* use a custom function to decrypt your credentials */
  /*
  function my_decrypt_func( $value ) {

    $cryptkey = my_service::_cryptkey( 'add-your-cryptkey-here', 'credentials' );


    return my_service::_decrypt_array( $value, $cryptkey );
  }
  */



  new acf_oauth_service_template();

endif;

