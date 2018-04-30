<?php

if ( !defined('ABSPATH') )
	die ( 'YOU SHALL NOT PASS!' );

/**
 * Gather and display all of our JSON-LD schema data on the front end
 */

class RM_Schema_JSON_LD {

	// Instance of this class
	static $instance	= false;

	// Plugin slug
	static $plugin_slug	= 'rm-schema-options';

	// Plugin data
	static $plugin_data	= NULL;

	// The schema payload that will be echoed out
	private $payload		= array();

	public function __construct() {

		// Set up our plugin data for the functions in this class to use and only one call gets made to the Cache/DB
		// This is one of the reasons why I decided to bypass the way ACF saves the options, one single call for all the plugin's data now
		self::$plugin_data	= get_option( 'option_'. self::$plugin_slug );

		add_action( 'wp_head', array( $this, 'add_custom_hook' ) );

		add_action( 'rm_json_ld', array( $this, 'collect_schema' ), 10 );

		// Insert the schema into the head now that it's been prepped
		// Give this last priority so schema is collected
		add_action( 'rm_json_ld', array( $this, 'output_head_schema' ), 99 );

		// Kill the Yoast schema
		add_filter( 'wpseo_json_ld_output', '__return_null' );

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
	 * Add a custom hook for future RM plugins or front end development to mess with
	 */
	public function add_custom_hook() {
		do_action( 'rm_json_ld' );
	}

	/**
	 * Run the functions to collect all our needed schema
	 */
	public function collect_schema() {

		// This is from the original set up by RM, I guess we are excluding schema from categories and archives? ¯\_(ツ)_/¯
		if ( is_page() || is_home() || is_front_page() || is_single() ) {
			$this->get_website_schema();
			$this->get_employees_schema();
			$this->get_locations_schema();
		}

		if ( is_single() ) {
			$this->get_single_post_schema();
		}

		/**
		 * Action to allow schema to be appended to our plugin data
		 */
		do_action( 'rm_add_schema', $this->payload );

	}

	public function get_website_schema() {

		global $post;

		// Declare some of our needed data
		$site_url	= get_bloginfo('url');
		$site_name	= !empty( self::$plugin_data['site_name'] ) ? self::$plugin_data['site_name'] : get_bloginfo('name');

		// Site description is determined by the following logic/priority:
		// 		1) Define natural WordPress "Site Tagline"
		// 		2) Overwrite Site Tagline if RM Schema Plugin "About Site" is not empty
		// 		3) Overwrite Site Tagline if All In One SEO plugin home description when it's the front page isn't empty
		// 		4) Overwrite Site Tagline if Yoast home description

		$site_about	= get_bloginfo('description');

		if ( !empty( self::$plugin_data['site_about'] ) ) {
			$site_about	= self::$plugin_data['site_about'];
		} elseif ( function_exists('aioseop_get_options') && !empty( aioseop_get_options()['aiosp_home_description'] ) ) {
			$site_about	= aioseop_get_options()['aiosp_home_description'];
		} elseif ( class_exists('WPSEO_Options') ) {
			$yoast_metadesc_home	= WPSEO_Options::get('metadesc-home-wpseo');
			$site_about = !empty( $yoast_metadesc_home ) ? $yoast_metadesc_home : $site_about;
		}

		$site_schema	= array();

		/**
		 * Main organization schema
		 */
		$organization_schema	= array(
			"@context"	=> "http://schema.org/",
			"@id"		=> get_bloginfo('url'),
			"@type"		=> "Organization",
			"name"		=> $site_name,
			"url"		=> $site_url
		);

		// Add image of site/organization if it was defined
		if ( !empty( self::$plugin_data['site_logo'] ) ) {

			$site_logo	= wp_get_attachment_image_src( self::$plugin_data['site_logo'], 'full' );

			$organization_schema['logo'] = array(
				"@type"	=> "ImageObject",
				"url"	=> $site_logo[0],
				"width"	=> $site_logo[1],
			);

		} // END check for site_logo

		// related URLs (social profiles)
		if ( !empty( self::$plugin_data['sameas'] ) ) {

			$sameas_schema	= array();

			foreach ( self::$plugin_data['sameas'] as $sameas ) {
				$sameas_schema[]	= $sameas['url'];
			}

			$organization_schema['sameAs']	= $sameas_schema;

		} // END check for sameas

		if ( !empty( self::$plugin_data['main_phone'] ) ) {

			$organization_schema['contactPoint'] = array(
				"telephone"		=> self::$plugin_data['main_phone'],
				"contactType"	=> "customer service"
			);

		} // END check for phone

		// Append the review schema here, if applicable
		if ( !empty( self::$plugin_data['reviews_schema_location'] ) && self::$plugin_data['reviews_schema_location'] == 'organization'	) {

			// one last check but also get the data and set to a variable inline
			if ( NULL !== $rating_schema = $this->get_rating_schema() ) {

				$organization_schema = array_merge( $organization_schema, $rating_schema );

			}

		}

		array_push( $site_schema, $organization_schema );


		// WEBSITE
		$website_schema	= array(
			"@context"	=> "http://schema.org/",
			"@type"		=> "WebSite",
			"url"		=> $site_url,
			"name"		=> $site_name,
			"about"		=> $site_about
		);

		// Add search functionality if desired
		if ( !empty( self::$plugin_data['sitelinks_searchbox'] ) ) {

			$website_schema['potentialAction'] = array(
				"@type"			=> "SearchAction",
				"target"		=> get_bloginfo('url').'/?s={search_term_string}',
				"query-input"	=> "required name=search_term_string"
			);

		}

		array_push( $site_schema, $website_schema );


		if ( !is_front_page() ) {

			// WEBPAGE
			$webpage_schema	= array(
				"@context"			=> "http://schema.org/",
				"@type"				=> "WebPage",
				"url"				=> get_the_permalink(),
				"headline"			=> get_the_title(),
				"mainEntityOfPage"	=> get_the_permalink(),
				"about"				=> get_the_excerpt()
			);

			array_push( $site_schema, $webpage_schema );

		} // End !is_front_page()

		if ( !empty( self::$plugin_data['organization_type'] ) ) {

			// in depth
			$in_depth_schema = array(
				"@context"	=> "http://schema.org/",
				"@id"		=> '#'. self::$plugin_data['organization_type'],
				"@type"		=> self::$plugin_data['organization_type'],
				"name"		=> $site_name,
			);

			if ( !empty( self::$plugin_data['about_us_link'] ) ) {
				$in_depth_schema['url']	= self::$plugin_data['about_us_link']['url'];
			}

			// Schema requires an image set and suggests/prefers a priceRange as well
			if ( !empty( self::$plugin_data['business_image'] ) ) {

				$business_image	= wp_get_attachment_image_src( self::$plugin_data['business_image'], 'large' );

				$in_depth_schema['image']	= $business_image[0]; // only URL needed

			} elseif ( !empty( self::$plugin_data['site_logo'] ) ) {

				// Just making absolutely sure the variable was defined further above
				// but if not, then define it
				if ( !isset( $site_logo ) ) {
					$site_logo		= wp_get_attachment_image_src( self::$plugin_data['site_logo'], 'full' );
					$site_logo_url	= $site_logo[0];
				} else {
					$site_logo_url	= $site_logo[0];
				}

				$in_depth_schema['image']	= $site_logo_url;
			}

			$in_depth_schema['priceRange']	= !empty( self::$plugin_data['price_range'] ) ? self::$plugin_data['price_range'] : 'Contact For Pricing';

			if ( !empty( self::$plugin_data['locations'] ) && is_array( self::$plugin_data['locations'] ) ) {

				foreach ( self::$plugin_data['locations'] as $key => $location ) {

					$single_location	= array(
						"@type"	=> "PostalAddress",
					);

					if ( !empty( $location['street_address'] ) ) {
						$single_location['streetAddress']	= $location['street_address'];
					}

					if ( !empty( $location['city'] ) ) {
						$single_location['addressLocality']	= $location['city'];
					}

					if ( !empty( $location['state'] ) ) {
						$single_location['addressRegion']	= $location['state'];
					}

					if ( !empty( $location['zipcode'] ) ) {
						$single_location['postalCode']	= $location['zipcode'];
					}

					if ( !empty( $location['country'] ) ) {
						$single_location['addressCountry']	= $location['country'];
					}

					if ( !empty( $location['phone'] ) || !empty( self::$plugin_data['main_phone'] ) ) {
						$single_location['telephone']	= !empty( $location['phone'] ) ? $location['phone'] : self::$plugin_data['main_phone'];
					}

					$in_depth_schema['address'][]	= $single_location;

				} // END foreach location

			} // END check for locations

			if ( !empty( self::$plugin_data['employees'] ) && is_array( self::$plugin_data['employees'] ) ) {

				foreach ( self::$plugin_data['employees'] as $employee ) {

					$in_depth_schema['employees'][]	= $employee['name'];

				} // END foreach employees

			} // END check for employees

			// Append the review schema here, if applicable
			if ( !empty( self::$plugin_data['reviews_schema_location'] ) && self::$plugin_data['reviews_schema_location'] == 'person' ) {

				// one last check but also get the data and set to a variable inline
				if ( NULL !== $rating_schema = $this->get_rating_schema() ) {

					$in_depth_schema = array_merge( $in_depth_schema, $rating_schema );

				}

			}

			array_push( $site_schema, $in_depth_schema );

		} // END check or organization_type

		// Merge site schema with the schema payload
		$this->payload	= array_merge( $this->payload, $site_schema );

	}

	public function get_employees_schema() {

		if ( !empty( self::$plugin_data['employees'] ) && is_array( self::$plugin_data['employees'] ) ) {

			foreach ( self::$plugin_data['employees'] as $key => $employee ) {
				$employee_schema = array(
					"@context"	=> "http://schema.org",
					"@type"		=> "Person",
					"jobTitle"	=> $employee['job_title'] // this is always present, no check if empty needed
				);

				if ( !empty( $employee['name'] ) ) {
					$employee_schema['name']	= $employee['name'];
				}

				if ( !empty( $employee['phone'] ) || !empty( self::$plugin_data['main_phone'] ) ) {
					$employee_schema['telephone']	= !empty( $employee['phone'] ) ? $employee['phone'] : self::$plugin_data['main_phone'];
				}

				if ( !empty( $employee['link']['url'] ) ) {
					$employee_schema['url']	= $employee['link']['url'];
				}

				// Add image of site/organization if it was defined
				if ( !empty( $employee['image'] ) ) {

					$employee_image	= wp_get_attachment_image_src( $employee['image'], 'medium' );

					$employee_schema['image']	= $employee_image[0]; // represents the URL

				}

				// Push employee data into the original $payload array variable
				array_push( $this->payload, $employee_schema );

			} // END foreach employee

		} // END check for employees

	}

	public function get_locations_schema() {

		if ( !empty( self::$plugin_data['locations'] ) && is_array( self::$plugin_data['locations'] ) ) {

			foreach ( self::$plugin_data['locations'] as $location ) {

				// Only adding if geo values exist
				if ( !empty( $location['latitude'] ) && !empty( $location['longitude'] ) ) {

					if ( !empty( $location['business_name'] ) ) {
						$location_name	= $location['business_name'];
					} elseif ( !empty( self::$plugin_data['site_name'] ) ) {
						$location_name	= self::$plugin_data['site_name'];
					} else {
						$location_name	= get_bloginfo('name');
					}

					$location_schema	= array(
						"@context"	=> "http://schema.org",
						"@type"		=> "Place",
						"name"		=> $location_name,
						"geo"		=> array(
							"@type"		=> "GeoCoordinates",
							"latitude"	=> $location['latitude'],
							"longitude"	=> $location['longitude']
						)
					);

					// Push location data into the original $payload array variable
					array_push( $this->payload, $location_schema );

				} // END check for lat/long

			} // END foreach location

		} // END check for locations

	}

	public function get_single_post_schema() {

		global $post;

		$post_ID		= $post->ID;
		$thumb_ID		= get_post_thumbnail_id( $post_ID );
		$author_ID		= $post->post_author;
		$post_author	= get_the_author_meta( 'user_nicename', $author_ID );

		$site_name	= !empty( self::$plugin_data['site_name'] ) ? self::$plugin_data['site_name'] : get_bloginfo('name');

		// Use excerpt as description but if blank, overwrite with SEO plugins
		// I really doubt more than one SEO plugin is installed, they shouldn't be anyway
		// But prioritizing All In One SEO plugin since that is (at the time of writing) Rosemont's go to SEO plugin
		$post_description	= get_the_excerpt( $post_ID );

		if ( '' !== $aioseo_desc = get_post_meta( $post_ID, '_aioseop_description', true ) ) {
			$post_description = $aioseo_desc;
		} elseif ( '' !== $yoast_desc = get_post_meta( $post_ID, '_yoast_wpseo_metadesc', true ) ) {
			$post_description = $yoast_desc;
		}

		$single_schema = array(
			"@context"	=> "http://schema.org/",
			"@type"		=> "Article",

			"mainEntityOfPage"	=> array(
				"@type"	=> "WebPage",
				"@id"	=> get_the_permalink()
			 ),

			"headline"	=> get_the_title(),

			"datePublished"	=> get_the_date( 'c', $post_ID ),
			"description"	=> $post_description,

			"author"	=> array(
				"@type"	=> "Person",
				"name"	=> $post_author
			),

			"publisher" => array(
				"@type"	=> "Organization",
				"name"	=> $site_name,
			)

		);

		// Add image of site/organization if it was defined
		if ( !empty( self::$plugin_data['site_logo'] ) ) {

			$site_logo	= wp_get_attachment_image_src( self::$plugin_data['site_logo'] );

			$single_schema['publisher']['logo']	= array(
				"@type"	=> "ImageObject",
				"url"	=> $site_logo[0],
				"width"	=> $site_logo[1],
			);

		}

		// Add image to schema IF there was one attached to the post
		// Here because not all our sites require a featured image
		if ( !empty( $thumb_ID ) ) {

			$thumb_url_array	= wp_get_attachment_image_src( $thumb_ID, 'thumbnail-size', true );

			$single_schema['image']	= array(
				"@type"		=> "ImageObject",
				"url"		=> $thumb_url_array[0],
				"height"	=> $thumb_url_array[1],
				"width"		=> $thumb_url_array[2]
			);

		}

		// Make sure there was a modification date before adding a mod date
		if ( get_the_modified_time( 'U' ) > get_the_time( 'U' ) ) {
			$single_schema['dateModified']	= get_the_modified_time( 'c', $post_ID );
		}

		// Push all data into the original $payload array variable
		array_push( $this->payload, $single_schema );

	}

	/**
	 * Get the rating schema if it is turned 'on'
	 *
	 * @return (mixed) Will return either the rating schema in array form or NULL if turned off
	 */
	public function get_rating_schema() {

		if ( !empty( self::$plugin_data['reviews_status'] )	&& self::$plugin_data['reviews_status'] == 'on'	) {

			$rating_schema['AggregateRating'] = array(
				"@type"			=> "AggregateRating",
				"ratingValue"	=> self::$plugin_data['rating_value'],
				"reviewCount"	=> self::$plugin_data['reviews_count']
			);

			return $rating_schema;

		} else {
			return NULL;
		}

	}

	public function output_head_schema() {

		/**
		 * Allow one last chance to manipulate schema outside of the plugin
		 *
		 * @param array $this->payload The array holding our data before it is JSON encoded below.
		 */
		$this->payload = apply_filters( 'rm_schema_json_ld_output', $this->payload );

		if ( is_array( $this->payload ) && !empty( $this->payload ) ) { ?>

			<!-- SCHEMA MARKUP -->
			<script type="application/ld+json"><?php echo json_encode( $this->payload ); ?></script>

		<?php }

	}

}
