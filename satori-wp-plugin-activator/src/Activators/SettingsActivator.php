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
     * Evaluates the configured condition against the current WordPress option
     * value and activates plugins if the condition is met. Supports multiple
     * comparison operators for flexible conditional logic.
     *
     * @param array $item Settings item containing condition and plugin data.
     * @return void
     * @since 1.0.0
     */
    public function handle(array $item): void
    {
        $s        = $item['data'];
        $field    = $s['field'];
        $operator = $s['operator'] ?? 'equals';
        $expected = $s['value'];
        $plugins  = $s['plugins'];

        $actual = get_option($field);

        $condition_met = match ($operator) {
            'equals'      => (string)$actual === (string)$expected,
            'not_equals'  => (string)$actual !== (string)$expected,
            'contains'    => is_string($actual) && is_string($expected) && (strpos($actual, $expected) !== false),
            'in'          => is_array($expected) && in_array($actual, $expected, true),
            default       => (string)$actual === (string)$expected,
        };

        if ($condition_met) {
            ActivationUtils::activate_plugins($plugins);
        }
    }
}
