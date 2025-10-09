<?php
declare(strict_types=1);

namespace SatoriDigital\PluginActivator\Activators;

use SatoriDigital\PluginActivator\Interfaces\ActivatorInterface;
use SatoriDigital\PluginActivator\Helpers\ActivationUtils;

final class GroupActivator implements ActivatorInterface
{
    private array $groups;

    public function __construct(array $config)
    {
        // Expect: "groups": { "staging": { "url": "...", "plugins":[...] }, "production": {...} }
        $this->groups = $config['groups'] ?? [];
    }

    public function getType(): string
    {
        return 'group';
    }

    public function collect(): array
    {
        $items = [];
        if (empty($this->groups)) {
            return $items;
        }

        $current = rtrim(site_url(), '/');

        foreach ($this->groups as $groupName => $g) {
            $url = !empty($g['url']) ? rtrim($g['url'], '/') : null;
            if (!$url || $url !== $current) {
                continue; // only collect for the matching environment
            }

            $plugins = $g['plugins'] ?? [];
            foreach ($plugins as $p) {
                if (empty($p['file'])) {
                    error_log(sprintf('[GroupActivator] %s: skipping plugin with missing "file".', $groupName));
                    continue;
                }
                $items[] = [
                    'type'  => $this->getType(),
                    'order' => (int)($p['order'] ?? 0),
                    'data'  => $p,
                ];
            }
        }

        return $items;
    }

    public function handle(array $item): void
    {
        $p = $item['data'];

        // Optional version check before activation
        if (!empty($p['version'])) {
            $current = ActivationUtils::get_plugin_version($p['file']);
            if ($current !== null && !ActivationUtils::satisfies_version($current, $p['version'])) {
                error_log(sprintf('[GroupActivator] Version mismatch for %s. Required %s, found %s.', $p['file'], $p['version'], $current));
            }
        }

        ActivationUtils::activate_plugins([$p]);
    }
}
