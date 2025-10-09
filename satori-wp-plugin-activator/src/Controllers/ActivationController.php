<?php
/**
 * Satori Digital Plugin Activator Controller
 *
 * @category Plugin_Loader
 * @package  SatoriDigital\Activate
 */

declare( strict_types=1 );

namespace SatoriDigital\PluginActivator\Controllers;

use SatoriDigital\PluginActivator\Activators\PluginActivator;
use SatoriDigital\PluginActivator\Activators\FilterActivator;
use SatoriDigital\PluginActivator\Activators\SettingsActivator;
use SatoriDigital\PluginActivator\Helpers\ConfigLoader;
use SatoriDigital\PluginActivator\Interfaces\ActivatorInterface;
use SatoriDigital\PluginActivator\Helpers\ActivationUtils;


/**
 * Activate Class
 *
 * Controller Class to load plugins either at network or site level
 */
class ActivationController {
 
	 
	/**
	 * List of activator instances (e.g. Plugin, Filter, Settings) that implement ActivatorInterface.
	 *
	 * @var array
	*/
	private array $activators = [];

	/**
	 * Normalized plugin activation configuration loaded from JSON.
	 *
	 * @var array
	*/
	private array $config;
 	
	/**
	 * Controller constructor.
	 * Loads configuration into an array $this->activators and initializes activators.
	 */
	public function __construct() {

		// Load config
		$loader = new ConfigLoader();
		$this->config = $loader->load();

		$this->activators = [
			new PluginActivator( $this->config ),
			new FilterActivator( $this->config ),
			new SettingsActivator( $this->config ),
			
		];
	} 
 
	/**
	 * Main function that loops through the activators and calls ->activate()
	*/
	public function run(): void {

		foreach ( $this->activators as $activator ) {
			if ( $activator instanceof ActivatorInterface ) {
				$activator->activate();
			}
		}

		$configured_plugins = array_merge(
			$this->config['plugins'] ?? [],
			array_merge(
				...array_map(
					fn( $f ) => $f['plugins'] ?? [],
					$this->config['filtered'] ?? []
				)
			),
			array_merge(
				...array_map(
					fn( $s ) => $s['plugins'] ?? [],
					$this->config['settings'] ?? []
				)
			)
		);

    	\SatoriDigital\PluginActivator\Helpers\ActivationUtils::deactivate_unlisted_plugins( $configured_plugins );

	}
}

