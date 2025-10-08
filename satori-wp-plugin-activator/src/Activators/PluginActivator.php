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
		$plan = $this->evaluate_plugins();

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


	/**
	 * Evaluate plugin configuration to determine which plugins need activation.
	 *
	 * Checks for plugin file existence, activation state, and version requirements.
	 * Logs any missing files or version mismatches, and builds a plan of plugins
	 * that should be activated.
	 *
	 * @return array {
	 *     @type array $to_activate    List of plugin slugs to activate.
	 *     @type array $missing        List of missing plugin slugs.
	 *     @type array $version_issues List of plugins with version mismatches.
	 * }
	 */
	private function evaluate_plugins(): array {
		$plan = [
			'to_activate'    => [],
			'missing'        => [],
			'version_issues' => [],
		];

		if ( empty( $this->config['plugins'] ) || ! is_array( $this->config['plugins'] ) ) {
			return $plan;
		}

		foreach ( $this->config['plugins'] as $plugin ) {
			$slug     = is_array( $plugin ) ? ( $plugin['file'] ?? '' ) : $plugin;
			$required = is_array( $plugin ) ? ( $plugin['required'] ?? false ) : false;
			$version  = is_array( $plugin ) ? ( $plugin['version'] ?? null ) : null;

			if ( empty( $slug ) ) {
				continue;
			}

			// File existence check
			if ( ! ActivationUtils::plugin_file_exists( $slug ) ) {
				$plan['missing'][] = $slug;
				error_log(
					sprintf( '[PluginActivator] Missing plugin file: %s', $slug )
				);
				continue;
			}

			// Version check if required
			if ( $version ) {
				$current_version = ActivationUtils::get_plugin_version( $slug );
				if ( $current_version && ! ActivationUtils::satisfies_version( $current_version, $version ) ) {
					$plan['version_issues'][] = [
						'slug'     => $slug,
						'required' => $version,
						'current'  => $current_version,
					];

					error_log(
						sprintf(
							'[PluginActivator] Version mismatch for %s — required: %s, current: %s',
							$slug,
							$version,
							$current_version
						)
					);

					// Optional: skip activation on mismatch
					continue;
				}
			}

			// Activation state check
			if ( ! is_plugin_active( $slug ) ) {
				$plan['to_activate'][] = $slug;
			}
		}

		return $plan;
	}


}
