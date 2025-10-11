<?php
/**
 * Plugin Activator
 *
 * Standard plugin activator that handles immediate plugin activation with
 * version validation, file existence checking, and requirement enforcement.
 * Primary activator for direct plugin management.
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
 * Class PluginActivator
 *
 * Handles immediate plugin activation with comprehensive validation including
 * version constraints, file existence, and requirement flags. Provides the
 * core plugin activation functionality for the activator system.
 *
 * @package SatoriDigital\PluginActivator\Activators
 * @since   1.0.0
 */
final class PluginActivator implements ActivatorInterface
{
    /**
     * Array of plugin specifications.
     *
     * @var array<int, array{file:string, required?:bool, version?:string, order?:int}>
     */
    private array $plugins;

    /**
     * Constructor.
     *
     * @param array $config Configuration array containing plugin specifications.
     * @since 1.0.0
     */
    public function __construct(array $config)
    {
        $this->plugins = $config['plugins'] ?? [];
    }

    /**
     * Get the activator type identifier.
     *
     * @return string The activator type.
     * @since 1.0.0
     */
    public function get_type(): string
    {
        return 'plugin';
    }

    /**
     * Collect plugin items for global ordering.
     *
     * Processes plugin configurations and returns an array of items that can be
     * sorted globally by order/priority before activation.
     *
     * @return array<int, array{type:string, order:int, data:array}> Array of plugin items.
     * @since 1.0.0
     */
    public function collect(): array
    {
        $items = [];
        foreach ($this->plugins as $p) {
            if (empty($p['file'])) {
                error_log('[PluginActivator] Skipping plugin with missing "file".');
                continue;
            }

            $items[] = [
                'type'  => $this->get_type(),
                'order' => (int)($p['order'] ?? 0),
                'data'  => $p,
            ];
        }

        return $items;
    }

    /**
     * Handle a single plugin item activation.
     *
     * Performs comprehensive validation including file existence, version
     * constraints, and requirement flags before attempting plugin activation.
     * Logs detailed information about any issues encountered.
     *
     * @param array $item Plugin item containing activation data.
     * @return void
     * @since 1.0.0
     */
    public function handle(array $item): void
    {
        $p    = $item['data'];
        $file = $p['file'];
        $req  = $p['required'] ?? false;
        $ver  = $p['version']  ?? null;

        // File existence check.
        if (!ActivationUtils::plugin_file_exists($file)) {
            error_log(sprintf('[PluginActivator] Plugin file not found: %s', $file));
            if (!empty($req)) {
                error_log(sprintf('[PluginActivator] REQUIRED plugin missing: %s', $file));
            }

            return;
        }

        // Version constraint validation.
        if (!empty($ver)) {
            $current = ActivationUtils::get_plugin_version($file);
            if ($current !== null && !ActivationUtils::satisfies_version($current, $ver)) {
                error_log(sprintf(
                    '[PluginActivator] Version mismatch for %s. Required %s, found %s.',
                    $file,
                    $ver,
                    $current
                ));
                if (!empty($req)) {
                    error_log(sprintf(
                        '[PluginActivator] REQUIRED plugin %s does not meet version %s. Proceeding to activate anyway.',
                        $file,
                        $ver
                    ));
                }
            }
        }

        // Activate plugin using utility methods.
        ActivationUtils::activate_plugins([$p]);
    }
}
