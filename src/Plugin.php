<?php


namespace SergeLiatko\WPResponsiveImagesSliderPro;


use BQW_SliderPro;
use WP_Post;

/**
 * Class Plugin
 *
 * @package SergeLiatko\WPResponsiveImagesSliderPro
 */
class Plugin {

	const SLIDERPRO_CONTENT_TYPE_KEY     = 'content_type';
	const SLIDERPRO_AVAILABLE_VALUES_KEY = 'available_values';
	const SLIDERPRO_RENDERER_CLASS_KEY   = 'renderer_class';
	const FULL_SIZE_NAME                 = 'full';
	const LARGE_SIZE_NAME                = 'large';

	/**
	 * @var \SergeLiatko\WPResponsiveImagesSliderPro\Plugin $instance - plugin instance.
	 *
	 * @since 0.0.1
	 */
	protected static $instance;

	/**
	 * @var array<string, array{0: string, 1: int, 2: int, 3: bool}>|array $image_sources - image sources lookup table.
	 *
	 * @since 0.0.2
	 */
	protected $image_sources;

	/**
	 * @var array<int, int|false> $post_thumbnail_ids - post thumbnail IDs lookup table.
	 *
	 * @since 0.0.2
	 */
	protected $post_thumbnail_ids;

	/**
	 * Returns the plugin instance.
	 *
	 * @return \SergeLiatko\WPResponsiveImagesSliderPro\Plugin - plugin instance.
	 *
	 * @since 0.0.1
	 */
	public static function getInstance(): Plugin {

		if ( ! ( self::$instance instanceof Plugin ) ) {
			self::setInstance( new self() );
		}

		return self::$instance;
	}

	/**
	 * Sets the plugin instance.
	 *
	 * @param \SergeLiatko\WPResponsiveImagesSliderPro\Plugin $instance - plugin instance.
	 *
	 * @since 0.0.1
	 */
	protected static function setInstance( Plugin $instance ) {

		self::$instance = $instance;
	}

	/**
	 * Plugin constructor.
	 *
	 * @since 0.0.1
	 */
	protected function __construct() {
		// before we start check if SliderPro plugin is active
		if ( ! is_plugin_active( 'sliderpro/sliderpro.php' ) ) {
			add_action( 'admin_notices', array( $this, 'sliderpro_not_active_notice' ), 10 );

			// abort plugin workflow
			return;
		}
		// check if ewww-image-optimizer plugin is active
		if ( is_admin() && is_plugin_active( 'ewww-image-optimizer/ewww-image-optimizer.php' ) ) {
			$conflicting_options = array_filter( array(
				get_option( 'ewww_image_optimizer_lazy_load', false ),
				get_option( 'ewww_image_optimizer_ll_autoscale', false ),
			) );
			if ( ! empty( $conflicting_options ) ) {
				add_action( 'admin_notices', array( $this, 'ewww_image_optimizer_conflict_notice' ), 10 );
			}
		}
		// fix lightbox image size issue
		add_filter( 'sliderpro_data', array( $this, 'fix_lightbox_image_size_issue' ), 10, 1 );
		// rewrite SliderPro renderers
		add_filter( 'sliderpro_default_slide_settings', array( $this, 'rewrite_sliderpro_renderers' ), 25, 1 );
		// add image render functions to sliderpro gallery slider
		add_filter( 'sliderpro_gallery_tags', array( $this, 'sliderpro_gallery_dynamic_tags' ), 10, 1 );
		// add image render functions to sliderpro posts slider
		add_filter( 'sliderpro_posts_tags', array( $this, 'sliderpro_posts_dynamic_tags' ), 10, 1 );
		// reformat templated images in slide renderer html
		add_filter( 'sliderpro_slide_markup', array( $this, 'reformat_templated_images' ), 10, 1 );
		// remove lazy loading from the first slide image
		add_filter( 'sliderpro_markup', array( $this, 'remove_lazy_loading_from_visible_slides' ), 25, 2 );
	}

	/**
	 * Removes the lazy loading from the first image in the slider.
	 *
	 * @param string $markup - slider markup.
	 * @param int    $id
	 *
	 * @return string - modified slider markup.
	 *
	 * @since 0.0.6
	 */
	public function remove_lazy_loading_from_visible_slides( string $markup, int $id ): string {
		if ( false === strpos( $markup, 'sp-image' ) ) {
			return $markup;
		}
		if (
			preg_match_all( '/<img.*class=["\'].?sp-image["\' ]?.+[^\/>]\/>/', $markup, $matches )
			&& ! empty( $matches[0] )
			&& is_array( $matches[0] )
		) {
			$slider         = BQW_SliderPro::get_instance()->get_slider( $id );
			$visible_slides = absint( $slider['settings']['visible_size'] ?? 1 );
			if ( $visible_slides > 1 ) {
				for ( $i = 0; $i < $visible_slides; $i ++ ) {
					if ( ! empty( $matches[0][ $i ] ) ) {
						$markup = $this->disable_lazy_loading( $matches[0][ $i ], $markup );
					}
				}
				if ( 0 < ( $last_slide_index = count( $matches[0] ) - 1 ) ) {
					$markup = $this->disable_lazy_loading( $matches[0][ $last_slide_index ], $markup );
				}
			} else {
				$markup = $this->disable_lazy_loading( $matches[0][0], $markup );
			}
		}

		return $markup;
	}

	/**
	 * Adds gallery dynamic tag renderers.
	 *
	 * @param array<string, string|array|callable|Closure> $tags - SliderPro gallery tags.
	 *
	 * @return array<string, string|array|callable|Closure> - modified SliderPro gallery tags.
	 *
	 * @since 0.0.2
	 */
	public function sliderpro_gallery_dynamic_tags( array $tags ): array {
		return array_merge( $tags, array(
			'image_width'  => array( $this, 'gallery_render_image_width' ),
			'image_height' => array( $this, 'gallery_render_image_height' ),
			'image_src'    => array( $this, 'gallery_render_image_src' ),
			'image_srcset' => array( $this, 'gallery_render_image_srcset' ),
			'image_sizes'  => array( $this, 'gallery_render_image_sizes' ),
		) );
	}

	/**
	 * Adds posts dynamic tag renderers.
	 *
	 * @param array<string, string|array|callable|Closure> $tags - SliderPro posts tags.
	 *
	 * @return array<string, string|array|callable|Closure> - modified SliderPro posts tags.
	 *
	 * @since 0.0.2
	 */
	public function sliderpro_posts_dynamic_tags( array $tags ): array {
		return array_merge( $tags, array(
			'image_width'  => array( $this, 'posts_render_image_width' ),
			'image_height' => array( $this, 'posts_render_image_height' ),
			'image_src'    => array( $this, 'posts_render_image_src' ),
			'image_srcset' => array( $this, 'posts_render_image_srcset' ),
			'image_sizes'  => array( $this, 'posts_render_image_sizes' ),
		) );
	}

	/**
	 * Renders image width attribute in posts slider.
	 *
	 * @param string|false $size - image size.
	 * @param \WP_Post     $post - current post.
	 *
	 * @return string - image width attribute.
	 *
	 * @since 0.0.2
	 */
	public function posts_render_image_width( $size, WP_Post $post ): string {
		if ( false === ( $id = $this->get_post_thumbnail_id( $post->ID ) ) ) {
			return '';
		}
		$size = empty( $size ) ? self::FULL_SIZE_NAME : $size;

		return $this->render_image_width( $id, $size );
	}

	/**
	 * Renders image height attribute in posts slider.
	 *
	 * @param string|false $size - image size.
	 * @param \WP_Post     $post - current post.
	 *
	 * @return string - image height attribute.
	 *
	 * @since 0.0.2
	 */
	public function posts_render_image_height( $size, WP_Post $post ): string {
		if ( false === ( $id = $this->get_post_thumbnail_id( $post->ID ) ) ) {
			return '';
		}
		$size = empty( $size ) ? self::FULL_SIZE_NAME : $size;

		return $this->render_image_height( $id, $size );
	}

	/**
	 * Renders image src attribute in posts slider.
	 *
	 * @param string|false $size - image size.
	 * @param \WP_Post     $post - current post.
	 *
	 * @return string - image src attribute.
	 *
	 * @since 0.0.2
	 */
	public function posts_render_image_src( $size, WP_Post $post ): string {
		if ( false === ( $id = $this->get_post_thumbnail_id( $post->ID ) ) ) {
			return '';
		}
		$size = empty( $size ) ? self::FULL_SIZE_NAME : $size;

		return $this->render_image_src( $id, $size );
	}

	/**
	 * Renders image srcset attribute in posts slider.
	 *
	 * @param string|false $size - image size.
	 * @param \WP_Post     $post - current post.
	 *
	 * @return string - image srcset attribute.
	 *
	 * @since 0.0.2
	 */
	public function posts_render_image_srcset( $size, WP_Post $post ): string {
		if ( false === ( $id = $this->get_post_thumbnail_id( $post->ID ) ) ) {
			return '';
		}
		$size = empty( $size ) ? self::FULL_SIZE_NAME : $size;

		return $this->render_image_srcset( $id, $size );
	}

	/**
	 * Renders image sizes attribute in posts slider.
	 *
	 * @param string|false $size - image size.
	 * @param \WP_Post     $post - current post.
	 *
	 * @return string - image sizes attribute.
	 *
	 * @since 0.0.2
	 */
	public function posts_render_image_sizes( $size, WP_Post $post ): string {
		if ( false === ( $id = $this->get_post_thumbnail_id( $post->ID ) ) ) {
			return '';
		}
		$size = empty( $size ) ? self::FULL_SIZE_NAME : $size;

		return $this->render_image_sizes( $id, $size );
	}

	/**
	 * Renders image width attribute in gallery slider.
	 *
	 * @param string|false    $size  - image size.
	 * @param object|\WP_Post $image - gallery image.
	 *
	 * @return string - image width attribute.
	 *
	 * @since 0.0.2
	 */
	public function gallery_render_image_width( $size, $image ): string {
		$size = empty( $size ) ? self::FULL_SIZE_NAME : $size;

		return $this->render_image_width( absint( $image->ID ?? 0 ), $size );
	}

	/**
	 * Renders image height attribute in gallery slider.
	 *
	 * @param string|false    $size  - image size.
	 * @param object|\WP_Post $image - gallery image.
	 *
	 * @return string - image height attribute.
	 *
	 * @since 0.0.2
	 */
	public function gallery_render_image_height( $size, $image ): string {
		$size = empty( $size ) ? self::FULL_SIZE_NAME : $size;

		return $this->render_image_height( absint( $image->ID ?? 0 ), $size );
	}

	/**
	 * Renders image src attribute in gallery slider.
	 *
	 * @param string|false    $size  - image size.
	 * @param object|\WP_Post $image - gallery image.
	 *
	 * @return string - image src attribute.
	 *
	 * @since 0.0.2
	 */
	public function gallery_render_image_src( $size, $image ): string {
		$size = empty( $size ) ? self::FULL_SIZE_NAME : $size;

		return $this->render_image_src( absint( $image->ID ?? 0 ), $size );
	}

	/**
	 * Renders image srcset attribute in gallery slider.
	 *
	 * @param string|false    $size  - image size.
	 * @param object|\WP_Post $image - gallery image.
	 *
	 * @return string - image srcset attribute.
	 *
	 * @since 0.0.2
	 */
	public function gallery_render_image_srcset( $size, $image ): string {
		$size = empty( $size ) ? self::FULL_SIZE_NAME : $size;

		return $this->render_image_srcset( absint( $image->ID ?? 0 ), $size );
	}

	/**
	 * Renders image sizes attribute in gallery slider.
	 *
	 * @param string|false    $size  - image size.
	 * @param object|\WP_Post $image - gallery image.
	 *
	 * @return string - image sizes attribute.
	 *
	 * @since 0.0.2
	 */
	public function gallery_render_image_sizes( $size, $image ): string {
		$size = empty( $size ) ? self::FULL_SIZE_NAME : $size;

		return $this->render_image_sizes( absint( $image->ID ?? 0 ), $size );
	}

	/**
	 * @param string $markup - SliderPro slide markup.
	 *
	 * @return string - modified markup.
	 *
	 * @since 0.0.2
	 */
	public function reformat_templated_images( string $markup = '' ): string {
		$templates = $this->extract_image_templates( $markup );
		// if no templates found, return original markup
		if ( empty( $templates ) ) {
			return $markup;
		}
		// generate new templates
		array_walk( $templates, array( $this, 'add_new_image_template' ) );

		// replace original templates with new ones in the markup
		return str_replace(
			array_column( $templates, 'template' ),
			array_column( $templates, 'replacement' ),
			$markup
		);
	}

	/**
	 * Adds main_image_link to the slides that have main_image_id set and main_image_link is empty when lightbox is enabled.
	 *
	 * @param array $data - SliderPro data.
	 *
	 * @return array - modified SliderPro data.
	 *
	 * @since 0.0.2
	 */
	public function fix_lightbox_image_size_issue( array $data = array() ): array {
		if ( true === boolval( $data['settings']['lightbox'] ?? false ) ) {
			$lightbox_image_size = apply_filters( 'sliderpro_lightbox_image_size', self::LARGE_SIZE_NAME, $data );
			if ( ! empty( $lightbox_image_size ) ) {
				$slides = array_filter( (array) $data['slides'] ?? array() );
				foreach ( $slides as $key => $slide ) {
					$main_image_id = absint( $slide['main_image_id'] ?? 0 );
					if ( ! empty( $main_image_id ) && empty( $slide['main_image_link'] ) ) {
						$data['slides'][ $key ]['main_image_link'] = esc_url_raw(
							strval(
								wp_get_attachment_image_url(
									$slide['main_image_id'],
									$lightbox_image_size
								)
							)
						);
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Rewrites SliderPro renderers.
	 *
	 * @param array $settings - SliderPro settings.
	 *
	 * @return array - modified SliderPro settings.
	 *
	 * @see   \BQW_SliderPro_Settings::getSlideSettings() for more details.
	 *
	 * @since 0.0.2
	 */
	public function rewrite_sliderpro_renderers( array $settings ): array {
		// modify only core renderer
		return $this->replace_renderer(
			$settings,
			'custom',
			'\SergeLiatko\WPResponsiveImagesSliderPro\Renderers\Slide',
			'BQW_SP_Slide_Renderer'
		);
	}

	/**
	 * Displays notice if SliderPro plugin is not active.
	 *
	 * @return void
	 *
	 * @since 0.0.2
	 */
	public function sliderpro_not_active_notice(): void {
		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			sprintf(
				__( '%1$s requires %2$s to be installed and activated.', 'wp-responsive-images-sliderpro' ),
				'<strong>' . __( 'WordPress Responsive Images for SliderPro', 'wp-responsive-images-sliderpro' ) . '</strong>',
				'<strong>' . __( 'SliderPro', 'wp-responsive-images-sliderpro' ) . '</strong>'
			)
		);
	}

	/**
	 * Displays notice about conflicting options with ewww image optimizer.
	 *
	 * @return void
	 *
	 * @since 0.0.3
	 */
	public function ewww_image_optimizer_conflict_notice(): void {
		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			sprintf(
				__( '%1$s may conflict with following settings in %2$s plugin: %3$s. Please, check %2$s plugin configuration for exceptions or disable the mentioned settings.', 'wp-responsive-images-sliderpro' ),
				'<strong>' . __( 'WordPress Responsive Images for SliderPro', 'wp-responsive-images-sliderpro' ) . '</strong>',
				'<strong>' . __( 'EWWW Image Optimizer', 'wp-responsive-images-sliderpro' ) . '</strong>',
				join( ', ', array_map(
					function ( $settings ) {
						return '<em>' . $settings . '</em>';
					},
					array(
						__( 'Lazy Load', 'wp-responsive-images-sliderpro' ),
						__( 'Automatic Scaling', 'wp-responsive-images-sliderpro' ),
					)
				) )
			)
		);
	}

	/**
	 * Replaces SliderPro renderer.
	 *
	 * @param array  $settings - SliderPro settings.
	 * @param string $key      - key of the renderer.
	 * @param string $new      - new renderer fully qualified class name.
	 * @param string $old      - old renderer fully qualified class name.
	 *
	 * @return array - modified SliderPro settings.
	 *
	 * @since 0.0.2
	 */
	protected function replace_renderer( array $settings, string $key, string $new, string $old = '' ): array {
		$current_renderer = strval( $settings[ self::SLIDERPRO_CONTENT_TYPE_KEY ][ self::SLIDERPRO_AVAILABLE_VALUES_KEY ][ $key ][ self::SLIDERPRO_RENDERER_CLASS_KEY ] ?? null );
		// if renderer is not available - return settings as is
		if ( empty( $current_renderer ) ) {
			return $settings;
		}
		// if old renderer is specified - check if it is the same as current before replacing
		if ( ! empty( $old ) && $old !== $current_renderer ) {
			return $settings;
		}
		// replace renderer
		$settings[ self::SLIDERPRO_CONTENT_TYPE_KEY ][ self::SLIDERPRO_AVAILABLE_VALUES_KEY ][ $key ][ self::SLIDERPRO_RENDERER_CLASS_KEY ] = $new;

		// return modified settings
		return $settings;
	}

	/**
	 * Extracts SliderPro image templates in dynamic slides.
	 *
	 * @param string $html - HTML of the slide.
	 *
	 * @return array<int, array{template: string, size: string, attributes: array<string, string|null>}> - array of image templates or empty array if no templates are found.
	 * Each template is an array with keys:
	 *  - template - HTML of the image tag.
	 *  - size - image size in WordPress of the image or empty string if not specified.
	 *  - attributes - array of HTML attributes of the image.
	 *
	 * @since 0.0.2
	 */
	protected function extract_image_templates( string $html = '' ): array {
		if ( empty( $html ) || ( false === strpos( $html, '[sp_image_src' ) ) ) {
			return array();
		}
		// first extract the templates images and their sizes using the following regex: /<img.*(?:data-)?src=["']\[sp_image_src\.?([a-z\d_-]+)?.*[\]\s].*\/>/
		/** @noinspection RegExpRedundantEscape */
		preg_match_all(
			'/<img.*(?:data-)?src=["\']\[sp_image_src\.?([a-z\d_-]+)?.*[\]\s][\'"].*\/>/',
			$html,
			$matches,
			PREG_SET_ORDER
		);
		// if no matches found - return empty array
		if ( empty( $matches ) ) {
			return array();
		}
		// prepare the templates array
		$templates = array();
		array_walk( $matches, function ( $match ) use ( &$templates ) {
			if ( ! empty( $match[0] ) ) {
				$templates[] = array(
					'template'   => $match[0],
					'size'       => $match[1] ?? '',
					'attributes' => $this->extract_image_attributes( $match[0] ),
				);
			}
		} );


		return $templates;
	}

	/**
	 * Extracts image attributes from the image HTML.
	 *
	 * @param string $image_html - HTML of the image.
	 *
	 * @return array<string, string|null> - array of HTML attributes of the image.
	 * @notice The returned array is indexed by attribute names. Null values are returned for attributes that do not have values defined.
	 *
	 * @since  0.0.2
	 */
	protected function extract_image_attributes( string $image_html = '' ): array {
		if (
			empty( $image_html )
			|| ( '' === ( $inner = trim( preg_replace( '/<img\s?|\s?\/>|\s+/', ' ', $image_html ) ) ) )
		) {
			return array();
		}
		$items      = array_filter( explode( ' ', $inner ) );
		$attributes = array();
		array_walk( $items, function ( $item ) use ( &$attributes ) {
			$item                   = array_map(
				function ( $attribute ) {
					return trim( $attribute, "\t\n\r\0\x0B\"' " );
				},
				explode( '=', $item )
			);
			$attributes[ $item[0] ] = $item[1] ?? null;
		} );

		return $attributes;
	}

	/**
	 * Adds replacement for the image tag in the slide HTML to the template.
	 *
	 * @param array $template - array of image template. Passed by reference.
	 *
	 * @param-out array $template - modified template.
	 *
	 * @return void
	 *
	 * @since     0.0.2
	 */
	protected function add_new_image_template( array &$template ): void {
		// get image size
		$size = empty( $template['size'] ) ? self::FULL_SIZE_NAME : strval( $template['size'] );
		// get image class
		$class = strval( $template['attributes']['class'] ?? '' );
		if ( ! empty( $class ) ) {
			$classes          = explode( ' ', $class );
			$attachment_class = "attachment-$size";
			$size_class       = "size-$size";
			if ( ! in_array( $attachment_class, $classes ) ) {
				$classes[] = $attachment_class;
			}
			if ( ! in_array( $size_class, $classes ) ) {
				$classes[] = $size_class;
			}
			$class = implode( ' ', $classes );
		}
		// Lazy loading
		$lazy_loading = ( isset( $template['attributes']['loading'] ) && $template['attributes']['loading'] === 'lazy' )
		                || ! empty( $template['attributes']['data-src'] );
		$loading      = $lazy_loading ? 'lazy' : 'eager';
		// move data-src to src
		if ( isset( $template['attributes']['data-src'] ) ) {
			$template['attributes']['src'] = $template['attributes']['data-src'];
		}
		// if size not empty - add a dot before it to use in new attributes
		if ( ! empty( $size ) ) {
			$size = '.' . $size;
		}
		// prepare new attributes
		$attributes = array(
			'loading' => $loading,
			'width'   => "[sp_image_width$size]",
			'height'  => "[sp_image_height$size]",
			'src'     => "[sp_image_src$size]",
			'srcset'  => "[sp_image_srcset$size]",
			'sizes'   => "[sp_image_sizes$size]",
			'class'   => $class,
		);
		// get fields to remove from attributes
		$attributes_to_remove = array(
			'loading'           => 'loading',
			'data-src'          => 'data-src',
			'data-retina'       => 'data-retina',
			'data-small'        => 'data-small',
			'data-medium'       => 'data-medium',
			'data-large'        => 'data-large',
			'data-retinasmall'  => 'data-retinasmall',
			'data-retinamedium' => 'data-retinamedium',
			'data-retinalarge'  => 'data-retinalarge',
		);
		// merge attributes
		$new_attributes = array_merge(
			$attributes,
			// remove attributes and collisions with new attributes
			array_diff_key( $template['attributes'], $attributes, $attributes_to_remove )
		);
		// build inner HTML
		array_walk( $new_attributes, function ( &$value, $attribute ) {
			// original attributes were already escaped - just build the HTML
			$value = is_null( $value ) ? $attribute : $attribute . '="' . $value . '"';
		} );
		$inner = implode( ' ', $new_attributes );
		// add new template to our template
		/** @noinspection HtmlRequiredAltAttribute */
		$template['replacement'] = "<img $inner />";
	}

	/**
	 * Returns image sources lookup table.
	 *
	 * @return array<string, array{0: string, 1: int, 2: int, 3: bool}>|array - image sources lookup table.
	 *
	 * @see   \wp_get_attachment_image_src() for more details.
	 *
	 * @since 0.0.2
	 */
	protected function get_image_sources(): array {
		if ( ! is_array( $this->image_sources ) ) {
			$this->set_image_sources( array() );
		}

		return $this->image_sources;
	}

	/**
	 * Sets image sources lookup table.
	 *
	 * @param array<string, array{0: string, 1: int, 2: int, 3: bool}>|array $image_sources - image sources lookup table.
	 *
	 * @since 0.0.2
	 */
	protected function set_image_sources( array $image_sources ): void {
		$this->image_sources = array_filter( $image_sources );
	}

	/**
	 * Returns image data from the lookup table.
	 *
	 * @param int    $id   - attachment ID.
	 * @param string $size - image size.
	 *
	 * @return array{0: string, 1: int, 2: int, 3: bool}|false - image data from the lookup table.
	 *
	 * @see   \wp_get_attachment_image_src() for more details.
	 *
	 * @since 0.0.2
	 */
	protected function get_image_source( int $id, string $size ) {
		$key           = sprintf( '%d-%s', $id, $size );
		$image_sources = $this->get_image_sources();
		if ( isset( $image_sources[ $key ] ) ) {
			return $image_sources[ $key ];
		}
		$image_source = wp_get_attachment_image_src( $id, $size );
		if ( ! is_array( $image_source ) ) {
			return false;
		}
		$image_sources[ $key ] = $image_source;
		$this->set_image_sources( $image_sources );

		return $image_source;
	}

	/**
	 * Returns post thumbnail IDs lookup table.
	 *
	 * @return array<int, int|false> - post thumbnail IDs lookup table.
	 *
	 * @since 0.0.2
	 */
	protected function get_post_thumbnail_ids(): array {
		if ( ! is_array( $this->post_thumbnail_ids ) ) {
			$this->set_post_thumbnail_ids( array() );
		}

		return $this->post_thumbnail_ids;
	}

	/**
	 * Sets post thumbnail IDs lookup table.
	 *
	 * @param array<int, int|false> $post_thumbnail_ids - post thumbnail IDs lookup table.
	 *
	 * @since 0.0.2
	 */
	protected function set_post_thumbnail_ids( array $post_thumbnail_ids ): void {
		$this->post_thumbnail_ids = $post_thumbnail_ids;
	}

	/**
	 * Returns post thumbnail ID from the lookup table.
	 *
	 * @param int $post_id - post ID.
	 *
	 * @return false|int - post thumbnail ID from the lookup table or false if post does not have a thumbnail.
	 *
	 * @since 0.0.2
	 */
	protected function get_post_thumbnail_id( int $post_id ) {
		$post_thumbnail_ids = $this->get_post_thumbnail_ids();
		if ( isset( $post_thumbnail_ids[ $post_id ] ) ) {
			return $post_thumbnail_ids[ $post_id ];
		}
		$post_thumbnail_id              = has_post_thumbnail( $post_id ) ? get_post_thumbnail_id( $post_id ) : false;
		$post_thumbnail_ids[ $post_id ] = $post_thumbnail_id;
		$this->set_post_thumbnail_ids( $post_thumbnail_ids );

		return $post_thumbnail_id;
	}

	/**
	 * Renders image width attribute.
	 *
	 * @param int    $id   - image ID.
	 * @param string $size - image size.
	 *
	 * @return string - image width attribute.
	 *
	 * @since 0.0.2
	 */
	protected function render_image_width( int $id, string $size ): string {
		$image_source = $this->get_image_source( $id, $size );
		if ( ! is_array( $image_source ) ) {
			return '';
		}

		return absint( $image_source[1] );
	}

	/**
	 * Renders image height attribute.
	 *
	 * @param int    $id   - image ID.
	 * @param string $size - image size.
	 *
	 * @return string - image height attribute.
	 *
	 * @since 0.0.2
	 */
	protected function render_image_height( int $id, string $size ): string {
		$image_source = $this->get_image_source( $id, $size );
		if ( ! is_array( $image_source ) ) {
			return '';
		}

		return absint( $image_source[2] );
	}

	/**
	 * Renders image src attribute.
	 *
	 * @param int    $id   - image ID.
	 * @param string $size - image size.
	 *
	 * @return string - image src attribute.
	 *
	 * @since 0.0.2
	 */
	protected function render_image_src( int $id, string $size ): string {
		$image_source = $this->get_image_source( $id, $size );
		if ( ! is_array( $image_source ) ) {
			return '';
		}

		return $image_source[0];
	}

	/**
	 * Renders image srcset attribute.
	 *
	 * @param int    $id   - image ID.
	 * @param string $size - image size.
	 *
	 * @return string - image srcset attribute.
	 *
	 * @since 0.0.2
	 */
	protected function render_image_srcset( int $id, string $size ): string {
		$srcset = wp_get_attachment_image_srcset( $id, $size );
		if ( empty( $srcset ) ) {
			return '';
		}

		return $srcset;
	}

	/**
	 * Renders image sizes attribute.
	 *
	 * @param int    $id   - image ID.
	 * @param string $size - image size.
	 *
	 * @return string - image sizes attribute.
	 *
	 * @since 0.0.2
	 */
	protected function render_image_sizes( int $id, string $size ): string {
		$sizes = wp_get_attachment_image_sizes( $id, $size );
		if ( empty( $sizes ) ) {
			return '';
		}

		return $sizes;
	}

	/**
	 * Disables the image lazy loading.
	 *
	 * @param string $image - image to be processed.
	 * @param string $html  - content HTML containing the image.
	 *
	 * @return string - processed HTML content.
	 *
	 * @since 0.0.8
	 */
	protected function disable_lazy_loading( string $image, string $html ): string {
		$replacement = preg_replace( '/\sloading=(["\'])lazy(["\'])/', ' loading=$1eager$2', $image );
		if ( empty( $replacement ) || ( $replacement === $image ) ) {
			return $html;
		}

		return str_replace( $image, $replacement, $html );
	}

}

