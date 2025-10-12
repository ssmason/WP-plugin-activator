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
use SatoriDigital\PluginActivator\Helpers\ActivationUtils;

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
    /**
     * Array of settings configurations.
     *
     * @var array<int, array{field:string, operator:string, value:mixed, plugins:array}>
     */
    private array $settings;

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
        return 'setting';
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
        $items = [];
        foreach ($this->settings as $s) {
            if (
                empty($s['field'])
                || !array_key_exists('value', $s)
                || empty($s['plugins'])
                || !is_array($s['plugins'])
            ) {
                error_log('[SettingsActivator] Invalid settings entry (need field, value, plugins[]).');
                continue;
            }

            $items[] = [
                'type'  => $this->get_type(),
                'order' => (int)($s['order'] ?? 0),
                'data'  => $s,
            ];
        }

        return $items;
    }

    /**
     * Handle a single settings item activation.
     *
     * Orchestrates the conditional activation process by evaluating
     * the configured condition and activating plugins if met.
     *
     * @param array $item Settings item containing condition and plugin data.
     * @return void
     * @since 1.0.0
     */
    public function handle(array $item): void
    {
        $condition_config = $this->extract_condition_config($item);

        if ($this->evaluate_condition($condition_config)) {
            $this->activate_conditional_plugins($condition_config['plugins']);
        }
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
        $data = $item['data'];

        return [
            'field'    => $data['field'],
            'operator' => $data['operator'] ?? 'equals',
            'expected' => $data['value'],
            'plugins'  => $data['plugins'],
            'actual'   => get_option($data['field']),
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
        $actual = $config['actual'];
        $expected = $config['expected'];

        return match ($operator) {
            'equals'     => $this->compare_equals($actual, $expected),
            'not_equals' => $this->compare_not_equals($actual, $expected),
            'contains'   => $this->compare_contains($actual, $expected),
            'in'         => $this->compare_in($actual, $expected),
            default      => $this->compare_equals($actual, $expected),
        };
    }

    /**
     * Compare values for equality.
     *
     * @param mixed $actual Actual option value.
     * @param mixed $expected Expected value.
     * @return bool True if values are equal.
     * @since 1.0.0
     */
    private function compare_equals($actual, $expected): bool
    {
        return (string)$actual === (string)$expected;
    }

    /**
     * Compare values for inequality.
     *
     * @param mixed $actual Actual option value.
     * @param mixed $expected Expected value.
     * @return bool True if values are not equal.
     * @since 1.0.0
     */
    private function compare_not_equals($actual, $expected): bool
    {
        return (string)$actual !== (string)$expected;
    }

    /**
     * Check if actual value contains expected string.
     *
     * @param mixed $actual Actual option value.
     * @param mixed $expected Expected substring.
     * @return bool True if actual contains expected.
     * @since 1.0.0
     */
    private function compare_contains($actual, $expected): bool
    {
        if (!is_string($actual) || !is_string($expected)) {
            return false;
        }

        return strpos($actual, $expected) !== false;
    }

    /**
     * Check if actual value is in expected array.
     *
     * @param mixed $actual Actual option value.
     * @param mixed $expected Expected array of values.
     * @return bool True if actual is in expected array.
     * @since 1.0.0
     */
    private function compare_in($actual, $expected): bool
    {
        if (!is_array($expected)) {
            return false;
        }

        return in_array($actual, $expected, true);
    }

    /**
     * Activate plugins based on satisfied condition.
     *
     * @param array $plugins Array of plugin specifications to activate.
     * @return void
     * @since 1.0.0
     */
    private function activate_conditional_plugins(array $plugins): void
    {
        ActivationUtils::activate_plugins($plugins);
    }
}
