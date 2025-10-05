<?php
/**
 * ConfigLoader
 *
 * Loads, normalizes, and validates plugin activation configuration from JSON files.
 *
 * Note - This class is small but powerful 
 * - every activator benefits from getting clean, validated config with defaults applied.
 * 
 * @category Plugin_Activator
 * @package  SatoriDigital\PluginActivator\Helpers
 */

declare( strict_types=1 );

namespace SatoriDigital\PluginActivator\Helpers;

class ConfigLoader {

	/**
	 * Load and normalize the plugin activation configuration.
	 *
	 * @return array Normalized configuration array.
	 */
	public function load(): array {
		$theme = get_option( 'stylesheet', '' );

		$config = $this->get_json_config( $theme );
		return $this->normalize_config( $config );
	}

	/**
	 * Read the JSON configuration file for a given key (e.g. theme or "network").
	 *
	 * @param string $key Identifier (usually theme slug).
	 * @return array Decoded config array or empty array on failure.
	 */
	private function get_json_config( string $key ): array {
		$file = trailingslashit( PLUGIN_ACTIVATION_CONFIG ) . $key . '.json';

		if ( ! file_exists( $file ) ) {
			error_log( sprintf( '[PluginActivator] Config file not found: %s', $file ) );
			return [];
		}

		$json = file_get_contents( $file );
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			error_log(
				sprintf(
					'[PluginActivator] JSON decode error in %s: %s',
					$file,
					json_last_error_msg()
				)
			);
			return [];
		}

		return is_array( $data ) ? $data : [];
	}

	/**
	 * Normalize configuration data structure.
	 * Ensures plugins are objects with default fields set, etc.
	 *
	 * @param array $config Raw configuration.
	 * @return array Normalized configuration.
	 */
	private function normalize_config( array $config ): array {
		if ( isset( $config['plugins'] ) && is_array( $config['plugins'] ) ) {
			$config['plugins'] = array_map( [ $this, 'normalize_plugin_entry' ], $config['plugins'] );
		}

		return $config;
	}

	/**
	 * Normalize a single plugin entry.
	 * Converts string entries to arrays with default fields.
	 *
	 * @param mixed $plugin Raw plugin entry.
	 * @return array Normalized plugin entry.
	 */
	private function normalize_plugin_entry( $plugin ): array {
		if ( is_string( $plugin ) ) {
			return [
				'slug'     => $plugin,
				'required' => false,
				'version'  => null,
				'order'    => 10,
			];
		}

		if ( is_array( $plugin ) ) {
			return array_merge(
				[
					'slug'     => '',
					'required' => false,
					'version'  => null,
					'order'    => 10,
				],
				$plugin
			);
		}

		// Invalid entry type
		error_log(
			sprintf(
				'[PluginActivator] Invalid plugin entry in config: %s',
				wp_json_encode( $plugin )
			)
		);
		return [];
	}
}
