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
class SettingsActivator implements ActivatorInterface {
 
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
	 * Evaluate settings-based conditions and register conditional plugin activations.
	 *
	 * Each rule defines a WordPress option, an operator, and a value. If the
	 * condition evaluates to true, the associated plugins are activated on
	 * the 'plugins_loaded' hook. Invalid rules and unsupported operators
	 * are logged for debugging.
	 *
	 * @return void
	*/
	public function activate(): void {
		if ( empty( $this->config['settings'] ) || ! is_array( $this->config['settings'] ) ) {
			return;
		}

		foreach ( $this->config['settings'] as $rule ) {
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
			$condition_met = $this->evaluate_condition( $current_value, $operator, $value, $field );

			if ( $condition_met ) {
				add_action(
					'plugins_loaded',
					function() use ( $plugins, $field, $operator, $value ) {
						ActivationUtils::activate_plugins( $plugins );
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
	/**
	 * Evaluate a settings-based condition against the current option value.
	 *
	 * Supports basic operators:
	 * - equals
	 * - not_equals
	 * - contains (string or array)
	 * - not_empty
	 *
	 * Logs unsupported operators for debugging.
	 *
	 * @param mixed  $current_value Current value of the WordPress option.
	 * @param string $operator      Comparison operator to apply.
	 * @param mixed  $value         Expected value to compare against.
	 * @param string $field         Option field name (for logging).
	 *
	 * @return bool True if the condition is satisfied, false otherwise.
	*/
	private function evaluate_condition( $current_value, string $operator, $value, string $field ): bool {
		switch ( $operator ) {
			case 'equals':
				return (string) $current_value === (string) $value;

			case 'not_equals':
				return (string) $current_value !== (string) $value;

			case 'contains':
				if ( is_string( $current_value ) ) {
					return strpos( $current_value, (string) $value ) !== false;
				}
				if ( is_array( $current_value ) ) {
					return in_array( $value, $current_value, true );
				}
				return false;

			case 'not_empty':
				return ! empty( $current_value );

			default:
				error_log(
					sprintf(
						'[PluginActivator] Unsupported operator "%s" in settings rule for field "%s".',
						$operator,
						$field
					)
				);
				return false;
		}
	}


}
