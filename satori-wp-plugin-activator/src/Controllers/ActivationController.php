<?php

namespace SatoriDigital\PluginActivator\Controllers;

use SatoriDigital\PluginActivator\Helpers\ConfigLoader;
use SatoriDigital\PluginActivator\Activators\PluginActivator;
use SatoriDigital\PluginActivator\Activators\FilterActivator;
use SatoriDigital\PluginActivator\Activators\SettingsActivator;
use SatoriDigital\PluginActivator\Activators\GroupActivator;
use SatoriDigital\PluginActivator\Helpers\ActivationUtils;

/**
 * Main Activation Controller.
 * Collects all activation instructions, sorts globally, then applies.
 */
class ActivationController {

	/**
	 * @var array
	 */
	protected $config;

	/**
	 * @var array
	 */
	protected $activators = [];

	/**
	 * Controller constructor.
	 * Loads configuration into $this->config and initializes activators.
	 */
	public function __construct() {

		$loader        = new ConfigLoader();
		$this->config  = $loader->load();

		$this->activators = [
			new PluginActivator( $this->config ),
			new FilterActivator( $this->config ),
			new SettingsActivator( $this->config ),
			new GroupActivator( $this->config ),
		];
	}

	/**
	 * Run activation workflow.
	 */
	public function run() {
    $collected = [];

    foreach ( $this->activators as $activator ) {
        $collected = array_merge( $collected, $activator->collect() );
    }

    // Sort globally by order
    usort( $collected, function ( $a, $b ) {
        return ($a['order'] ?? 10) <=> ($b['order'] ?? 10);
    });

    // Deactivate unlisted
    ActivationUtils::deactivate_unlisted_plugins( $collected );

    // Check versions
    ActivationUtils::check_versions( $collected );

    // Activate in order
    ActivationUtils::activate_plugins( $collected );
}

	/**
	 * Process activation, deactivation, and version checks.
	 *
	 * @param array $items
	 */
	protected function process_activation( array $items ) {
		$to_activate   = [];
		$to_deactivate = [];

		foreach ( $items as $item ) {
			$file     = $item['file'] ?? null;
			$version  = $item['version'] ?? null;
			$required = $item['required'] ?? false;

			if ( ! $file ) {
				continue;
			}

			// Check if file exists and handle missing files
			if ( ActivationUtils::is_plugin_file_missing( $file ) ) {
				if ( $required ) {
					ActivationUtils::log_missing_plugin( $file );
				}
				$to_deactivate[] = $file;
				continue;
			}

			// Version check
			if ( $version && ! ActivationUtils::check_version( $file, $version ) ) {
				ActivationUtils::log_version_mismatch( $file, $version );
				$to_deactivate[] = $file;
				continue;
			}

			// Normal activation
			$to_activate[] = $file;
		}

		// Deactivate plugins not required or failing checks
		if ( ! empty( $to_deactivate ) ) {
			ActivationUtils::deactivate_plugins( $to_deactivate );
		}

		// Activate required plugins in sorted order
		if ( ! empty( $to_activate ) ) {
			ActivationUtils::activate_plugins( $to_activate );
		}
	}
}
