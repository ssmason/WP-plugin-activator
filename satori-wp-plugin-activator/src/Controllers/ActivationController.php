<?php
/**
 * Satori Digital Plugin Activator Controller
 *
 * @category Plugin_Loader
 * @package  SatoriDigital\Activate
 */

declare( strict_types=1 );

namespace SatoriDigital\PluginActivator\Controllers;

use SatoriDigital\PluginActivator\Activators\GroupActivator;
use SatoriDigital\PluginActivator\Activators\PluginActivator;
use SatoriDigital\PluginActivator\Activators\FilterActivator;
use SatoriDigital\PluginActivator\Activators\SettingsActivator;
use SatoriDigital\PluginActivator\Helpers\ConfigLoader;
use SatoriDigital\PluginActivator\Helpers\ActivationUtils;
use SatoriDigital\PluginActivator\Interfaces\ActivatorInterface;

class ActivationController {

	/**
	 * Normalized plugin activation configuration loaded from JSON.
	 *
	 * @var array
	 */
	private array $config;

	/**
	 * @var array
	 */
	private array $activators = [];

	/**
	 * Controller constructor.
	 */
	public function __construct() {
		// Load and merge group config early
		$loader       = new ConfigLoader();
		$this->config = $loader->load();
		$this->config = GroupActivator::merge_group_plugins( $this->config );

		// Register activators in strict execution order
		$this->activators = [
			new GroupActivator( $this->config ),
			new PluginActivator( $this->config ),
			new FilterActivator( $this->config ),
			new SettingsActivator( $this->config ),
		];
	}

	/**
	 * Run all activators in sequence and then deactivate unlisted plugins.
	 */
	public function run(): void {
		// Activate in deterministic order
		foreach ( $this->activators as $activator ) {
			if ( $activator instanceof ActivatorInterface ) {
				$activator->activate();
			}
		}

		// Collect all configured plugins for the deactivation sweep
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

		ActivationUtils::deactivate_unlisted_plugins( $configured_plugins );
	}
}
