<?php
/**
 * Satori Digital Plugin Activator
 *
 * @category Plugin_Loader
 * @package  SatoriDigital\Activate
 */

declare( strict_types=1 );

namespace SatoriDigital\PluginActivator\Activators;

use SatoriDigital\PluginActivator\Interfaces\ActivatorInterface;
use SatoriDigital\PluginActivator\Helpers\ActivationUtils;


/**
 * Activate Class
 *
 * Class to load plugins either at network or site level
 */
class PluginActivator implements ActivatorInterface {
 
	/**
	 * Plugin activation configuration.
	 *
	 * @var array
	 */
	private array $config;
 
	/**
	 * Plugin constructor.
	 */
	public function __construct( array $config ) {
		$this->config = $config;
	}
	
	/**
	 * Evaluate and activate all plugins defined in the configuration.
	 *
	 * This method checks the filesystem, activation state, and version requirements
	 * for each plugin. It builds an activation plan, logs any missing plugins or
	 * version mismatches, and activates any plugins that are not currently active.
	 * It also deactivates any plugins that are currently active but not listed in
	 * the configuration.
	 *
	 * @return void
	 */
	public function activate(): void {
		$plan = ActivationUtils::evaluate_plugins( $this->config);

		if ( ! empty( $plan['to_activate'] ) ) {
			$immediate = [];
			$deferred  = [];

			foreach ( $plan['to_activate'] as $plugin ) {
				if ( ! empty( $plugin['defer'] ) && $plugin['defer'] === true ) {
					$deferred[] = $plugin;
				} else {
					$immediate[] = $plugin;
				}
			}
			// Activate immediate plugins right away
			if ( ! empty( $immediate ) ) {
				ActivationUtils::activate_plugins( $immediate );
			}

			// Defer activation of others to plugins_loaded
			if ( ! empty( $deferred ) ) {
				add_action( 'plugins_loaded', function() use ( $deferred ) {
					ActivationUtils::activate_plugins( $deferred );
				}, 1 );
			}
		}
	}
}
