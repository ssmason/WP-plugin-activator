<?php
/**
 * Activation Utilities
 *
 * Shared helper functions used by multiple activators.
 *
 * @category Plugin_Activator
 * @package  SatoriDigital\PluginActivator\Helpers
 */

declare( strict_types=1 );

namespace SatoriDigital\PluginActivator\Helpers;

class ActivationUtils {

	/**
	 * Ensure WordPress plugin functions are loaded.
	 * Some functions like activate_plugin() are only available in wp-admin context.
	 *
	 * @return void
	 */
	public static function ensure_plugin_functions(): void {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
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
	public static function evaluate_plugins( array $config ): array {
		$plan = [
			'to_activate'    => [],
			'missing'        => [],
			'version_issues' => [],
		];

		if ( empty( $config['plugins'] ) || ! is_array( $config['plugins'] ) ) {
			return $plan;
		}

		foreach ( $config['plugins'] as $plugin ) {
			$slug    = is_array( $plugin ) ? ( $plugin['file'] ?? '' ) : $plugin;
			$version = is_array( $plugin ) ? ( $plugin['version'] ?? null ) : null;

			if ( empty( $slug ) ) {
				continue;
			}

			$check = self::evaluate_plugin_entry( $slug, $version );

			if ( $check['missing'] ) {
				$plan['missing'][] = $check['missing'];
				continue;
			}

			if ( $check['version_issue'] ) {
				$plan['version_issues'][] = $check['version_issue'];

				if ( is_plugin_active( $check['version_issue']['slug'] ) ) {
					deactivate_plugins( $check['version_issue']['slug'], true );
				}

				continue;
			}

			if ( $check['should_activate'] ) {
				$plan['to_activate'][] = $slug;
			}
		}

		return $plan;
	}
 
 
	public static function evaluate_plugin_entry( string $slug, ?string $required_version = null ): array {
		$result = [
			'missing'         => null,
			'version_issue'   => null,
			'should_activate' => false,
		];

		$abs = WP_PLUGIN_DIR . '/' . ltrim( $slug, '/' );

		// File existence
		if ( ( method_exists(__CLASS__, 'plugin_file_exists') && ! self::plugin_file_exists( $slug ) )
			|| ! file_exists( $abs ) ) {

			$result['missing'] = $slug;
			error_log( sprintf( '[PluginActivator] Missing plugin file: %s', $slug ) );
			return $result;
		}

		// Version check (if required)
		if ( ! empty( $required_version ) ) {
			$current_version = null;

			// Prefer your existing helper if present
			if ( method_exists( __CLASS__, 'get_plugin_version' ) ) {
				$current_version = self::get_plugin_version( $abs );
			} else {
				// Fallback: read header directly
				if ( function_exists( 'get_file_data' ) ) {
					$headers = get_file_data( $abs, [ 'Version' => 'Version' ] );
					$current_version = ! empty( $headers['Version'] ) ? $headers['Version'] : null;
				}
			}

			$current_version = $current_version ?? '';

			if ( $current_version === '' || ! self::satisfies_version( $current_version, $required_version ) ) {
				$result['version_issue'] = [
					'slug'     => $slug,
					'required' => $required_version,
					'current'  => $current_version !== '' ? $current_version : '(unknown)',
				];

				error_log( sprintf(
					'[PluginActivator] Version mismatch for %s — required: %s, current: %s',
					$slug,
					$required_version,
					$current_version !== '' ? $current_version : '(unknown)'
				) );

				return $result;
			}
		}

		// Only mark for activation if not already active
		if ( function_exists( 'is_plugin_active' ) && ! is_plugin_active( $slug ) ) {
			$result['should_activate'] = true;
		}

		return $result;
	}


	/**
	 * Activate an array of plugins by slug/path using core WordPress functions.
	 *
	 * @param array $plugins Array of plugin slugs or paths.
	 * @return void
	 */
	public static function activate_plugins( array $plugins ): void {
		self::ensure_plugin_functions(); 	
		foreach ( $plugins as $plugin ) {

			$slug = is_array( $plugin ) ? ( $plugin['file'] ?? '' ) : $plugin;
			
			if ( empty( $slug ) ) {
				error_log( '[ActivationUtils] Empty plugin slug detected during activation.' );
				continue;
			}

			if ( ! self::plugin_file_exists( $slug ) ) {
				error_log(
					sprintf( '[ActivationUtils] Plugin file not found: %s', $slug )
				);
				continue;
			}

			$active_plugins = get_option( 'active_plugins', [] );
			if ( in_array( $slug, $active_plugins, true ) ) {
				continue; // already active
			}

			$result = activate_plugin( $slug );

			if ( is_wp_error( $result ) ) {
				error_log(
					sprintf(
						'[PluginActivator] Failed to activate plugin %s: %s',
						$slug,
						$result->get_error_message()
					)
				);
			} else {
				error_log(
					sprintf( '[PluginActivator] Plugin activated: %s', $slug )
				);
			}
		}
	}

	/**
	 * Deactivate an array of plugins.
	 *
	 * @param array $plugins List of plugin paths to deactivate.
	 */
	public static function deactivate_plugins( array $plugins ): void {
		if ( empty( $plugins ) ) {
			return;
		}

		self::ensure_plugin_functions();

		// ✅ Call the global WordPress function explicitly
		\deactivate_plugins( $plugins, false, is_multisite() );
	}

	/**
	 * Check if the plugin file exists in the WP plugins directory.
	 *
	 * @param string $slug Plugin path relative to WP_PLUGIN_DIR.
	 * @return bool
	 */
	public static function plugin_file_exists( string $slug ): bool {
		return file_exists( WP_PLUGIN_DIR . '/' . $slug );
	}

	/**
	 * Get the installed version of a plugin, if available.
	 *
	 * @param string $slug Plugin path relative to WP_PLUGIN_DIR.
	 * @return string|null Version string or null if not found.
	 */
	public static function get_plugin_version( string $slug ): ?string {
		self::ensure_plugin_functions();

		$plugin_data = get_plugin_data( $slug, false, false );

		return $plugin_data['Version'] ?? null;
	}

	/**
	 * Check whether the installed version satisfies the required version.
	 * Currently supports >= comparison.
	 *
	 * @param string      $current  Current installed version.
	 * @param string|null $required Required minimum version.
	 * @return bool
	 */
	public static function satisfies_version( string $current, ?string $required ): bool {
	if ( empty( $required ) ) {
		return true;
	}

	// Match operator and version number, e.g. ">=3.0.0" or "==2.5.0"
	if ( preg_match( '/^(>=|<=|==|>|<)\s*(.+)$/', $required, $matches ) ) {
		$operator = $matches[1];
		$version  = $matches[2];

		return version_compare( $current, $version, $operator );
	}

	// Fallback: if no operator given, do a simple >= comparison
	return version_compare( $current, $required, '>=' );
}


	/**
	 * Deactivate any plugins that are currently active but not listed in the configuration.
	 *
	 * @param array $configured_plugins Array of normalized plugin entries (must include 'slug').
	 * @return void
	 */
	public static function deactivate_unlisted_plugins( array $configured_plugins ): void {
		self::ensure_plugin_functions();

		$active_plugins = get_option( 'active_plugins', [] );
		$config_slugs   = array_map(
			static function ( $plugin ) {
				return is_array( $plugin ) ? ( $plugin['file'] ?? '' ) : $plugin;
			},
			$configured_plugins
		);

		$config_slugs = array_filter( $config_slugs ); // remove empties
		$to_deactivate = array_diff( $active_plugins, $config_slugs );

		if ( empty( $to_deactivate ) ) {
			return;
		}

		self::deactivate_plugins( $to_deactivate );

		error_log(
			sprintf(
				'[PluginActivator] Deactivated unlisted plugins: %s',
				implode( ', ', $to_deactivate )
			)
		);
	}
}
