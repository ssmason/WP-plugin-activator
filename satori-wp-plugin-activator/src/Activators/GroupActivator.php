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
    private const TYPE = 'group';

    /**
     * Array of group configurations keyed by environment name.
     *
     * @var array<string, array{url:string, plugins:array}>
     */
    private readonly array $groups;

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
        return self::TYPE;
    }

    /**
     * Collect group items for the current environment.
     *
     * Identifies the matching environment group and collects all valid
     * plugin items from that group for activation.
     *
     * @return array<int, array{type:string, order:int, data:array}> Array of group items for matching URL.
     * @since 1.0.0
     */
    public function collect(): array
    {
        if (empty($this->groups)) {
            return [];
        }

        $matching_group = $this->find_matching_environment_group();
        if ($matching_group === null) {
            return [];
        }

        return $this->collect_plugins_from_group($matching_group);
    }

    /**
     * Find the group that matches the current environment URL.
     *
     * @return array{name:string, config:array}|null Matching group configuration or null if no match.
     * @since 1.0.0
     */
    private function find_matching_environment_group(): ?array
    {
        $current_url = $this->get_normalized_site_url();

        foreach ($this->groups as $group_name => $group_config) {
            if ($this->does_group_match_current_url($group_config, $current_url)) {
                return [
                    'name'   => $group_name,
                    'config' => $group_config,
                ];
            }
        }

        return null;
    }

    /**
     * Get the normalized current site URL.
     *
     * @return string Normalized site URL without trailing slash.
     * @since 1.0.0
     */
    private function get_normalized_site_url(): string
    {
        return rtrim(site_url(), '/');
    }

    /**
     * Check if group configuration matches current URL.
     *
     * @param array  $group_config Group configuration.
     * @param string $current_url  Current site URL.
     * @return bool True if group matches current environment.
     * @since 1.0.0
     */
    private function does_group_match_current_url(array $group_config, string $current_url): bool
    {
        $group_url = !empty($group_config['url']) ? rtrim($group_config['url'], '/') : null;
        return $group_url !== null && hash_equals($group_url, $current_url);
    }

    /**
     * Collect all valid plugin items from the matching group.
     *
     * @param array{name:string, config:array} $matching_group Matching group data.
     * @return array<int, array{type:string, order:int, data:array}> Array of formatted plugin items.
     * @since 1.0.0
     */
    private function collect_plugins_from_group(array $matching_group): array
    {
        $group_name   = $matching_group['name'];
        $group_config = $matching_group['config'];
        $plugins      = $group_config['plugins'] ?? [];

        $items = [];

        foreach ($plugins as $plugin) {
            $formatted_item = $this->format_plugin_item($plugin, $group_name);
            if ($formatted_item !== null) {
                $items[] = $formatted_item;
            }
        }

        return $items;
    }

    /**
     * Format a single plugin configuration into a collection item.
     *
     * @param array  $plugin     Plugin configuration.
     * @param string $group_name Group name for error logging.
     * @return array{type:string, order:int, data:array}|null Formatted item or null if invalid.
     * @since 1.0.0
     */
    private function format_plugin_item(array $plugin, string $group_name): ?array
    {
        if (empty($plugin['file'])) {
            $this->log_invalid_plugin($group_name);
            return null;
        }

        if (!str_ends_with($plugin['file'], '.php')) {
            error_log(sprintf('[GroupActivator] %s: invalid plugin file "%s".', $group_name, $plugin['file']));
            return null;
        }

        return [
            'type'  => self::TYPE,
            'order' => (int)($plugin['order'] ?? 0),
            'data'  => $plugin,
        ];
    }

    /**
     * Log invalid plugin configuration.
     *
     * @param string $group_name Group name for context.
     * @return void
     * @since 1.0.0
     */
    private function log_invalid_plugin(string $group_name): void
    {
        error_log(sprintf('[GroupActivator] %s: skipping plugin with missing "file".', $group_name));
    }
}
