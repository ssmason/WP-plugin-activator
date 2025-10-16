<?php
/**
 * Activation Utils
 *
 * Utility class for plugin activation, version checking, and WordPress integration.
 * Provides static methods for plugin management tasks including activation,
 * deactivation, version validation, and file existence checks.
 *
 * @category Plugin_Activator
 * @package  SatoriDigital\PluginActivator\Helpers
 * @author   Satori Digital
 * @license  GPL-2.0+
 * @link     https://satoridigital.com
 */

declare(strict_types=1);

namespace SatoriDigital\PluginActivator\Helpers;

use function activate_plugin;
use function deactivate_plugins;
use function get_option;
use function get_plugins;
use function is_multisite;
use function is_plugin_active;

/**
 * Class ActivationUtils
 *
 * Static utility class providing plugin activation, version checking,
 * and file validation methods for the plugin activator system.
 *
 * @package SatoriDigital\PluginActivator\Helpers
 * @since   1.0.0
 */
final class ActivationUtils
{
    /**
     * Cached plugin data to avoid multiple get_plugins() calls.
     *
     * @var array|null
     * @since 1.0.0
     */
    private static ?array $plugin_cache = null;

    /**
     * Log prefix for consistent error_log output.
     *
     * @var string
     * @since 1.0.0
     */
    private const LOG_PREFIX = '[PluginActivator]';

    /**
     * Ensure WordPress plugin API is loaded.
     *
     * Loads wp-admin includes if necessary for plugin functions.
     *
     * @return void
     * @since 1.0.0
     */
    private static function ensure_wp_plugin_api(): void
    {
        if (!\function_exists('activate_plugin')) {
            require_once \ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    /**
     * Get cached plugin data, loading once if needed.
     *
     * @return array Plugin data from WordPress.
     * @since 1.0.0
     */
    private static function get_all_plugins(): array
    {
        if (self::$plugin_cache === null) {
            self::ensure_wp_plugin_api();
            self::$plugin_cache = get_plugins();
        }

        return self::$plugin_cache;
    }

    /**
     * Clear the plugin cache.
     *
     * @return void
     * @since 1.0.0
     */
    public static function clear_plugin_cache(): void
    {
        self::$plugin_cache = null;
    }

    /**
     * Normalize any input into a flat list of plugin specs.
     *
     * @param array $input Array of mixed plugin specifications.
     * @return array<int, array{file:string, required:bool, version:string|null, defer:bool}>
     * @since 1.0.0
     */
    private static function normalize_to_specs(array $input): array
    {
        $specs = [];

        foreach ($input as $item) {
            if (\is_string($item)) {
                self::add_spec($specs, ['file' => $item]);
                continue;
            }

            if (!\is_array($item)) {
                continue;
            }

            if (!empty($item['file'])) {
                self::add_spec($specs, $item);
                continue;
            }

            self::process_nested_plugins($specs, $item);
        }

        return \array_values($specs);
    }

    /**
     * Add a normalized plugin spec.
     *
     * @param array $specs Reference to the collection array.
     * @param array $maybe Candidate plugin spec.
     * @return void
     * @since 1.0.0
     */
    private static function add_spec(array &$specs, array $maybe): void
    {
        $file = $maybe['file'] ?? '';
        if ($file === '') {
            return;
        }

        $specs[$file] = [
            'file'     => $file,
            'required' => (bool)($maybe['required'] ?? false),
            'version'  => $maybe['version'] ?? null,
            'defer'    => (bool)($maybe['defer'] ?? false),
        ];
    }

    /**
     * Process nested plugin structures.
     *
     * @param array $specs Reference to the collection array.
     * @param array $item  Item possibly containing nested plugins.
     * @return void
     * @since 1.0.0
     */
    private static function process_nested_plugins(array &$specs, array $item): void
    {
        $data = $item['data'] ?? [];
        if (\is_array($data)) {
            if (!empty($data['file'])) {
                self::add_spec($specs, $data);
            } elseif (!empty($data['plugins']) && \is_array($data['plugins'])) {
                self::process_plugin_array($specs, $data['plugins']);
            }
        }

        if (!empty($item['plugins']) && \is_array($item['plugins'])) {
            self::process_plugin_array($specs, $item['plugins']);
        }
    }

    /**
     * Process a plugin array.
     *
     * @param array $specs   Reference to the collection array.
     * @param array $plugins Plugin list.
     * @return void
     * @since 1.0.0
     */
    private static function process_plugin_array(array &$specs, array $plugins): void
    {
        foreach ($plugins as $plugin) {
            if (\is_string($plugin)) {
                self::add_spec($specs, ['file' => $plugin]);
            } elseif (\is_array($plugin)) {
                self::add_spec($specs, $plugin);
            }
        }
    }

    /**
     * Extract plugin file paths.
     *
     * @param array $input Mixed specs.
     * @return array<int, string> List of unique plugin file paths.
     * @since 1.0.0
     */
    private static function extract_files(array $input): array
    {
        return \array_values(
            \array_unique(
                \array_filter(
                    \array_map(static fn($s) => $s['file'] ?? '', self::normalize_to_specs($input))
                )
            )
        );
    }

    /**
     * Check plugin file existence.
     *
     * @param string $file Plugin file path relative to WP_PLUGIN_DIR.
     * @return bool True if file exists, false otherwise.
     * @since 1.0.0
     */
    public static function plugin_file_exists(string $file): bool
    {
        return \file_exists(\WP_PLUGIN_DIR . '/' . $file);
    }

    /**
     * Get plugin version.
     *
     * @param string $file Plugin file path relative to WP_PLUGIN_DIR.
     * @return string|null Plugin version string or null if unavailable.
     * @since 1.0.0
     */
    public static function get_plugin_version(string $file): ?string
    {
        $all = self::get_all_plugins();
        $ver = $all[$file]['Version'] ?? '';
        return $ver !== '' ? (string)$ver : null;
    }

    /**
     * Compare versions with an expression.
     *
     * @param string      $current       Current version.
     * @param string|null $required_expr Expression like ">=1.0.0".
     * @return bool True if condition satisfied.
     * @since 1.0.0
     */
    public static function satisfies_version(string $current, ?string $required_expr): bool
    {
        if ($required_expr === null || $required_expr === '') {
            return true;
        }

        if (!\preg_match('/^(>=|<=|>|<|=|==|!=)?\s*([\d\.]+)$/', $required_expr, $m)) {
            return \version_compare($current, $required_expr, '>=');
        }

        return \version_compare($current, $m[2], $m[1] ?: '>=');
    }

    /**
     * Check plugin versions and log mismatches.
     *
     * @param array $input Plugin specs.
     * @return void
     * @since 1.0.0
     */
    public static function check_versions(array $input): void
    {
        $specs = self::normalize_to_specs($input);
        $all_plugins = self::get_all_plugins();

        foreach ($specs as $s) {
            $file = $s['file'];
            $req  = $s['version'] ?? null;
            if (!$req || empty($all_plugins[$file]['Version'])) {
                continue;
            }

            $current = (string)$all_plugins[$file]['Version'];
            if (!self::satisfies_version($current, $req)) {
                error_log(sprintf(
                    '%s Version mismatch: %s requires %s, found %s.',
                    self::LOG_PREFIX,
                    $file,
                    $req,
                    $current
                ));
            }
        }
    }

    /**
     * Activate plugins (immediate + deferred).
     *
     * @param array $input Plugin specs.
     * @return void
     * @since 1.0.0
     */
    public static function activate_plugins(array $input): void
    {
        self::ensure_wp_plugin_api();

        $specs = self::normalize_to_specs($input);
        $validated = self::validate_plugin_batch($specs);

        self::process_activations($validated['immediate']);
        self::process_activations($validated['deferred']);
    }

    /**
     * Validate batch of plugin specs.
     *
     * @param array $specs Normalized specs.
     * @return array{immediate:array,deferred:array} Grouped lists.
     * @since 1.0.0
     */
    private static function validate_plugin_batch(array $specs): array
    {
        $all_plugins = self::get_all_plugins();
        $immediate = [];
        $deferred = [];

        foreach ($specs as $spec) {
            if (!self::plugin_file_exists($spec['file'])) {
                self::handle_missing_plugin_file($spec['file'], (bool)($spec['required'] ?? false));
                continue;
            }

            $version_expr = $spec['version'] ?? null;
            if (
                $version_expr &&
                !self::validate_plugin_version_constraint(
                    $spec['file'],
                    $version_expr,
                    $all_plugins
                )
            ) {
                self::handle_version_constraint_failure($spec['file']);
                continue;
            }

            if (!empty($spec['defer'])) {
                $deferred[] = $spec['file'];
            } else {
                $immediate[] = $spec['file'];
            }
        }

        return compact('immediate', 'deferred');
    }

    /**
     * Validate plugin version constraint.
     *
     * @param string $file Plugin file path relative to WP_PLUGIN_DIR.
     * @param string $expr Version constraint expression (e.g. '>=1.0.0').
     * @param array  $all  Cached plugin data from WordPress.
     * @return bool True if constraint satisfied.
     * @since 1.0.0
     */
    private static function validate_plugin_version_constraint(
        string $file,
        string $expr,
        array $all
    ): bool {
        $ver = $all[$file]['Version'] ?? '';
        return $ver !== '' && self::satisfies_version($ver, $expr);
    }

    /**
     * Handle missing plugin file.
     *
     * @param string $file     Plugin file path relative to WP_PLUGIN_DIR.
     * @param bool   $required Whether the plugin is required.
     * @return void
     * @since 1.0.0
     */
    private static function handle_missing_plugin_file(string $file, bool $required): void
    {
        error_log(sprintf('%s Plugin file not found: %s', self::LOG_PREFIX, $file));
        if ($required) {
            self::log_missing_plugin($file);
        }
    }

    /**
     * Handle version constraint failure.
     *
     * @param string $file Plugin file path relative to WP_PLUGIN_DIR.
     * @return void
     * @since 1.0.0
     */
    private static function handle_version_constraint_failure(string $file): void
    {
        if (is_plugin_active($file)) {
            deactivate_plugins([$file], false, is_multisite());
            error_log(sprintf('%s Deactivated due to version mismatch: %s', self::LOG_PREFIX, $file));
        }
    }

    /**
     * Activate a list of plugins.
     *
     * @param array<int, string> $files Plugin files to activate.
     * @return void
     * @since 1.0.0
     */
    private static function process_activations(array $files): void
    {
        foreach ($files as $file) {
            if (!is_plugin_active($file)) {
                activate_plugin($file, '', false, true);
            }
        }
    }

    /**
     * Deactivate unlisted plugins.
     *
     * @param array $input Plugin specs that should remain active.
     * @return void
     * @since 1.0.0
     */
    public static function deactivate_unlisted_plugins(array $input): void
    {
        self::ensure_wp_plugin_api();

        $allowed = self::extract_files($input);
        $active  = (array)get_option('active_plugins', []);

        $to_deactivate = \array_diff($active, $allowed);
        if ($to_deactivate === []) {
            return;
        }

        deactivate_plugins($to_deactivate, false, is_multisite());

        error_log(sprintf(
            '%s Deactivated unlisted plugins: %s',
            self::LOG_PREFIX,
            \implode(', ', $to_deactivate)
        ));
    }

    /**
     * Check if a plugin version satisfies a given constraint.
     *
     * @param string $file       Plugin file path relative to WP_PLUGIN_DIR.
     * @param string $constraint Version constraint (e.g. '>=1.0.0').
     * @return bool True if satisfied, false otherwise.
     * @since 1.0.0
     */
    public static function check_version(string $file, string $constraint): bool
    {
        $plugins = self::get_all_plugins();
        $installed = $plugins[$file]['Version'] ?? '';
        if ($installed === '') {
            return false;
        }

        if (\preg_match('/^(>=|<=|>|<|==|!=)\s*(.+)$/', $constraint, $m)) {
            return \version_compare($installed, $m[2], $m[1]);
        }

        return \version_compare($installed, $constraint, '>=');
    }

    /**
     * Check if a plugin file is missing.
     *
     * @param string $plugin_file Plugin file path relative to WP_PLUGIN_DIR.
     * @return bool True if missing, false otherwise.
     * @since 1.0.0
     */
    public static function is_plugin_file_missing(string $plugin_file): bool
    {
        return !self::plugin_file_exists($plugin_file);
    }

    /**
     * Log a version mismatch warning.
     *
     * @param string      $file     Plugin file path relative to WP_PLUGIN_DIR.
     * @param string      $required Required version expression.
     * @param string|null $current  Current plugin version (auto-detected if null).
     * @return void
     * @since 1.0.0
     */
    public static function log_version_mismatch(
        string $file,
        string $required,
        ?string $current = null
    ): void {
        $current ??= self::get_plugin_version($file) ?? 'unknown';
        error_log(sprintf(
            '%s Version mismatch for %s. Required %s, found %s.',
            self::LOG_PREFIX,
            $file,
            $required,
            $current
        ));
    }

    /**
     * Log missing required plugin warning.
     *
     * @param string $file Plugin file path relative to WP_PLUGIN_DIR.
     * @return void
     * @since 1.0.0
     */
    public static function log_missing_plugin(string $file): void
    {
        error_log(sprintf('%s REQUIRED plugin missing: %s', self::LOG_PREFIX, $file));
    }
}
