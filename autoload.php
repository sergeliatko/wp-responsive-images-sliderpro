<?php
/**
 * PSR-4 Autoloader.
 */
spl_autoload_register( function ( $class ) {
	if (
		strncmp(
			$namespace = 'SergeLiatko\WPResponsiveImagesSliderPro',
			$class,
			$namespace_length = strlen( $namespace )
		) !== 0
	) {
		return;
	}
	if ( file_exists(
		$file = join(
			        DIRECTORY_SEPARATOR,
			        array(
				        __DIR__,
				        'src',
				        str_replace(
					        '\\',
					        DIRECTORY_SEPARATOR,
					        trim(
						        substr( $class, $namespace_length ),
						        '\\'
					        )
				        ),
			        )
		        ) . '.php'
	) ) {
		include $file;
	}
} );
