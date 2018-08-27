<?php

/*
Plugin Name: Advanced Custom Fields: OAuth Service Plugin
Plugin URI:
Description: Adds new Service to ACF OAuth
Version: 1.0.0
*/


// exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;


// use a value of 10 to include a new Service or extend a Built-In Service
// use a value of 8 to replace a Built-In Service
$priority = 10;


// hook
add_action( 'acf-oauth/include_services', 'service_template_include_service', $priority );


// include your Service Class
function service_template_include_service() {


  include_once( plugin_dir_path( __FILE__ ).'service/service.php' );
}