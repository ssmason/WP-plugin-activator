<?php
/**
 * Satori-Digital Plugin Activator
 *
 * @category Plugin_Loader
 * @package  SatoriDigital\PluginActivator
 */


if ( ! defined( 'PLUGIN_ACTIVATION_CONFIG' ) ) {
	define( 'PLUGIN_ACTIVATION_CONFIG', __DIR__ . '/satori-wp-plugin-activator/config' );
}

if ( file_exists( __DIR__ . '/satori-wp-plugin-activator/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/satori-wp-plugin-activator/vendor/autoload.php';
}

use SatoriDigital\PluginActivator\Controllers\ActivationController;

add_action( 'after_setup_theme', function() {
	( new ActivationController() )->run();
});