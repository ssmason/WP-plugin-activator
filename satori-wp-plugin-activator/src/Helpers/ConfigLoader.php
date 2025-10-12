<?php
/**
 * Config Loader
 *
 * Loads, normalizes, and validates plugin activation configuration from JSON files.
 * Provides clean, validated configuration with defaults applied for all activators.
 * Includes request-based caching to minimize file I/O operations.
 *
 * @category Plugin_Activator
 * @package  SatoriDigital\PluginActivator\Helpers
 * @author   Satori Digital
 * @license  GPL-2.0+
 * @link     https://satoridigital.com
 */

declare(strict_types=1);

namespace SatoriDigital\PluginActivator\Helpers;

/**
 * Class ConfigLoader
 *
 * Handles loading and normalizing plugin activation configuration from JSON files.
 * Provides clean, validated configuration data with proper defaults applied for
 * all activator types in the system. Includes simple caching to prevent multiple
 * file reads within the same request.
 *
 * @package SatoriDigital\PluginActivator\Helpers
 * @since   1.0.0
 */
class ConfigLoader
{
    /**
     * Cached configuration data keyed by theme name.
     *
     * @var array<string, array>
     * @since 1.0.0
     */
    private static array $config_cache = [];

    /**
     * Load and normalize the plugin activation configuration.
     *
     * Loads configuration from theme-specific JSON files with simple
     * caching to avoid repeated file I/O operations within the same request.
     *
     * @return array Normalized configuration array.
     * @since 1.0.0
     */
    public function load(): array
    {
        $theme = get_option('stylesheet', '');

        // Check if we have cached config for this theme.
        if (isset(self::$config_cache[$theme])) {
            return self::$config_cache[$theme];
        }

        $config = $this->get_json_config($theme);
        $normalized = $this->normalize_config($config);

        // Cache the normalized configuration.
        self::$config_cache[$theme] = $normalized;

        return $normalized;
    }

    /**
     * Read the JSON configuration file for a given key (e.g. theme or "network").
     *
     * Attempts to load and decode JSON configuration file, with proper
     * error handling for missing files and invalid JSON.
     *
     * @param string $key Identifier (usually theme slug).
     * @return array Decoded config array or empty array on failure.
     * @since 1.0.0
     */
    private function get_json_config(string $key): array
    {
        $file = trailingslashit(PLUGIN_ACTIVATION_CONFIG) . $key . '.json';

        if (!file_exists($file)) {
            error_log(sprintf('[PluginActivator] Config file not found: %s', $file));
            return [];
        }

        $json = file_get_contents($file);
        if ($json === false) {
            error_log(sprintf('[PluginActivator] Failed to read config file: %s', $file));
            return [];
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log(
                sprintf(
                    '[PluginActivator] JSON decode error in %s: %s',
                    $file,
                    json_last_error_msg()
                )
            );
            return [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Normalize configuration data structure.
     *
     * Ensures plugins are objects with default fields set and applies
     * consistent structure across all configuration entries.
     *
     * @param array $config Raw configuration.
     * @return array Normalized configuration.
     * @since 1.0.0
     */
    private function normalize_config(array $config): array
    {
        if (isset($config['plugins']) && is_array($config['plugins'])) {
            $config['plugins'] = array_map([$this, 'normalize_plugin_entry'], $config['plugins']);
        }

        return $config;
    }

    /**
     * Normalize a single plugin entry.
     *
     * Converts string entries to arrays with default fields and ensures
     * consistent structure for all plugin specifications.
     *
     * @param mixed $plugin Raw plugin entry.
     * @return array Normalized plugin entry.
     * @since 1.0.0
     */
    private function normalize_plugin_entry($plugin): array
    {
        if (is_string($plugin)) {
            return [
                'slug'     => $plugin,
                'required' => false,
                'version'  => null,
                'order'    => 10,
            ];
        }

        if (is_array($plugin)) {
            return array_merge(
                [
                    'slug'     => '',
                    'required' => false,
                    'version'  => null,
                    'order'    => 10,
                ],
                $plugin
            );
        }

        // Invalid entry type.
        error_log(
            sprintf(
                '[PluginActivator] Invalid plugin entry in config: %s',
                wp_json_encode($plugin)
            )
        );
        return [];
    }

    /**
     * Clear all cached configuration data.
     *
     * Useful for testing or when forcing a reload of configuration files.
     *
     * @return void
     * @since 1.0.0
     */
    public static function clear_cache(): void
    {
        self::$config_cache = [];
    }

    /**
     * Clear cached configuration for a specific theme.
     *
     * @param string $theme Theme identifier to clear from cache.
     * @return void
     * @since 1.0.0
     */
    public static function clear_theme_cache(string $theme): void
    {
        unset(self::$config_cache[$theme]);
    }
}
