<?php
/**
 * Satori Digital Plugin Activator
 *
 * @category Plugin_Loader
 * @package  SatoriDigital\Activate
 */

declare( strict_types=1 );

namespace SatoriDigital\PluginActivator\Activators;

use SatoriDigital\PluginActivator\Helpers\ConfigLoader;
use SatoriDigital\PluginActivator\Helpers\ActivationUtils;

use SatoriDigital\PluginActivator\Interfaces\ActivatorInterface;
/**
 * Activate Class
 *
 * Class to load plugins either at network or site level
 */
class FilterActivator implements ActivatorInterface {
 
    /**
	 * Plugin activation configuration.
	 *
	 * @var array
	*/
	private array $config;

	public function __construct( array $config ) {
		$this->config = $config;
	}

	/**
	 * Register hook-based (filtered) plugin activations defined in the configuration.
	 * 
	 * For each filtered entry, this method validates the structure and attaches
	 * a callback to the specified WordPress hook. When that hook fires, the
	 * listed plugins are activated. Invalid entries and triggered activations
	 * are logged for debugging.
	 *
	 * @return void
	*/
	public function activate(): void {
		if ( empty( $this->config['filtered'] ) || ! is_array( $this->config['filtered'] ) ) {
			return;
		}

		foreach ( $this->config['filtered'] as $entry ) {
			$hook     = $entry['hook']    ?? null;
			$plugins  = $entry['plugins'] ?? [];
			$priority = isset( $entry['priority'] ) ? (int) $entry['priority'] : 10;

			if ( empty( $hook ) || empty( $plugins ) || ! is_array( $plugins ) ) {
				error_log(
					sprintf(
						'[PluginActivator] Invalid filtered activation entry: %s',
						wp_json_encode( $entry )
					)
				);
				continue;
			}

			add_action(
				$hook,
				function() use ( $plugins, $hook ) {

					// Build a temporary config matching the evaluate_plugins format
					$temp_config = [ 'plugins' => [] ];
					foreach ( $plugins as $plugin ) {
						$temp_config['plugins'][] = is_array( $plugin ) ? $plugin : [ 'file' => $plugin ];
					}

					$plan = ActivationUtils::evaluate_plugins( $temp_config );

					if ( ! empty( $plan['to_activate'] ) ) {
						ActivationUtils::activate_plugins( $plan['to_activate'] );

						error_log(
							sprintf(
								'[PluginActivator] Filtered activation triggered on hook "%s" for plugins: %s',
								$hook,
								implode( ', ', $plan['to_activate'] )
							)
						);
					}
				},
				$priority
			);
		}
	}

}
