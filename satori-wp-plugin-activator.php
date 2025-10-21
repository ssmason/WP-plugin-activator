<?php
/**
 * Satori-Digital Plugin Activator
 *
 * @category Plugin_Loader
 * @package  SatoriDigital\PluginActivator
 */


if ( ! defined( 'PLUGIN_ACTIVATION_CONFIG' ) ) {
	define(
		'PLUGIN_ACTIVATION_CONFIG',
		( defined( 'WPCOM_VIP_PRIVATE_DIR' ) ? WPCOM_VIP_PRIVATE_DIR : WP_CONTENT_DIR . '/private' ) . '/plugin-config'
	);
}

if ( file_exists( __DIR__ . '/satori-wp-plugin-activator/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/satori-wp-plugin-activator/vendor/autoload.php';
}

use SatoriDigital\PluginActivator\Config\ActivatorOptions;
use SatoriDigital\PluginActivator\Controllers\ActivationController;


// Instantiate options immediately so it's always available
$options = new ActivatorOptions();

// Log activation/deactivation changes
add_action('update_option_satori_plugin_activator_disabled', function($old_value, $new_value) {
    $user = wp_get_current_user();
    if ($new_value !== $old_value) {
        $action = $new_value ? 'Plugin De-activated' : 'Plugin Activated';
        error_log(sprintf('[PluginActivator] [%s] by user %s (ID: %d)', $action, $user->user_login, $user->ID));
    }
}, 10, 2);

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



