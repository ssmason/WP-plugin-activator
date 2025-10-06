<?php
/**
 * Satori Digital Plugin Activator
 *
 * @category Plugin_Loader
 * @package  SatoriDigital\Activate
 */

declare( strict_types=1 );

namespace SatoriDigital\PluginActivator;

/**
 * Activate Class
 *
 * Class to load plugins either at network or site level
 */
class ActivationController.php {
 
	/**
	 * Array of plugin keys required to activate plugins at specific points ie theme/settings/filters
	 *
	 * @var array
	 */
	private $keys;
 
	/**
	 * Plugin constructor.
	 */
	public function __construct() {
		$this->set_keys( get_option( 'stylesheet', '' ) );
	}
 
	/**
	 * Set the theme keys, plugins, filters & settings
	 * 
	 * @param String $theme Name of the theme for which config is required.
	 */
	public function set_keys( string $theme ): void {

		$config = $this->get_json_config( $theme );
		if ( ! is_array( $config ) ) {
			error_log( sprintf(
				'[PluginActivator] Invalid JSON config for theme "%s". Falling back to empty config.',
				$theme
			) );
			$config = [];
		}

		// Ensure top-level keys exist
		$config = array_merge(
			[
				'plugins'   => [],
				'filtered'  => [],
				'settings'  => [],
				'deactivate'=> [],
				'groups'    => []
			],
			$config
		);

		// Normalize plugins: convert string entries to structured arrays with defaults
		$normalized_plugins = [];
		if ( is_array( $config['plugins'] ) ) {
			foreach ( $config['plugins'] as $plugin ) {
				if ( is_string( $plugin ) ) {
					$normalized_plugins[] = [
						'file'     => $plugin,
						'required' => false,
						'version'  => null,
						'order'    => 10
					];
				} elseif ( is_array( $plugin ) && ! empty( $plugin['file'] ) ) {
					$normalized_plugins[] = array_merge(
						[
							'required' => false,
							'version'  => null,
							'order'    => 10
						],
						$plugin
					);
				}
				// Invalid plugin entries are silently skipped
			}
		}

		$config['plugins'] = $normalized_plugins;
		$this->keys = $config;
	}

	/**
	 * Get theme array keys
	*/
	public function get_keys():array {
		return $this->keys;
	} 
 
	/**
	 * Main function runninng activation controllers
	*/
	public function run():bool { 
 
		//$this->network_activation(); 
		$this->theme_activation();
		$this->filter_activation();
		// $this->settings_activation();
		return true;
	}
 
	/**
	 * Ensure required WordPress plugin functions are loaded.
	 * 
	 * @return void
	*/
	private function ensure_wp_plugin_functions(): void {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	/**
	 * Get the version of a plugin from its file header.
	 * 
	 * @param string $abs Absolute path to the plugin file.
	 * @return string|null Plugin version or null if not found.
	*/
	private function get_plugin_version( string $abs ): ?string {
		if ( ! file_exists( $abs ) ) {
			return null;
		}
		$headers = get_file_data( $abs, [ 'Version' => 'Version' ] );
		return ! empty( $headers['Version'] ) ? $headers['Version'] : null;
	}

	/**
	 * Check if an installed version satisfies a version constraint.
	 * Supports operators like >=, <=, ==, <, >, =.
	 * 
	 * @param string|null $installed Installed plugin version.
	 * @param string|null $constraint Version constraint (e.g. ">=3.14.0").
	 * @return bool True if the version satisfies the constraint.
	*/
	private function satisfies_version( ?string $installed, ?string $constraint ): bool {
		if ( empty( $constraint ) || empty( $installed ) ) {
			return true;
		}
		if ( ! preg_match( '/^(<=|>=|==|=|<|>)\s*([0-9A-Za-z\.\-\+]+)$/', $constraint, $m ) ) {
			return true;
		}
		$op  = $m[1] === '=' ? '==' : $m[1];
		$ver = $m[2];
		return version_compare( $installed, $ver, $op );
	}

	/**
	 * Evaluate the state of all plugins listed in the theme config.
	 * Checks if plugins exist, are active, and meet version requirements.
	 * Logs missing plugins and version mismatches.
	 * 
	 * @return array Evaluation results including to_activate, missing, version_issues, and a detailed report.
	*/
	private function evaluate_theme_plugins(): array {
		$this->ensure_wp_plugin_functions();

		$plan = [
			'to_activate'     => [],
			'missing'         => [],
			'version_issues'  => [],
			'report'          => [],
		];

		foreach ( $this->keys['plugins'] as $p ) {
			$file      = $p['file'];
			$abs       = WP_PLUGIN_DIR . '/' . $file;
			$basename  = plugin_basename( $abs );
			$exists    = file_exists( $abs );
			$active    = $exists ? is_plugin_active( $basename ) : false;
			$installed = $exists ? $this->get_plugin_version( $abs ) : null;
			$ok        = $this->satisfies_version( $installed, $p['version'] ?? null );

			if ( ! $exists ) {
				$plan['missing'][] = $file;

				error_log(
					sprintf(
						'[PluginActivator] Missing plugin file: %s (declared in theme config)',
						$file
					)
				);

			} elseif ( ! $active ) {
				$plan['to_activate'][] = $basename;
			}

			if ( $exists && ! $ok ) {
				$plan['version_issues'][ $basename ] = [ $installed, $p['version'] ?? null ];

				error_log(
					sprintf(
						'[PluginActivator] Version mismatch for %s. Installed: %s, Required: %s',
						$basename,
						$installed ?? 'unknown',
						$p['version'] ?? 'unspecified'
					)
				);
			}

			$plan['report'][] = [
				'file'              => $file,
				'basename'          => $basename,
				'exists'            => $exists,
				'active'            => $active,
				'installed_version' => $installed,
				'version_ok'        => $ok,
				'required'          => (bool) ( $p['required'] ?? false ),
				'order'             => (int) ( $p['order'] ?? 10 ),
			];
		}

		return $plan;
	}
 
	/**
	 * Activate plugins that are specific to the current theme.
	 * Evaluates plugin state first, then activates as needed.
	 * Logs any missing plugins or version issues.
	 * 
	 * @return void
	*/
	private function theme_activation(): void {

		if ( isset( $this->get_keys()['plugins'] ) && is_array( $this->get_keys()['plugins'] ) ) {
			$plan = $this->evaluate_theme_plugins();

			// Activate any plugins that are not currently active
			if ( ! empty( $plan['to_activate'] ) ) {
				$this->activate_plugins( $plan['to_activate'] );
			}

			// Log missing plugins
			if ( ! empty( $plan['missing'] ) ) {
				foreach ( $plan['missing'] as $missing_plugin ) {
					error_log(
						sprintf(
							'[PluginActivator] Theme activation: Missing plugin "%s" — cannot activate.',
							$missing_plugin
						)
					);
				}
			}

			// Log version issues
			if ( ! empty( $plan['version_issues'] ) ) {
				foreach ( $plan['version_issues'] as $basename => $versions ) {
					list( $installed, $required ) = $versions;
					error_log(
						sprintf(
							'[PluginActivator] Theme activation: Version issue for "%s". Installed: %s, Required: %s',
							$basename,
							$installed ?? 'unknown',
							$required ?? 'unspecified'
						)
					);
				}
			}
		}
	}

	/**
	 * Activate an array of plugins by their basenames using WordPress core functions.
	 * Logs any activation errors.
	 * 
	 * @param array $plugins Array of plugin basenames (e.g. 'query-monitor/query-monitor.php').
	 * @return void
	*/
	private function activate_plugins( array $plugins ): void {
		$this->ensure_wp_plugin_functions();

		foreach ( $plugins as $basename ) {
			if ( ! is_plugin_active( $basename ) ) {
				$result = activate_plugin( $basename );

				if ( is_wp_error( $result ) ) {
					error_log(
						sprintf(
							'[PluginActivator] Failed to activate plugin "%s": %s',
							$basename,
							$result->get_error_message()
						)
					);
				} else {
					error_log(
						sprintf(
							'[PluginActivator] Activated plugin "%s".',
							$basename
						)
					);
				}
			}
		}
	}

	/**
	 * Activate plugins on specific WordPress hooks based on the "filtered" configuration.
	 * Each entry specifies a hook, optional priority, and plugins to activate when that hook fires.
	 * Logs any misconfigured entries for easier debugging.
	 * 
	 * @return void
	*/
	private function filter_activation(): void {
		if ( empty( $this->get_keys()['filtered'] ) || ! is_array( $this->get_keys()['filtered'] ) ) {
			return;
		}

		foreach ( $this->get_keys()['filtered'] as $entry ) {
			$hook     = $entry['hook']    ?? null;
			$plugins  = $entry['plugins'] ?? [];
			$priority = isset( $entry['priority'] ) ? (int) $entry['priority'] : 10;

			// Basic validation
			if ( empty( $hook ) || empty( $plugins ) || ! is_array( $plugins ) ) {
				error_log(
					sprintf(
						'[PluginActivator] Invalid filtered activation entry detected: %s',
						wp_json_encode( $entry )
					)
				);
				continue;
			}

			add_action(
				$hook,
				function() use ( $plugins, $hook ) {
					$this->activate_plugins( $plugins );

					error_log(
						sprintf(
							'[PluginActivator] Filtered activation triggered on hook "%s" for plugins: %s',
							$hook,
							implode( ', ', $plugins )
						)
					);
				},
				$priority
			);
		}
	}

	/**
	 * Activate plugins conditionally based on WordPress option values,
	 * according to the "settings" configuration in the JSON file.
	 * Supports basic operators: equals, not_equals, contains, not_empty.
	 * 
	 * @return void
	*/
	private function settings_activation(): void {
		if ( empty( $this->get_keys()['settings'] ) || ! is_array( $this->get_keys()['settings'] ) ) {
			return;
		}

		foreach ( $this->get_keys()['settings'] as $rule ) {
			$field    = $rule['field']    ?? null;
			$operator = $rule['operator'] ?? 'equals';
			$value    = $rule['value']    ?? null;
			$plugins  = $rule['plugins']  ?? [];

			// Basic rule validation
			if ( empty( $field ) || empty( $plugins ) || ! is_array( $plugins ) ) {
				error_log(
					sprintf(
						'[PluginActivator] Invalid settings activation rule: %s',
						wp_json_encode( $rule )
					)
				);
				continue;
			}

			$current_value = get_option( $field );

			$condition_met = false;
			switch ( $operator ) {
				case 'equals':
					$condition_met = (string) $current_value === (string) $value;
					break;

				case 'not_equals':
					$condition_met = (string) $current_value !== (string) $value;
					break;

				case 'contains':
					if ( is_string( $current_value ) ) {
						$condition_met = strpos( $current_value, (string) $value ) !== false;
					} elseif ( is_array( $current_value ) ) {
						$condition_met = in_array( $value, $current_value, true );
					}
					break;

				case 'not_empty':
					$condition_met = ! empty( $current_value );
					break;

				default:
					error_log(
						sprintf(
							'[PluginActivator] Unsupported operator "%s" in settings rule for field "%s".',
							$operator,
							$field
						)
					);
					break;
			}

			if ( $condition_met ) {
				add_action(
					'plugins_loaded',
					function() use ( $plugins, $field, $operator, $value ) {
						$this->activate_plugins( $plugins );
						error_log(
							sprintf(
								'[PluginActivator] Settings activation triggered for field "%s" (operator: %s, value: %s) — activated plugins: %s',
								$field,
								$operator,
								is_scalar( $value ) ? $value : wp_json_encode( $value ),
								implode( ', ', $plugins )
							)
						);
					},
					10
				);
			}
		}
	}


}
