<?php

if ( !defined('ABSPATH') )
	die ( 'YOU SHALL NOT PASS!' );

/**
 * This class handles creating the admin page and ACF fields
 *
 * Notes:
 * 		1) Separated the group and fields to keep it a bit more organized
 * 		2) To keep code organized, I added 'repeater' fields with the 'acf_add_local_field' function.
 * 		   Therefore, every 'sub_field' has to be added with the 'acf_add_local_field' function or else ACF throws a bitch fit
 */

class RM_Schema_ACF {

	// Instance of this class
	static $instance	= false;

	// Plugin slug
	static $plugin_slug	= 'rm-schema-options';

	// Plugin data
	static $plugin_data	= null;

	// Class variable to hold our ACF groups
	static $groups		= array();

	public function __construct() {

		// get our plugin options as serialized data as we saved into the theme options
		self::$plugin_data	= get_option( 'option_'. self::$plugin_slug );

		// Define our groups on construct
		self::$groups[]	= array(
			'key'			=> 'rm_site_schema',
			'title'			=> 'General Site/Business Schema',
			'menu_order'	=> 0
		);

		self::$groups[]	= array(
			'key'			=> 'rm_employees_schema',
			'title'			=> 'Employees Schema',
			'menu_order'	=> 1
		);

		self::$groups[]	= array(
			'key'			=> 'rm_review_schema',
			'title'			=> 'Review Schema',
			'menu_order'	=> 2
		);

		$this->create_admin_page();
		$this->add_groups();

		// add_action( 'acf/init', array( $this, 'add_groups' ) );
	}

	/**
	 * Singleton
	 *
	 * @return A single instance of the current class.
	 */
	public static function singleton() {

		if ( !self::$instance )
			self::$instance = new self();

		return self::$instance;

	}

	/**
	 * Create the settings page in the sidebar of the WP admin area
	 */
	public static function create_admin_page() {

		if ( function_exists('acf_add_options_page') ) {

			acf_add_options_page( array(
				'page_title' 	=> 'Rosemont Schema Options',
				'menu_title'	=> 'RM Schema',
				'menu_slug' 	=> self::$plugin_slug,
				'capability'	=> 'edit_posts',
				'redirect'		=> false
			) );

		}

	}

	/**
	 * Create our ACF groups dynamically
	 */
	public static function add_groups() {

		foreach ( self::$groups as $group ) {

			acf_add_local_field_group( array(
				'key'			=> $group['key'],
				'title'			=> $group['title'],
				'menu_order'	=> !empty( $group['menu_order'] ) ? $group['menu_order'] : 0,
				'location'		=> array(
					array(
						array(
							'param'		=> 'options_page',
							'operator'	=> '==',
							'value'		=> self::$plugin_slug,
						),
					),
				),
				'instruction_placement'	=> 'field'
			) );

			// calling each function based on the key of the group (dynamic purposes)
			$method_name	= 'add_fields_' . $group['key'];
			self::{"$method_name"}($group['key']);

		}

	}

	/**
	 * Create the main fields for each group in the functions below here
	 */
	public static function add_fields_rm_site_schema( $parent ) {

		/**
		 * Start Site Schema fields/options
		 */
		acf_add_local_field( array(
			'parent'	=> $parent,
			'key'		=> 'site_logo',
			'name'		=> 'site_logo',
			'label'		=> 'Site Logo',
			'type'		=> 'image',
			'return_value'	=> 'url',
			'mime_types'	=> 'jpg, png',
			'min_width'		=> 160,
			'min_height'	=> 90,
			'max_width'		=> 1920,
			'max_height'	=> 1080,
			'instructions'	=> 'Only jpg and png files accepted. <br>Min dimensions of image: 160px width by 90px height <br>Max dimensions of image: 1920px width by 1080px height'
		) );

		acf_add_local_field( array(
			'parent'	=> $parent,
			'key'		=> 'site_name',
			'name'		=> 'site_name',
			'label'		=> 'Site Name',
			'type'		=> 'text',
			'placeholder'	=> get_bloginfo('name'),
			'instructions'	=> 'If left blank, will default to the "Site Title" defined in WordPress admin area under General Settings. <br>Used for Organization and Website schema.'
		) );

		acf_add_local_field( array(
			'parent'	=> $parent,
			'key'		=> 'site_about',
			'name'		=> 'site_about',
			'label'		=> 'About Site',
			'type'		=> 'text',
			'placeholder'	=> get_bloginfo('description'),
			'instructions'	=> 'If left blank, will default to the "Site Tagline" defined in WordPress admin area under General Settings.'
		) );

		acf_add_local_field( array(
			'parent'	=> $parent,
			'key'		=> 'main_phone',
			'name'		=> 'main_phone',
			'label'		=> 'Phone Number',
			'type'		=> 'text',
			'placeholder'	=> '+1-555-555-5555',
		) );

		acf_add_local_field( array(
			'parent'	=> $parent,
			'key'		=> 'about_us_link',
			'name'		=> 'about_us_link',
			'label'		=> 'About Us Link',
			'type'		=> 'link',
		) );

		acf_add_local_field( array(
			'parent'	=> $parent,
			'key'		=> 'organization_type',
			'name'		=> 'organization_type',
			'label'		=> 'Organization Type',
			'type'		=> 'radio',
			'choices'	=> array(
				'Physician'				=> 'Physician',
				'Dentist'				=> 'Dentist'
			),
			'layout'		=> 'horizontal',
			'other_choice'	=> true
		) );

		// This should probably be replaced by defining social media URLs elsewhere, like theme settings but not all blogs have theme settings
		acf_add_local_field( array(
			'parent'	=> $parent,
			'key'		=> 'sameas',
			'name'		=> 'sameas',
			'label'		=> 'Related URLs (Social Media)',
			'type'		=> 'repeater',
			'layout'	=> 'row',
			'instruction'	=> 'Related sites/URLs and social media URLs that this website is related to.',
			'button_label'	=> 'Add URL',
		) );

		acf_add_local_field( array(
			'parent'	=> 'sameas',
			'key'		=> 'url',
			'name'		=> 'url',
			'label'		=> 'URL',
			'type'		=> 'url',
		) );

		// This will/should be moved to a plugin or combined with one that will work as a hub for all theme settings/options
		acf_add_local_field( array(
			'parent'	=> $parent,
			'key'		=> 'locations',
			'name'		=> 'locations',
			'label'		=> 'Locations',
			'type'		=> 'repeater',
			'layout'	=> 'row',
			'button_label'	=> 'Add Location',
		) );

		// Doing this separately to keep things organized
		self::add_location_sub_fields();

	}

	public static function add_fields_rm_employees_schema( $parent ) {

		acf_add_local_field( array(
			'parent'	=> $parent,
			'key'		=> 'employees',
			'name'		=> 'employees',
			'label'		=> 'Employees',
			'type'		=> 'repeater',
			'layout'	=> 'row',
			'button_label'	=> 'Add Employee',
		) );

		// Doing this separately to keep things organized
		self::add_employee_sub_fields();

	}

	public static function add_fields_rm_review_schema( $parent ) {

		/**
		 * Start Review Schema fields/options
		 */
		acf_add_local_field( array(
			'parent'	=> $parent,
			'key'		=> 'reviews_status',
			'name'		=> 'reviews_status',
			'label'		=> 'Reviews URL',
			'type'		=> 'radio',
			'layout'	=> 'horizontal',
			'choices'	=> array(
				'off'	=> 'Off',
				'on'	=> 'On',
			),
			'default_value'	=> 'off',
			'instructions'	=> 'If status is "off", none of the below will show up on schema or markup. <br><strong>Note for DEV:</strong> Insert "<strong>do_action(\'reviews_markup\')</strong>" in a theme template to display the Reviews Schema (99% of the time you want to put this in the "<strong>footer.php</strong>" template).'
		) );
		acf_add_local_field( array(
			'parent'	=> $parent,
			'key'		=> 'reviews_url',
			'name'		=> 'reviews_url',
			'label'		=> 'Reviews URL',
			'type'		=> 'url',
			'instructions'	=> 'Insert full URL of link. If the URL lives on the same site, will automatically open in same browser tab, else will open in new tab.'
		) );

		acf_add_local_field( array(
			'parent'	=> $parent,
			'key'		=> 'rating_value',
			'name'		=> 'rating_value',
			'label'		=> 'Star Rating / Rating Value',
			'type'		=> 'number',
			'min'		=> 0,
			'max'		=> 5,
			'step'		=> '0.1',
			'placeholder'	=> '0.0',
			'default_value'	=> '0.0',
			'instructions'	=> 'This will appear as an actual number in the mark up.'
		) );

		acf_add_local_field( array(
			'parent'	=> $parent,
			'key'		=> 'reviews_count',
			'name'		=> 'reviews_count',
			'label'		=> 'Reviews Count',
			'type'		=> 'number',
			'min'		=> 1,
			'placeholder'	=> '0',
			'default_value'	=> 1,
			'instructions'	=> 'The number of reviews for the front-end markup and schema markup.'
		) );

		acf_add_local_field( array(
			'parent'	=> $parent,
			'key'		=> 'reviews_schema_location',
			'name'		=> 'reviews_schema_location',
			'label'		=> 'Reviews Schema Location',
			'type'		=> 'radio',
			'choices'	=> array(
				'organization'	=> 'Organization',
				'person'		=> 'Physician/Dentist',
			),
			'default_value'	=> 'organization',
			'instructions'	=> 'Determine where/what to append Reviews Schema to in Schema markup.'
		) );

	}

	/**
	 * Create sub fields for repeaters in the functions below
	 */
	public static function add_location_sub_fields() {

		acf_add_local_field( array(
			'parent'	=> 'locations',
			'key'		=> 'business_name',
			'name'		=> 'business_name',
			'label'		=> 'Business Name',
			'type'		=> 'text',
		) );

		acf_add_local_field( array(
			'parent'	=> 'locations',
			'key'		=> 'street_address',
			'name'		=> 'street_address',
			'label'		=> 'Street Address',
			'type'		=> 'text',
		) );

		acf_add_local_field( array(
			'parent'	=> 'locations',
			'key'		=> 'city',
			'name'		=> 'city',
			'label'		=> 'City',
			'type'		=> 'text',
		) );

		acf_add_local_field( array(
			'parent'	=> 'locations',
			'key'		=> 'state',
			'name'		=> 'state',
			'label'		=> 'State',
			'type'		=> 'text',
			'maxlength'	=> 2
		) );

		acf_add_local_field( array(
			'parent'	=> 'locations',
			'key'		=> 'zipcode',
			'name'		=> 'zipcode',
			'label'		=> 'Zipcode',
			'type'		=> 'number',
			'maxlength'	=> 6
		) );

		acf_add_local_field( array(
			'parent'	=> 'locations',
			'key'		=> 'country',
			'name'		=> 'country',
			'label'		=> 'Country',
			'type'		=> 'text',
			'instructions'	=> 'Only really necessary if outside of US.'
		) );

		acf_add_local_field( array(
			'parent'	=> 'locations',
			'key'		=> 'phone',
			'name'		=> 'phone',
			'label'		=> 'Phone Number',
			'type'		=> 'text',
			'placeholder'	=> '+1-555-555-5555',
			'instructions'	=> 'If left blank, will default to the general number above.'
		) );

		acf_add_local_field( array(
			'parent'		=> 'locations',
			'key'			=> 'latitude',
			'name'			=> 'latitude',
			'label'			=> 'Latitude',
			'type'			=> 'number',
			'min'			=> -90,
			'max'			=> 90,
			'placeholder'	=> '000.0000'
		) );

		acf_add_local_field( array(
			'parent'		=> 'locations',
			'key'			=> 'longitude',
			'name'			=> 'longitude',
			'label'			=> 'Longitude',
			'type'			=> 'number',
			'min'			=> -180,
			'max'			=> 180,
			'placeholder'	=> '000.0000'
		) );

	}

	public static function add_employee_sub_fields() {

		acf_add_local_field( array(
			'parent'	=> 'employees',
			'key'		=> 'name',
			'name'		=> 'name',
			'label'		=> 'Name',
			'type'		=> 'text',
		) );

		acf_add_local_field( array(
			'parent'	=> 'employees',
			'key'		=> 'job_title',
			'name'		=> 'job_title',
			'label'		=> 'Job Title',
			'type'		=> 'radio',
			'choices'	=> array(
				'Physician'				=> 'Physician',
				'Dentist'				=> 'Dentist',
				'Plastic Surgeon'		=> 'Plastic Surgeon',
				'Ophthalmologist'		=> 'Ophthalmologist',
				'Dermatologist'			=> 'Dermatologist',
				'Orthopaedic Surgeon'	=> 'Orthopaedic Surgeon',
				'Bariatric Surgeon'		=> 'Bariatric Surgeon',
			),
			'layout'		=> 'horizontal',
			'other_choice'	=> true
		) );

		acf_add_local_field( array(
			'parent'	=> 'employees',
			'key'		=> 'image',
			'name'		=> 'image',
			'label'		=> 'Image',
			'type'		=> 'image',
			'return_value'	=> 'url',
			'mime_types'	=> 'jpg, png',
			'instructions'	=> 'Only jpg and png files accepted'
		) );

		acf_add_local_field( array(
			'parent'	=> 'employees',
			'key'		=> 'phone',
			'name'		=> 'phone',
			'label'		=> 'Phone Number',
			'type'		=> 'text',
			'placeholder'	=> '+1-555-555-5555',
			'instructions'	=> 'If left blank, will default to business number.'
		) );

		acf_add_local_field( array(
			'parent'	=> 'employees',
			'key'		=> 'link',
			'name'		=> 'link',
			'label'		=> 'Link',
			'type'		=> 'link',
		) );

	}

}
