<?php

if ( !defined('ABSPATH') )
	die ( 'YOU SHALL NOT PASS!' );

/**
 * This class handles setting up reviews markup/CSS
 */

class RM_Schema_Reviews {

	// Instance of this class
	static $instance	= false;

	// Plugin slug
	static $plugin_slug	= 'rm-schema-options';

	// Plugin data
	static $plugin_data	= null;

	public function __construct() {

		// get our plugin options as serialized data as we saved into the theme options
		self::$plugin_data	= get_option( 'option_'. self::$plugin_slug );

		// If reviews are off, do NOTHING
		if ( !empty( self::$plugin_data['reviews_status'] ) && self::$plugin_data['reviews_status'] == 'on' ) {

			add_action( 'wp_head', array( $this, 'output_review_style' ) );

			add_action( 'reviews_markup', array( $this, 'output_reviews_markup' ) );

		}

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
	 * Inserts styles required for the reviews markup into the head of the page
	 */
	public function output_review_style() {

		$source = RM_SCHEMA_PATH .'css/ratings.min.css';

		if ( file_exists($source) ) {
			$contents	= file_get_contents( $source );

			// strip the source map if present, causes bugs
			$contents	= preg_replace( '/\/\*(.)+\ *\//', '', $contents );

			echo '<style type="text/css" media="screen">'. trim( $contents ) .'</style>';
		}

	}

	public function add_reviews_hook() {

		add_action( 'reviews_markup', array( $this, 'output_reviews_markup' ) );

	}

	public function output_reviews_markup() {

		$ratings_text		= sprintf( '%s Stars from %s Reviews', self::$plugin_data['rating_value'], self::$plugin_data['reviews_count'] );
		$ratings_url		= self::$plugin_data['reviews_url'];
		$ratings_url_target	= ( strpos( $ratings_url, site_url() ) === 0 ) ? '' : ' target="_blank"';
		$ratings_count		= round( self::$plugin_data['rating_value'] );
		?>

		<div class="ratings">
			<a href="<?php echo $ratings_url; ?>"<?php echo $ratings_url_target; ?>>
				<span class="ratings__text"><?php echo $ratings_text; ?></span>

				<?php if ( file_exists( RM_SCHEMA_PATH .'/images/star.svg' ) ) : ?>
				<span class="ratings__stars">

					<?php for ( $i = 0; $i < $ratings_count; $i++ ) { ?>
					<span class="ratings__star"><?php include RM_SCHEMA_PATH .'images/star.svg'; ?></span>
					<?php } ?>

				</span>
				<?php endif; ?>

			</a>
		</div>

		<?php

	}

}
