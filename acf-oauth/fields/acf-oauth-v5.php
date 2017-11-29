<?php

// todo: add validation for existing tokens

// exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;


// check if class already exists
if( !class_exists('acf_field_oauth') ) :


class acf_field_oauth extends acf_field {


	/*
	*  __construct
	*
	*  This function will setup the field type data
	*
	*  @type	function
	*  @date	5/03/2014
	*  @since	5.0.0
	*
	*  @param	n/a
	*  @return	n/a
	*/

	function __construct( $settings ) {

		/*
		*  name (string) Single word, no spaces. Underscores allowed
		*/

		$this->name = 'oauth';


		/*
		*  label (string) Multiple words, can include spaces, visible when selecting a field type
		*/

		$this->label = __('OAuth Authentication', 'acf-oauth');


		/*
		*  category (string) basic | content | choice | relational | jquery | layout | CUSTOM GROUP NAME
		*/

		$this->category = __('OAuth Authentication', 'acf-oauth');


		/*
		*  defaults (array) Array of default settings which are merged into the field object. These are used later in settings
		*/

		$this->defaults = array(
			'oauth-service'	=> 'none',
			'oauth-button-label'	=> '',
		);


		/*
		*  l10n (array) Array of strings that are used in JavaScript. This allows JS strings to be translated in PHP and loaded via:
		*  var message = acf._e('oauth', 'error');
		*/

		$this->l10n = array(
			'error'	=> __('Error! Please enter a higher value', 'acf-oauth'),
		);


		/*
		*  settings (array) Store plugin settings (url, path, version) as a reference for later use with assets
		*/

		$this->settings = $settings;


		add_filter( 'acf/prepare_field/type=oauth', array( $this, 'prepare_field' ) );


		// do not delete!
    	parent::__construct();

	}


	/*
	*  prepare_field()
	*
	*  Prevent rendering of fields with disabled service, also hides fields for service that was formerly active
	*/

	function prepare_field( $field ) {

		return acf_plugin_oauth::is_service( $field['oauth-service'] ) ? $field : false;
	}


	/*
	*  render_field_settings()
	*
	*  Create extra settings for your field. These are visible when editing a field
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field (array) the $field being edited
	*  @return	n/a
	*/

	function render_field_settings( $field ) {

		/*
		*  acf_render_field_setting
		*
		*  This function will create a setting for your field. Simply pass the $field parameter and an array of field settings.
		*  The array of settings does not require a `value` or `prefix`; These settings are found from the $field array.
		*
		*  More than one setting can be added by copy/paste the above code.
		*  Please note that you must also have a matching $defaults value for the field name (font_size)
		*/

		$service_list = apply_filters('acf-oauth/services', array( ) );

		$needs_configuration = 0;

		array_walk( $service_list, function( &$label, $service ) use ( &$needs_configuration ){
			if( ! acf_plugin_oauth::is_service( $service ) ) {

				$label .= ' ('.__('needs configuration','acf-oauth').')';
				$needs_configuration++;
			}
		});


		acf_render_field_setting( $field, array(
			'label'			=> __('OAuth Service','acf-oauth'),
			//'instructions'	=> __('Select OAuth Service','acf-oauth'),
			'type'			=> 'select',
			'name'			=> 'oauth-service',
			'required' => true,
			'choices'  => $service_list
		));


		// add a note to the ui if any service needs configuration
		if( 0 < $needs_configuration ) {

			acf_render_field_setting( $field, array(
				'label'			=> '',
				//'instructions'	=> __('Select OAuth Service','acf-oauth'),
				'type'			=> 'message',
				'name'			=> 'oauth-message-needs-configuration',
				'message' => '<strong><small>'.
						__( 'One or more services require additional configuration. Usualy a service needs a Client/App Key to work. Please check the docs for further information.', 'acf-oauth' ).
						'</strong></small>'
			));
		}

		acf_render_field_setting( $field, array(
			'label'			=> __('Button Label','acf-oauth'),
			'instructions'	=> __('Label for the Login Button','acf-oauth'),
			'type'			=> 'text',
			'name'			=> 'oauth-button-label',
		));

	}



	/*
	*  render_field()
	*
	*  Create the HTML interface for your field
	*
	*  @param	$field (array) the $field being rendered
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field (array) the $field being edited
	*  @return	n/a
	*/

	function render_field( $field ) {

		/*
		*  Review the data of $field.
		*  This will show what data is available

		echo '<pre>';
		var_dump( $field );
		echo '</pre>';

		*/

		?>
		<div class="acf-oauth-wrapper"  data-oauth-service="<?php echo esc_attr( $field['oauth-service'] ) ?>" data-oauth-uuid="<?php echo acf_plugin_oauth::_uuid(); ?>">

			<div class="acf-oauth-vcard hidden">
				<div class="vcard"></div>
				<div class="acf-oauth-trash-account"><i class="fa fa-trash"></i></div>
			</div>

			<a class="disabled acf-oauth-verify button button-secondary">
				<?php

						if( !empty( $field['oauth-button-label'] ) )
							echo $field['oauth-button-label'];
						else
							echo apply_filters(	'acf-oauth/service/'.$field['oauth-service'].'/button-label', __( 'Login', 'acf-oauth' )	);

					?>
			</a>

			<div class="acf-oauth-field-group">

				<input type="hidden" name="<?php echo esc_attr($field['name']); ?>" value="<?php echo $field['value'] ?>"	 />

			</div>
		</div>
		<?php

	}


	/*
	*  input_admin_enqueue_scripts()
	*
	*  This action is called in the admin_enqueue_scripts action on the edit screen where your field is created.
	*  Use this action to add CSS + JavaScript to assist your render_field() action.
	*
	*  @type	action (admin_enqueue_scripts)
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	n/a
	*  @return	n/a
	*/



	function input_admin_enqueue_scripts() {

		// vars
		$url = $this->settings['url'];
		$version = $this->settings['version'];


		// register & include JS
		wp_register_script( 'acf-input-oauth', "{$url}assets/js/input.js", array('acf-input'), $version );
		wp_enqueue_script('acf-input-oauth');
		wp_localize_script( 'acf-input-oauth', 'acf_oauth', array(

			'ajaxurl' 		=> admin_url('admin-ajax.php'),
			'assets' 			=> "{$url}assets/",
			'_oauthnonce' => wp_create_nonce('acf-oauth-1'),
			'lang' 				=> array(
				'status_list' => acf_plugin_oauth::get_status_list()
			)

		));


		// register & include CSS
		wp_register_style( 'font-awesome', "https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css", array(), '4.7.0' );
		wp_register_style( 'acf-input-oauth', "{$url}assets/css/input.css", array('acf-input', 'font-awesome'), $version );
		wp_enqueue_style('acf-input-oauth');

	}




	/*
	*  input_admin_head()
	*
	*  This action is called in the admin_head action on the edit screen where your field is created.
	*  Use this action to add CSS and JavaScript to assist your render_field() action.
	*
	*  @type	action (admin_head)
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	n/a
	*  @return	n/a
	*/

	/*

	function input_admin_head() {



	}

	*/


	/*
   	*  input_form_data()
   	*
   	*  This function is called once on the 'input' page between the head and footer
   	*  There are 2 situations where ACF did not load during the 'acf/input_admin_enqueue_scripts' and
   	*  'acf/input_admin_head' actions because ACF did not know it was going to be used. These situations are
   	*  seen on comments / user edit forms on the front end. This function will always be called, and includes
   	*  $args that related to the current screen such as $args['post_id']
   	*
   	*  @type	function
   	*  @date	6/03/2014
   	*  @since	5.0.0
   	*
   	*  @param	$args (array)
   	*  @return	n/a
   	*/

   	/*

   	function input_form_data( $args ) {



   	}

   	*/


	/*
	*  input_admin_footer()
	*
	*  This action is called in the admin_footer action on the edit screen where your field is created.
	*  Use this action to add CSS and JavaScript to assist your render_field() action.
	*
	*  @type	action (admin_footer)
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	n/a
	*  @return	n/a
	*/

	/*

	function input_admin_footer() {



	}

	*/


	/*
	*  field_group_admin_enqueue_scripts()
	*
	*  This action is called in the admin_enqueue_scripts action on the edit screen where your field is edited.
	*  Use this action to add CSS + JavaScript to assist your render_field_options() action.
	*
	*  @type	action (admin_enqueue_scripts)
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	n/a
	*  @return	n/a
	*/

	/*

	function field_group_admin_enqueue_scripts() {

	}

	*/


	/*
	*  field_group_admin_head()
	*
	*  This action is called in the admin_head action on the edit screen where your field is edited.
	*  Use this action to add CSS and JavaScript to assist your render_field_options() action.
	*
	*  @type	action (admin_head)
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	n/a
	*  @return	n/a
	*/

	/*

	function field_group_admin_head() {

	}

	*/


	/*
	*  load_value()
	*
	*  This filter is applied to the $value after it is loaded from the db
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value (mixed) the value found in the database
	*  @param	$post_id (mixed) the $post_id from which the value was loaded
	*  @param	$field (array) the field array holding all the field options
	*  @return	$value
	*/



	function load_value( $value, $post_id, $field ) {


		$value = apply_filters( 'acf-oauth/service/'.$field['oauth-service'].'/load_value', $value, $post_id, $field );


		// return
		return $value;

	}




	/*
	*  update_value()
	*
	*  This filter is applied to the $value before it is saved in the db
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value (mixed) the value found in the database
	*  @param	$post_id (mixed) the $post_id from which the value was loaded
	*  @param	$field (array) the field array holding all the field options
	*  @return	$value
	*/



	function update_value( $value, $post_id, $field ) {


		$value = apply_filters( 'acf-oauth/service/'.$field['oauth-service'].'/update_value', $value, $post_id, $field );


		// return
		return $value;

	}




	/*
	*  format_value()
	*
	*  This filter is appied to the $value after it is loaded from the db and before it is returned to the template
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value (mixed) the value which was loaded from the database
	*  @param	$post_id (mixed) the $post_id from which the value was loaded
	*  @param	$field (array) the field array holding all the field options
	*
	*  @return	$value (mixed) the modified value
	*/



	function format_value( $value, $post_id, $field ) {


		$value = apply_filters( 'acf-oauth/service/'.$field['oauth-service'].'/format_value', $value, $post_id, $field );


		// return
		return $value;

	}




	/*
	*  validate_value()
	*
	*  This filter is used to perform validation on the value prior to saving.
	*  All values are validated regardless of the field's required setting. This allows you to validate and return
	*  messages to the user if the value is not correct
	*
	*  @type	filter
	*  @date	11/02/2014
	*  @since	5.0.0
	*
	*  @param	$valid (boolean) validation status based on the value and the field's required setting
	*  @param	$value (mixed) the $_POST value
	*  @param	$field (array) the field array holding all the field options
	*  @param	$input (string) the corresponding input name for $_POST value
	*  @return	$valid
	*/



	function validate_value( $valid, $value, $field, $input ){


		$valid = apply_filters( 'acf-oauth/service/'.$field['oauth-service'].'/validate_value', $valid, $value, $post_id, $field );


		// return
		return $valid;

	}




	/*
	*  delete_value()
	*
	*  This action is fired after a value has been deleted from the db.
	*  Please note that saving a blank value is treated as an update, not a delete
	*
	*  @type	action
	*  @date	6/03/2014
	*  @since	5.0.0
	*
	*  @param	$post_id (mixed) the $post_id from which the value was deleted
	*  @param	$key (string) the $meta_key which the value was deleted
	*  @return	n/a
	*/

	/*

	function delete_value( $post_id, $key ) {



	}

	*/


	/*
	*  load_field()
	*
	*  This filter is applied to the $field after it is loaded from the database
	*
	*  @type	filter
	*  @date	23/01/2013
	*  @since	3.6.0
	*
	*  @param	$field (array) the field array holding all the field options
	*  @return	$field
	*/

	/*

	function load_field( $field ) {

		return $field;

	}

	*/


	/*
	*  update_field()
	*
	*  This filter is applied to the $field before it is saved to the database
	*
	*  @type	filter
	*  @date	23/01/2013
	*  @since	3.6.0
	*
	*  @param	$field (array) the field array holding all the field options
	*  @return	$field
	*/

	/*

	function update_field( $field ) {

		return $field;

	}

	*/


	/*
	*  delete_field()
	*
	*  This action is fired after a field is deleted from the database
	*
	*  @type	action
	*  @date	11/02/2014
	*  @since	5.0.0
	*
	*  @param	$field (array) the field array holding all the field options
	*  @return	n/a
	*/

	/*

	function delete_field( $field ) {



	}

	*/


}


// initialize
new acf_field_oauth( $this->settings );


// class_exists check
endif;

?>