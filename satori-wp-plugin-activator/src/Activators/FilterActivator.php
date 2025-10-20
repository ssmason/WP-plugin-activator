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
    private const TYPE = 'filter';

    /**
     * Array of filter configurations.
     *
     * @var array<int, array{hook:string, priority?:int, plugins:array, order?:int}>
     */
    private readonly array $filters;

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
        return self::TYPE;
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
        if (empty($this->filters)) {
            return [];
        }

        $items = [];

        foreach ($this->filters as $filter) {
            if (!is_array($filter)) {
                error_log(sprintf(
                    '[FilterActivator] Invalid filter entry type: expected array, got %s',
                    gettype($filter)
                ));
                continue;
            }

            if (!$this->is_valid_filter($filter)) {
                $this->log_invalid_filter($filter);
                continue;
            }

            $items[] = [
                'type'  => self::TYPE,
                'order' => (int)($filter['order'] ?? 0),
                'data'  => $filter,
            ];
        }

        return $items;
    }

    /**
     * Validate a single filter configuration entry.
     *
     * Ensures required keys exist and that the plugins field is properly structured.
     *
     * @param array $filter Filter configuration.
     * @return bool True if valid, false otherwise.
     * @since 1.0.0
     */
    private function is_valid_filter(array $filter): bool
    {
        return !empty($filter['hook'])
            && !empty($filter['plugins'])
            && is_array($filter['plugins']);
    }

    /**
     * Log invalid filter configuration.
     *
     * @param array $filter Filter configuration for context.
     * @return void
     * @since 1.0.0
     */
    private function log_invalid_filter(array $filter): void
    {
        $hook = $filter['hook'] ?? '(undefined)';
        error_log(sprintf(
            '[FilterActivator] Invalid filter entry (missing hook/plugins). Hook: "%s"',
            $hook
        ));
    }
}
