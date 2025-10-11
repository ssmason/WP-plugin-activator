<?php
/**
 * Group Activator
 *
 * Environment-aware plugin activator that activates plugins based on URL
 * matching. Allows different plugin sets for staging, production, and
 * development environments based on site URL configuration.
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
 * Class GroupActivator
 *
 * Provides environment-based plugin activation by matching the current site URL
 * against configured environment URLs. Enables different plugin configurations
 * for staging, production, and development environments.
 *
 * @package SatoriDigital\PluginActivator\Activators
 * @since   1.0.0
 */
final class GroupActivator implements ActivatorInterface
{
    /**
     * Array of group configurations keyed by environment name.
     *
     * @var array<string, array{url:string, plugins:array}>
     */
    private array $groups;

    /**
     * Constructor.
     *
     * @param array $config Configuration array containing group specifications.
     * @since 1.0.0
     */
    public function __construct(array $config)
    {
        $this->groups = $config['groups'] ?? [];
    }

    /**
     * Get the activator type identifier.
     *
     * @return string The activator type.
     * @since 1.0.0
     */
    public function get_type(): string
    {
        return 'group';
    }

    /**
     * Collect group items for the current environment.
     *
     * Matches the current site URL against configured group URLs and returns
     * plugin items for the matching environment only.
     *
     * @return array<int, array{type:string, order:int, data:array}> Array of group items for matching URL.
     * @since 1.0.0
     */
    public function collect(): array
    {
        $items = [];
        if (empty($this->groups)) {
            return $items;
        }

        $current = rtrim(site_url(), '/');

        foreach ($this->groups as $group_name => $g) {
            $url = !empty($g['url']) ? rtrim($g['url'], '/') : null;
            if (!$url || $url !== $current) {
                continue; // Only collect for the matching environment.
            }

            $plugins = $g['plugins'] ?? [];
            foreach ($plugins as $p) {
                if (empty($p['file'])) {
                    error_log(sprintf('[GroupActivator] %s: skipping plugin with missing "file".', $group_name));
                    continue;
                }

                $items[] = [
                    'type'  => $this->get_type(),
                    'order' => (int)($p['order'] ?? 0),
                    'data'  => $p,
                ];
            }
        }

        return $items;
    }

    /**
     * Handle a single group item activation.
     *
     * Validates plugin version constraints if specified and activates the plugin
     * using the standard activation utilities. Logs version mismatches for
     * debugging purposes.
     *
     * @param array $item Group item containing plugin data.
     * @return void
     * @since 1.0.0
     */
    public function handle(array $item): void
    {
        $p = $item['data'];

        // Optional version validation before activation.
        if (!empty($p['version'])) {
            $current = ActivationUtils::get_plugin_version($p['file']);
            if (
                $current !== null
                && !ActivationUtils::satisfies_version($current, $p['version'])
            ) {
                error_log(sprintf(
                    '[GroupActivator] Version mismatch for %s. Required %s, found %s.',
                    $p['file'],
                    $p['version'],
                    $current
                ));
            }
        }

        ActivationUtils::activate_plugins([$p]);
    }
}
