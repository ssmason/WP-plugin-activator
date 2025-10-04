<?php
/**
 * Bootstrap file for unit tests.
 * 
 * @category Plugin_Loader
 * @package  SatoriDigital\Activate
 */

/**
 * Required global to identify unit test config files.
 */ 
define( 'PLUGIN_ACTIVATION_CONFIG', dirname( dirname( __FILE__ ) ) . '/unit-tests/plugin-config' );
define( 'WP_PLUGIN_DIR', '/wp-content/plugins/' );

/**
 * Autoload which will deliver plugin class.
 */ 
if ( file_exists( 'vendor/autoload.php' ) ) {
	// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath
	include_once 'vendor/autoload.php';
}

WP_Mock::bootstrap();
