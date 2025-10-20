<?php
/**
 * Settings Activator
 *
 * Conditional plugin activator that activates plugins based on WordPress
 * option values. Supports various comparison operators for flexible
 * conditional activation based on site configuration.
 *
 * @category Plugin_Activator
 * @package  SatoriDigital\PluginActivator\Activators
 * @author   Satori Digital
 * @license  GPL-2.0+
 * @link     https://satoridigital.com
 */

declare(strict_types=1);

namespace SatoriDigital\PluginActivator\Activators;

use SatoriDigital\PluginActivator\Interfaces\ActivatorInterface;

/**
 * Class SettingsActivator
 *
 * Provides conditional plugin activation based on WordPress option values.
 * Supports multiple comparison operators including equals, not_equals,
 * contains, and in-array checks for flexible configuration-based activation.
 *
 * @package SatoriDigital\PluginActivator\Activators
 * @since   1.0.0
 */
final class SettingsActivator implements ActivatorInterface
{
    private const TYPE = 'setting';

    /**
     * Array of settings configurations.
     *
     * @var array<int, array{field:string, operator?:string, value:mixed, plugins:array, order?:int}>
     */
    private readonly array $settings;

    /**
     * Constructor.
     *
     * @param array $config Configuration array containing settings specifications.
     * @since 1.0.0
     */
    public function __construct(array $config)
    {
        $this->settings = $config['settings'] ?? [];
    }

    /**
     * Get the activator type identifier.
     *
     * @return string The activator type.
     * @since 1.0.0
     */
    public function get_type(): string
    {
        return self::TYPE;
    }

    /**
     * Collect settings items for global ordering.
     *
     * Processes settings configurations and returns an array of items that can be
     * sorted globally by order/priority before conditional evaluation.
     *
     * @return array<int, array{type:string, order:int, data:array}> Array of settings items.
     * @since 1.0.0
     */
    public function collect(): array
    {
        if (empty($this->settings)) {
            return [];
        }

        $items = [];

        foreach ($this->settings as $setting) {
            if (!$this->is_valid_setting($setting)) {
                $this->log_invalid_setting($setting);
                continue;
            }

            if (!$this->handle($setting)) {
                continue;
            }

            $items[] = [
                'type'  => self::TYPE,
                'order' => (int)($setting['order'] ?? 0),
                'data'  => $setting,
            ];
        }

        return $items;
    }

    /**
     * Validate a settings entry.
     *
     * Ensures the configuration has the required fields and structure.
     *
     * @param array $setting Settings configuration.
     * @return bool True if valid, false otherwise.
     * @since 1.0.0
     */
    private function is_valid_setting(array $setting): bool
    {
        return !empty($setting['field'])
            && array_key_exists('value', $setting)
            && !empty($setting['plugins'])
            && is_array($setting['plugins']);
    }

    /**
     * Log invalid settings entry.
     *
     * @param array $setting The invalid configuration.
     * @return void
     * @since 1.0.0
     */
    private function log_invalid_setting(array $setting): void
    {
        $field = $setting['field'] ?? '(undefined)';
        error_log(sprintf(
            '[SettingsActivator] Invalid settings entry (need field, value, plugins[]). Field: "%s".',
            $field
        ));
    }

    /**
     * Handle a single settings item activation.
     *
     * Orchestrates the conditional activation process by evaluating
     * the configured condition and activating plugins if met.
     *
     * @param array $item Settings item containing condition and plugin data.
     * @return bool True if condition is satisfied and plugins should be activated.
     * @since 1.0.0
     */
    public function handle(array $item): bool
    {
        if (!isset($item['field']) || !isset($item['plugins'])) {
            return false;
        }

        $condition_config = $this->extract_condition_config($item);
        return $this->evaluate_condition($condition_config);
    }

    /**
     * Extract condition configuration from settings item.
     *
     * @param array $item Settings item data.
     * @return array Condition configuration with all required fields.
     * @since 1.0.0
     */
    private function extract_condition_config(array $item): array
    {
        [
            'field'    => $field,
            'operator' => $operator,
            'value'    => $expected,
            'plugins'  => $plugins,
        ] = $item + [
            'field'    => '',
            'operator' => 'equals',
            'value'    => null,
            'plugins'  => [],
        ];

        return [
            'field'    => $field,
            'operator' => $operator,
            'expected' => $expected,
            'plugins'  => $plugins,
            'actual'   => get_option($field),
        ];
    }

    /**
     * Evaluate whether the condition is met.
     *
     * @param array $config Condition configuration.
     * @return bool True if condition is satisfied.
     * @since 1.0.0
     */
    private function evaluate_condition(array $config): bool
    {
        $operator = $config['operator'];
        $actual   = $config['actual'];
        $expected = $config['expected'];

        return match ($operator) {
            'equals'     => $this->compare_equals($actual, $expected),
            'not_equals' => $this->compare_not_equals($actual, $expected),
            'contains'   => $this->compare_contains($actual, $expected),
            'in'         => $this->compare_in($actual, $expected),
            default      => $this->handle_unknown_operator($operator, $actual, $expected),
        };
    }

    /**
     * Handle unknown comparison operators.
     *
     * Logs a warning and defaults to strict equality comparison.
     *
     * @param string $operator Operator name.
     * @param mixed  $actual   Actual value.
     * @param mixed  $expected Expected value.
     * @return bool Comparison result (defaults to equality).
     * @since 1.0.0
     */
    private function handle_unknown_operator(string $operator, mixed $actual, mixed $expected): bool
    {
        error_log(sprintf('[SettingsActivator] Unknown operator "%s". Defaulting to "equals".', $operator));
        return $this->compare_equals($actual, $expected);
    }

    /**
     * Compare values for equality.
     *
     * @param mixed $actual   Actual option value.
     * @param mixed $expected Expected value.
     * @return bool True if values are equal.
     * @since 1.0.0
     */
    private function compare_equals(mixed $actual, mixed $expected): bool
    {
        return $actual !== false && (string)$actual === (string)$expected;
    }

    /**
     * Compare values for inequality.
     *
     * @param mixed $actual   Actual option value.
     * @param mixed $expected Expected value.
     * @return bool True if values are not equal.
     * @since 1.0.0
     */
    private function compare_not_equals(mixed $actual, mixed $expected): bool
    {
        return (string)$actual !== (string)$expected;
    }

    /**
     * Check if actual value contains expected substring.
     *
     * @param mixed $actual   Actual option value.
     * @param mixed $expected Expected substring.
     * @return bool True if actual contains expected.
     * @since 1.0.0
     */
    private function compare_contains(mixed $actual, mixed $expected): bool
    {
        return is_string($actual)
            && is_string($expected)
            && str_contains($actual, $expected);
    }

    /**
     * Check if actual value is in expected array.
     *
     * @param mixed $actual   Actual option value.
     * @param mixed $expected Expected array of values.
     * @return bool True if actual is in expected array.
     * @since 1.0.0
     */
    private function compare_in(mixed $actual, mixed $expected): bool
    {
        return is_array($expected)
            && in_array($actual, $expected, true);
    }
}
