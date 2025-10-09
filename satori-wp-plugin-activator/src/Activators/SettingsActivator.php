<?php
declare(strict_types=1);

namespace SatoriDigital\PluginActivator\Activators;

use SatoriDigital\PluginActivator\Interfaces\ActivatorInterface;
use SatoriDigital\PluginActivator\Helpers\ActivationUtils;

final class SettingsActivator implements ActivatorInterface
{
    private array $settings;

    public function __construct(array $config)
    {
        $this->settings = $config['settings'] ?? [];
    }

    public function getType(): string
    {
        return 'setting';
    }

    public function collect(): array
    {
        $items = [];
        foreach ($this->settings as $s) {
            if (empty($s['field']) || !array_key_exists('value', $s) || empty($s['plugins']) || !is_array($s['plugins'])) {
                error_log('[SettingsActivator] Invalid settings entry (need field, value, plugins[]).');
                continue;
            }
            $items[] = [
                'type'  => $this->getType(),
                'order' => (int)($s['order'] ?? 0),
                'data'  => $s,
            ];
        }
        return $items;
    }

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
