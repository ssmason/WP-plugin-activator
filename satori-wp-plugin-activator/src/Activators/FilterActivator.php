<?php
/**
 * Filter Activator
 *
 * Activates plugins based on WordPress filter/action hooks. Allows plugins
 * to be activated at specific points during WordPress execution based on
 * hook triggers with configurable priorities.
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
 * Class FilterActivator
 *
 * Provides hook-based plugin activation functionality. Allows plugins to be
 * activated when specific WordPress filters or actions are triggered, with
 * support for custom priorities and deferred activation.
 *
 * @package SatoriDigital\PluginActivator\Activators
 * @since   1.0.0
 */
final class FilterActivator implements ActivatorInterface
{
    /**
     * Array of filter configurations.
     *
     * @var array<int, array{hook:string, priority:int, plugins:array}>
     */
    private array $filters;

    /**
     * Constructor.
     *
     * @param array $config Configuration array containing filtered hook specifications.
     * @since 1.0.0
     */
    public function __construct(array $config)
    {
        $this->filters = $config['filtered'] ?? [];
    }

    /**
     * Get the activator type identifier.
     *
     * @return string The activator type.
     * @since 1.0.0
     */
    public function get_type(): string
    {
        return 'filter';
    }

    /**
     * Collect filtered items for global ordering.
     *
     * Processes filter configurations and returns an array of items that can be
     * sorted globally by order/priority before activation.
     *
     * @return array<int, array{type:string, order:int, data:array}> Array of filtered items.
     * @since 1.0.0
     */
    public function collect(): array
    {
        $items = [];

        foreach ($this->filters as $f) {
            if (empty($f['hook']) || empty($f['plugins']) || !is_array($f['plugins'])) {
                error_log('[FilterActivator] Invalid filter entry (missing hook/plugins).');
                continue;
            }

            $items[] = [
                'type'  => $this->get_type(),
                'order' => (int)($f['order'] ?? 0),
                'data'  => $f,
            ];
        }

        return $items;
    }
 
}
