<?php
declare(strict_types=1);

namespace SatoriDigital\PluginActivator\Activators;

use SatoriDigital\PluginActivator\Interfaces\ActivatorInterface;
use SatoriDigital\PluginActivator\Helpers\ActivationUtils;

final class PluginActivator implements ActivatorInterface
{
    private array $plugins;

    public function __construct(array $config)
    {
        $this->plugins = $config['plugins'] ?? [];
    }

    public function getType(): string
    {
        return 'plugin';
    }

    public function collect(): array
    {
        $items = [];
        foreach ($this->plugins as $p) {
            if (empty($p['file'])) {
                error_log('[PluginActivator] Skipping plugin with missing "file".');
                continue;
            }
            $items[] = [
                'type'  => $this->getType(),
                'order' => (int)($p['order'] ?? 0),
                'data'  => $p,
            ];
        }
        return $items;
    }

    public function handle(array $item): void
    {
        $p    = $item['data'];
        $file = $p['file'];
        $req  = $p['required'] ?? false;
        $ver  = $p['version']  ?? null;

        // file exist check
        if (!ActivationUtils::plugin_file_exists($file)) {
            error_log(sprintf('[PluginActivator] Plugin file not found: %s', $file));
            if (!empty($req)) {
                // required plugin missing — log hard failure (do not wp_die() to avoid white-screens)
                error_log(sprintf('[PluginActivator] REQUIRED plugin missing: %s', $file));
            }
            return;
        }

        // version check (if provided)
        if (!empty($ver)) {
            $current = ActivationUtils::get_plugin_version($file);
            if ($current !== null && !ActivationUtils::satisfies_version($current, $ver)) {
                error_log(sprintf('[PluginActivator] Version mismatch for %s. Required %s, found %s.', $file, $ver, $current));
                if (!empty($req)) {
                    // required but version mismatch — still attempt activation, but log loudly
                    error_log(sprintf('[PluginActivator] REQUIRED plugin %s does not meet version %s. Proceeding to activate anyway.', $file, $ver));
                }
            }
        }

        // activate (uses WP core via ActivationUtils)
        ActivationUtils::activate_plugins([$p]);
    }
}
