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
				error_log( '[PluginActivator] Empty plugin slug detected during activation.' );
				continue;
			}

			if ( ! self::plugin_file_exists( $slug ) ) {
				error_log(
					sprintf( '[PluginActivator] Plugin file not found: %s', $slug )
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

		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $slug, false, false );

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
