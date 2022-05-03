<?php
/**
 * WordPress Responsive Images for SliderPro
 *
 * @package     SergeLiatko\WPResponsiveImagesSliderPro
 * @author      Serge Liatko
 * @copyright   2022 Serge Liatko https://techspokes.com
 * @license     GPL-3.0+
 *
 * @wordpress-plugin
 * Plugin Name: WordPress Responsive Images for SliderPro
 * Plugin URI:  https://github.com/sergeliatko/wp-responsive-images-sliderpro?utm_source=wordpress&utm_medium=plugin&utm_campaign=wp-responsive-images-sliderpro&utm_content=plugin-link
 * Description: Adds support for WordPress native lazy image loading, image srcset and sizes HTML attributes to SliderPro plugin.
 * Version:     0.0.7
 * Author:      Serge Liatko
 * Author URI:  https://techspokes.com?utm_source=wordpress&utm_medium=plugin&utm_campaign=wp-responsive-images-sliderpro&utm_content=author-link
 * Text Domain: wp-responsive-images-sliderpro
 * License:     GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 */

// do not load this file directly
defined( 'ABSPATH' ) or die( sprintf( 'Please do not load %s directly', __FILE__ ) );

// load namespace
require_once( dirname( __FILE__ ) . '/autoload.php' );

// load plugin text domain
add_action( 'plugins_loaded', function () {

	load_plugin_textdomain(
		'wp-responsive-images-sliderpro',
		false,
		basename( dirname( __FILE__ ) ) . '/languages'
	);
}, 10, 0 );

// load the plugin
add_action( 'plugins_loaded', array( 'SergeLiatko\WPResponsiveImagesSliderPro\Plugin', 'getInstance' ), 25, 0 );

