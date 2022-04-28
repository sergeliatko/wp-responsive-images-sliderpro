<?php


namespace SergeLiatko\WPResponsiveImagesSliderPro;


/**
 * Class Plugin
 *
 * @package SergeLiatko\WPResponsiveImagesSliderPro
 */
class Plugin {

	/**
	 * @var \SergeLiatko\WPResponsiveImagesSliderPro\Plugin $instance
	 */
	protected static $instance;

	/**
	 * @return \SergeLiatko\WPResponsiveImagesSliderPro\Plugin
	 */
	public static function getInstance(): Plugin {

		if ( ! ( self::$instance instanceof Plugin ) ) {
			self::setInstance( new self() );
		}

		return self::$instance;
	}

	/**
	 * @param \SergeLiatko\WPResponsiveImagesSliderPro\Plugin $instance
	 */
	protected static function setInstance( Plugin $instance ) {

		self::$instance = $instance;
	}

	/**
	 * Plugin constructor.
	 */
	protected function __construct() {
	}

}

