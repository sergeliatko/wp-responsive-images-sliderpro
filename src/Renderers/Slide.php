<?php


namespace SergeLiatko\WPResponsiveImagesSliderPro\Renderers;


use BQW_SP_Slide_Renderer;

/**
 * Class Slide
 *
 * @package SergeLiatko\WPResponsiveImagesSliderPro\Renderers
 */
class Slide extends BQW_SP_Slide_Renderer {

	/**
	 * @var array<string,array{name: string, width: int, height: int, crop: bool}> $image_sizes - Array of image sizes indexed by {width}x{height}.
	 *
	 * @since 0.0.2
	 */
	protected $images_sizes;

	/**
	 * Returns the image sizes array.
	 *
	 * @return array<string,array{name: string, width: int, height: int, crop: bool}> - Array of image sizes indexed by {width}x{height}.
	 *
	 * @since 0.0.2
	 */
	protected function get_images_sizes(): array {
		if ( empty( $this->images_sizes ) ) {
			$this->set_images_sizes( $this->load_images_sizes() );
		}

		return $this->images_sizes;
	}

	/**
	 * Sets the image sizes array.
	 *
	 * @param array<string,array{name: string, width: int, height: int, crop: bool}> $images_sizes - Array of image sizes indexed by {width}x{height}.
	 *
	 * @since 0.0.2
	 */
	protected function set_images_sizes( array $images_sizes ): void {
		$this->images_sizes = $images_sizes;
	}

	/**
	 * Returns array of all image sizes registered in WordPress.
	 *
	 * @return array<string,array{name: string, width: int, height: int, crop: bool}> - Array of image sizes indexed by {width}x{height}.
	 *
	 * @since 0.0.2
	 */
	protected function load_images_sizes(): array {
		$sizes = array();
		foreach ( wp_get_registered_image_subsizes() as $size_name => $size_data ) {
			$sizes["{$size_data['width']}x{$size_data['height']}"] = array(
				'name'   => $size_name,
				'width'  => $size_data['width'],
				'height' => $size_data['height'],
				'crop'   => $size_data['crop'],
			);
		}

		return $sizes;
	}

	/**
	 * Returns image size name for given image size dimensions or array of dimensions.
	 *
	 * @param int $width  - image width.
	 * @param int $height - image height.
	 *
	 * @return string|int[] - image size name or array of width and height.
	 *
	 * @since 0.0.2
	 */
	protected function get_image_size( int $width, int $height ) {
		$sizes = $this->get_images_sizes();
		if ( isset( $sizes["{$width}x$height"]['name'] ) ) {
			return $sizes["{$width}x$height"]['name'];
		}
		foreach ( $sizes as $size ) {
			if (
				! empty( $size['name'] )
				&& ( $size['width'] === $width )
				&& ( $size['height'] === 0 || $size['height'] <= $height )
			) {
				return $size['name'];
			}
		}

		return array( $width, $height );
	}

	/**
	 * Renders main image HTML.
	 *
	 * @return string - HTML of the main image.
	 *
	 * @since 0.0.2
	 */
	protected function create_main_image(): string {
		// get the main image id
		$main_image_id = absint( $this->data['main_image_id'] ?? 0 );
		// check if the image we are dealing with is in the media library
		if ( 0 < $main_image_id ) {
			$width       = absint( $this->data['main_image_width'] ?? 0 );
			$height      = absint( $this->data['main_image_height'] ?? 0 );
			$size        = ! ( empty( $width ) || empty( $height ) ) ? $this->get_image_size( $width, $height ) : 'full';
			$size_class  = is_array( $size ) ? implode( 'x', $size ) : $size;
			$image_class = "sp-image attachment-$size_class size-$size_class";
			$attributes  = array(
				'class'   => apply_filters( 'sliderpro_main_image_classes', $image_class, $this->slider_id, $this->slide_index ),
				'title'   => empty( $this->hide_image_title ) ? strval( $this->data['main_image_title'] ?? '' ) : '',
				'alt'     => strval( $this->data['main_image_alt'] ?? '' ),
				'loading' => empty( $this->lazy_loading ) ? 'eager' : 'lazy',
			);
			$image       = wp_get_attachment_image( $main_image_id, $size, false, array_filter( $attributes ) );
			if ( ! empty( $image ) ) {
				return $image;
			}
		}

		// the main image is not in the media library - let the core handle it
		return parent::create_main_image();
	}

	/**
	 * Renders the thumbnail image HTML.
	 *
	 * @return string - HTML for the thumbnail image.
	 *
	 * @since 0.0.2
	 */
	protected function create_thumbnail_image(): string {
		// process only if the thumbnail image src is empty, auto-thumbnail is enabled and the thumbnail image is in the media library
		if (
			empty( $this->data['thumbnail_source'] )
			&& ( true === $this->auto_thumbnail_images )
			&& ( 0 < ( $main_image_id = absint( $this->data['main_image_id'] ?? 0 ) ) )
		) {
			$thumbnail_width  = absint( $this->data['thumbnail_width'] ?? 0 );
			$thumbnail_height = absint( $this->data['thumbnail_height'] ?? 0 );
			$thumbnail_size   = ! ( empty( $thumbnail_width ) || empty( $thumbnail_height ) ) ?
				$this->get_image_size( $thumbnail_width, $thumbnail_height )
				: strval( $this->data['thumbnail_size'] ?? 'thumbnail' );

			$size_class      = is_array( $thumbnail_size ) ? implode( 'x', $thumbnail_size ) : $thumbnail_size;
			$thumbnail_class = $this->has_thumbnail_content() ?
				"attachment-$size_class size-$size_class"
				: apply_filters(
					'sliderpro_thumbnail_classes',
					"sp-thumbnail attachment-$size_class size-$size_class",
					$this->slider_id,
					$this->slide_index
				);

			$attributes = array(
				'class'   => $thumbnail_class,
				'title'   => empty( $this->hide_image_title ) ? strval( $this->data['thumbnail_title'] ?? '' ) : '',
				'alt'     => strval( $this->data['thumbnail_alt'] ?? '' ),
				'loading' => empty( $this->lazy_loading ) ? 'eager' : 'lazy',
			);

			$thumbnail = wp_get_attachment_image( $main_image_id, $thumbnail_size, false, array_filter( $attributes ) );
			if ( ! empty( $thumbnail ) ) {
				return $thumbnail;
			}
		}

		// let the core handle it
		return parent::create_thumbnail_image();
	}


}
