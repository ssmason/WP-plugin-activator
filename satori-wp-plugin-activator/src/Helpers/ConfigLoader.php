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
 * Provides validated configuration data with proper defaults applied for
 * all activator types. Includes simple request-level caching.
 *
 * @package SatoriDigital\PluginActivator\Helpers
 * @since   1.0.0
 */
final class ConfigLoader
{
    private const LOG_PREFIX = '[PluginActivator]';

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
     * Uses request-based cache to avoid repeated file I/O.
     *
     * @return array Normalized configuration array.
     * @since 1.0.0
     */
    public function load(): array
    {
        $theme = (string) get_option('stylesheet', '');

        if (isset(self::$config_cache[$theme])) {
            return self::$config_cache[$theme];
        }

        $config     = $this->get_json_config($theme);
        $normalized = $this->normalize_config($config);

        return self::$config_cache[$theme] = $normalized;
    }

    /**
     * Read and decode a JSON configuration file.
     *
     * @param string $key Identifier (usually theme slug).
     * @return array Decoded config array or [] on failure.
     * @since 1.0.0
     */
    private function get_json_config(string $key): array
    {
        $file = trailingslashit(PLUGIN_ACTIVATION_CONFIG) . $key . '.json';

        if (!file_exists($file)) {
            error_log(sprintf('%s Config file not found: %s', self::LOG_PREFIX, $file));
            return [];
        }

        $json = @file_get_contents($file);
        if ($json === false) {
            error_log(sprintf('%s Failed to read config file: %s', self::LOG_PREFIX, $file));
            return [];
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log(sprintf(
                '%s JSON decode error in %s: %s',
                self::LOG_PREFIX,
                $file,
                json_last_error_msg()
            ));
            return [];
        }

        return \is_array($data) ? $data : [];
    }

    /**
     * Normalize configuration structure and ensure defaults.
     *
     * @param array $config Raw config data.
     * @return array Normalized configuration.
     * @since 1.0.0
     */
    private function normalize_config(array $config): array
    {
        if (empty($config['plugins']) || !\is_array($config['plugins'])) {
            return $config;
        }

        $config['plugins'] = \array_map(
            [$this, 'normalize_plugin_entry'],
            $config['plugins']
        );

        return $config;
    }

    /**
     * Normalize a single plugin entry.
     *
     * Converts strings to structured arrays and applies defaults.
     *
     * @param mixed $plugin Raw plugin entry.
     * @return array Normalized plugin entry.
     * @since 1.0.0
     */
    private function normalize_plugin_entry(mixed $plugin): array
    {
        if (\is_string($plugin)) {
            return [
                'slug'     => $plugin,
                'required' => false,
                'version'  => null,
                'order'    => 10,
            ];
        }

        if (\is_array($plugin)) {
            return \array_merge(
                [
                    'slug'     => '',
                    'required' => false,
                    'version'  => null,
                    'order'    => 10,
                ],
                $plugin
            );
        }

        error_log(sprintf(
            '%s Invalid plugin entry in config: %s',
            self::LOG_PREFIX,
            wp_json_encode($plugin)
        ));

        return [];
    }

    /**
     * Clear all cached configuration data.
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
     * @param string $theme Theme identifier.
     * @return void
     * @since 1.0.0
     */
    public static function clear_theme_cache(string $theme): void
    {
        unset(self::$config_cache[$theme]);
    }
}
