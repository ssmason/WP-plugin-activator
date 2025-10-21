<?php
/**
 * Satori-Digital Plugin Activator
 *
 * @category Plugin_Loader
 * @package  SatoriDigital\PluginActivator
 */


if ( ! defined( 'PLUGIN_ACTIVATION_CONFIG' ) ) {
	define( 'PLUGIN_ACTIVATION_CONFIG', WP_CONTENT_DIR . '/private/plugin-config' );
}

if ( file_exists( __DIR__ . '/satori-wp-plugin-activator/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/satori-wp-plugin-activator/vendor/autoload.php';
}

use SatoriDigital\PluginActivator\Config\ActivatorOptions;
use SatoriDigital\PluginActivator\Controllers\ActivationController;


// Instantiate options immediately so it's always available
$options = new ActivatorOptions();

error_log ('Plugin Activator - is_disabled: ' . $options->is_disabled() );
if ( $options->is_disabled() ) {
    // Do nothing
    return;
}
 
add_action( 'after_setup_theme', function() {
	if ( ! defined( 'SATORI_PLUGIN_ACTIVATOR_LOADED' ) ) {
		define( 'SATORI_PLUGIN_ACTIVATOR_LOADED', true );
		( new ActivationController() )->run();
	}
});



