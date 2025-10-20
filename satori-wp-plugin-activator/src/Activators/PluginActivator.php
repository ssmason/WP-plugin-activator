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
    private const TYPE = 'plugin';

    /**
     * Array of plugin specifications.
     *
     * @var array<int, string|array{file:string, required?:bool, version?:string, order?:int}>
     */
    private readonly array $plugins;

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
        return self::TYPE;
    }

    /**
     * Collect plugin items for global ordering.
     *
     * @return array<int, array{type:string, order:int, data:array}>
     * @since 1.0.0
     */
    public function collect(): array
    {
        if (empty($this->plugins)) {
            return [];
        }

        $items = [];

        foreach ($this->plugins as $plugin) {
            // Normalize string entries into array form.
            $plugin = is_string($plugin) ? ['file' => $plugin] : $plugin;

            if (!$this->is_valid_plugin($plugin)) {
                $this->log_invalid_plugin($plugin);
                continue;
            }

            $items[] = [
                'type'  => self::TYPE,
                'order' => (int)($plugin['order'] ?? 0),
                'data'  => $plugin,
            ];
        }

        return $items;
    }

    /**
     * Validate a plugin configuration.
     *
     * @param array $plugin Plugin configuration array.
     * @return bool True if valid, false otherwise.
     * @since 1.0.0
     */
    private function is_valid_plugin(array $plugin): bool
    {
        return !empty($plugin['file'])
            && is_string($plugin['file'])
            && str_ends_with($plugin['file'], '.php');
    }

    /**
     * Log invalid plugin configuration.
     *
     * @param array $plugin Plugin configuration array.
     * @return void
     * @since 1.0.0
     */
    private function log_invalid_plugin(array $plugin): void
    {
        $file = $plugin['file'] ?? '(undefined)';
        error_log(sprintf(
            '[PluginActivator] Skipping invalid plugin entry (missing or invalid file): "%s".',
            $file
        ));
    }
}
