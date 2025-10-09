<?php
declare(strict_types=1);

namespace SatoriDigital\PluginActivator\Activators;

use SatoriDigital\PluginActivator\Interfaces\ActivatorInterface;
use SatoriDigital\PluginActivator\Helpers\ActivationUtils;

final class FilterActivator implements ActivatorInterface
{
    private array $filters;

    public function __construct(array $config)
    {
        // Config key you used previously was "filtered"
        $this->filters = $config['filtered'] ?? [];
    }

    public function getType(): string
    {
        return 'filter';
    }

    public function collect(): array
    {
        $items = [];

        // Support both shapes:
        // 1) ["filtered" => [ { hook, priority, plugins: [ {file...}, ... ] }, ... ]]
        // 2) legacy nested shapes if any
        foreach ($this->filters as $f) {
            if (empty($f['hook']) || empty($f['plugins']) || !is_array($f['plugins'])) {
                error_log('[FilterActivator] Invalid filter entry (missing hook/plugins).');
                continue;
            }
            $items[] = [
                'type'  => $this->getType(),
                'order' => (int)($f['order'] ?? 0),
                'data'  => $f,
            ];
        }

        return $items;
    }

    public function handle(array $item): void
    {
        $f        = $item['data'];
        $hook     = $f['hook'];
        $priority = (int)($f['priority'] ?? 10);
        $plugins  = $f['plugins'];

        // Defer activation of these plugins to the specified hook.
        add_action($hook, static function () use ($plugins) {
            // Activate respecting each plugin's file/version/required flags inside ActivationUtils::activate_plugins
            ActivationUtils::activate_plugins($plugins);
        }, $priority, 0);
    }
}
