<?php
/**
 * Activation Utils
 *
 * Utility class for plugin activation, version checking, and WordPress integration.
 * Provides static methods for common plugin management tasks including activation,
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
 * Handles complex plugin specifications and provides safe activation
 * with version constraints and dependency management.
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
     * Ensure WordPress plugin API is loaded.
     *
     * Loads the WordPress plugin administration functions if they are not
     * already available. Required for activate_plugin(), get_plugins(), etc.
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
     * Useful for testing or when plugins change during execution.
     *
     * @return void
     * @since 1.0.0
     */
    public static function clear_plugin_cache(): void
    {
        self::$plugin_cache = null;
    }

    /**
     * Normalize any input into a flat list of plugin specifications.
     *
     * Takes various input formats (strings, specs, collected items) and
     * converts them into a standardized array of plugin specifications.
     * Handles deduplication and extracts nested plugin configurations.
     *
     * @param array $input Array of mixed plugin specifications.
     * @return array<int, array{file:string, required:bool, version:string|null, defer:bool}> Normalized plugin specs.
     * @since 1.0.0
     */
    private static function normalize_to_specs(array $input): array
    {
        $specs = []; // Use file as key for automatic deduplication.

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

            // Handle nested data structures.
            self::process_nested_plugins($specs, $item);
        }

        return \array_values($specs);
    }

    /**
     * Add a normalized plugin spec to the collection.
     *
     * @param array $specs Reference to specs collection.
     * @param array $maybe Raw plugin specification.
     * @return void
     * @since 1.0.0
     */
    private static function add_spec(array &$specs, array $maybe): void
    {
        if (empty($maybe['file'])) {
            return;
        }

        $file = $maybe['file'];

        // Use file as key for automatic deduplication.
        $specs[$file] = [
            'file'     => $file,
            'required' => $maybe['required'] ?? false,
            'version'  => $maybe['version'] ?? null,
            'defer'    => $maybe['defer'] ?? false,
        ];
    }

    /**
     * Process nested plugin structures from data or plugins arrays.
     *
     * @param array $specs Reference to specs collection.
     * @param array $item Item containing nested plugin data.
     * @return void
     * @since 1.0.0
     */
    private static function process_nested_plugins(array &$specs, array $item): void
    {
        // Check item['data']['plugins'] or item['data']['file'].
        if (!empty($item['data']) && \is_array($item['data'])) {
            $data = $item['data'];

            if (!empty($data['file'])) {
                self::add_spec($specs, $data);
            } elseif (!empty($data['plugins']) && \is_array($data['plugins'])) {
                self::process_plugin_array($specs, $data['plugins']);
            }
        }

        // Check item['plugins'].
        if (!empty($item['plugins']) && \is_array($item['plugins'])) {
            self::process_plugin_array($specs, $item['plugins']);
        }
    }

    /**
     * Process an array of plugin specifications.
     *
     * @param array $specs Reference to specs collection.
     * @param array $plugins Array of plugin specifications.
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
     * Extract plugin file paths from any mixed input.
     *
     * Processes various input formats and returns a clean array of
     * unique plugin file paths, filtering out duplicates and empty values.
     *
     * @param array $input Array of mixed plugin specifications.
     * @return array<int, string> Array of unique plugin file paths.
     * @since 1.0.0
     */
    private static function extract_files(array $input): array
    {
        $files = [];
        $specs = self::normalize_to_specs($input);

        foreach ($specs as $s) {
            $files[] = $s['file'];
        }

        return \array_values(\array_unique(\array_filter($files)));
    }

    /**
     * Check if a plugin file exists in the WordPress plugins directory.
     *
     * @param string $file Plugin file path relative to WP_PLUGIN_DIR.
     * @return bool True if the plugin file exists, false otherwise.
     * @since 1.0.0
     */
    public static function plugin_file_exists(string $file): bool
    {
        return \file_exists(\WP_PLUGIN_DIR . '/' . $file);
    }

    /**
     * Get the version of an installed plugin.
     *
     * Retrieves the version number from the plugin headers. Returns null
     * if the plugin is not found or has no version information.
     *
     * @param string $file Plugin file path relative to WP_PLUGIN_DIR.
     * @return string|null The plugin version or null if not found.
     * @since 1.0.0
     */
    public static function get_plugin_version(string $file): ?string
    {
        $all = self::get_all_plugins(); // ← Uses cache instead of direct get_plugins()
        if (!isset($all[$file]['Version'])) {
            return null;
        }

        $ver = (string) $all[$file]['Version'];
        return $ver !== '' ? $ver : null;
    }

    /**
     * Check if a plugin version satisfies a version constraint.
     *
     * Compares the current plugin version against a version expression
     * such as '>=2.1.0', '<1.0', '=3.0', etc. Uses semantic version
     * comparison with fallback to simple string comparison.
     *
     * @param string      $current      The current plugin version.
     * @param string|null $required_expr The version constraint expression.
     * @return bool True if the version satisfies the constraint, false otherwise.
     * @since 1.0.0
     */
    public static function satisfies_version(string $current, ?string $required_expr): bool
    {
        if ($required_expr === null || $required_expr === '') {
            return true;
        }

        if (!\preg_match('/^(>=|<=|>|<|=|==|!=)?\s*([0-9][0-9\.]*)$/', $required_expr, $m)) {
            return \version_compare($current, $required_expr, '>=');
        }

        $op  = $m[1] ?: '>=';
        $ver = $m[2];
        return \version_compare($current, $ver, $op);
    }

    /**
     * Check plugin versions against requirements without activation.
     *
     * Validates all plugins in the input against their version requirements
     * and logs any mismatches. Does not perform activation, only validation.
     *
     * @param array $input Array of plugin specifications with version requirements.
     * @return void
     * @since 1.0.0
     */
    public static function check_versions(array $input): void
    {
        $specs = self::normalize_to_specs($input);
        // Get all plugin data once for the entire batch.
        $all_plugins = self::get_all_plugins();
        foreach ($specs as $s) {
            $file = $s['file'];
            $req  = $s['version'] ?? null;
            if (!$req) {
                continue;
            }

            // Use cached plugin data instead of calling get_plugin_version().
            $current = null;
            if (isset($all_plugins[$file]['Version'])) {
                $ver = (string) $all_plugins[$file]['Version'];
                $current = $ver !== '' ? $ver : null;
            }

            if ($current !== null && !self::satisfies_version($current, $req)) {
                \error_log(\sprintf(
                    '[PluginActivator] Version mismatch: %s requires %s, found %s.',
                    $file,
                    $req,
                    $current
                ));
            }
        }
    }

    /**
     * Activate plugins with strict version enforcement.
     *
     * Performs plugin activation with comprehensive validation including
     * file existence, version constraints, and dependency ordering.
     * Supports deferred activation and automatic deactivation of
     * version-incompatible plugins.
     *
     * @param array $input Array of plugin specifications to activate.
     * @return void
     * @since 1.0.0
     */
    public static function activate_plugins(array $input): void
    {
        self::ensure_wp_plugin_api();
        
        $specs = self::normalize_to_specs($input);
        $validation_results = self::validate_plugin_batch($specs);
        
        self::process_immediate_activations($validation_results['immediate']);
        self::process_deferred_activations($validation_results['deferred']);
    }

    /**
     * Validate a batch of plugin specifications.
     *
     * Checks file existence and version constraints for all plugins,
     * separating them into immediate and deferred activation queues.
     *
     * @param array $specs Normalized plugin specifications.
     * @return array Array with 'immediate' and 'deferred' plugin file lists.
     * @since 1.0.0
     */
    private static function validate_plugin_batch(array $specs): array
    {
        $all_plugins = self::get_all_plugins();
        $immediate = [];
        $deferred = [];

        foreach ($specs as $spec) {
            $validation = self::validate_single_plugin_spec($spec, $all_plugins);
            
            if (!$validation['valid']) {
                continue;
            }

            if ($spec['defer']) {
                $deferred[] = $spec['file'];
            } else {
                $immediate[] = $spec['file'];
            }
        }

        return [
            'immediate' => $immediate,
            'deferred'  => $deferred,
        ];
    }

    /**
     * Validate a single plugin specification.
     *
     * Checks file existence and version constraints for one plugin.
     * Handles missing files and version mismatches appropriately.
     *
     * @param array $spec Plugin specification.
     * @param array $all_plugins Cached plugin data from WordPress.
     * @return array Validation result with 'valid' boolean.
     * @since 1.0.0
     */
    private static function validate_single_plugin_spec(array $spec, array $all_plugins): array
    {
        $file = $spec['file'];
        $required = (bool)($spec['required'] ?? false);
        $version_expr = $spec['version'] ?? null;

        // Check file existence
        if (!self::plugin_file_exists($file)) {
            self::handle_missing_plugin_file($file, $required);
            return ['valid' => false, 'reason' => 'missing_file'];
        }

        // Check version constraints
        if ($version_expr) {
            $version_valid = self::validate_plugin_version_constraint($file, $version_expr, $all_plugins);
            if (!$version_valid) {
                self::handle_version_constraint_failure($file);
                return ['valid' => false, 'reason' => 'version_mismatch'];
            }
        }

        return ['valid' => true];
    }

    /**
     * Validate plugin version against constraint.
     *
     * @param string $file Plugin file path.
     * @param string $version_expr Version constraint expression.
     * @param array $all_plugins Cached plugin data.
     * @return bool True if version constraint is satisfied.
     * @since 1.0.0
     */
    private static function validate_plugin_version_constraint(string $file, string $version_expr, array $all_plugins): bool
    {
        $current = null;
        if (isset($all_plugins[$file]['Version'])) {
            $ver = (string) $all_plugins[$file]['Version'];
            $current = $ver !== '' ? $ver : null;
        }

        return $current !== null && self::satisfies_version($current, $version_expr);
    }

    /**
     * Handle missing plugin file.
     *
     * @param string $file Plugin file path.
     * @param bool $required Whether the plugin is required.
     * @return void
     * @since 1.0.0
     */
    private static function handle_missing_plugin_file(string $file, bool $required): void
    {
        \error_log(\sprintf('[PluginActivator] Plugin file not found: %s', $file));
        
        if ($required) {
            self::log_missing_plugin($file);
        }
    }

    /**
     * Handle version constraint failure.
     *
     * Deactivates plugin if currently active due to version mismatch.
     *
     * @param string $file Plugin file path.
     * @return void
     * @since 1.0.0
     */
    private static function handle_version_constraint_failure(string $file): void
    {
        if (is_plugin_active($file)) {
            deactivate_plugins([$file], false, is_multisite());
            \error_log(\sprintf('[PluginActivator] Deactivated due to version mismatch: %s', $file));
        }
    }

    /**
     * Process immediate plugin activations.
     *
     * @param array $plugin_files Array of plugin file paths to activate immediately.
     * @return void
     * @since 1.0.0
     */
    private static function process_immediate_activations(array $plugin_files): void
    {
        foreach ($plugin_files as $file) {
            if (!is_plugin_active($file)) {
                activate_plugin($file, '', false, true);
            }
        }
    }

    /**
     * Process deferred plugin activations.
     *
     * @param array $plugin_files Array of plugin file paths to activate after immediate ones.
     * @return void
     * @since 1.0.0
     */
    private static function process_deferred_activations(array $plugin_files): void
    {
        foreach ($plugin_files as $file) {
            if (!is_plugin_active($file)) {
                activate_plugin($file, '', false, true);
            }
        }
    }

    /**
     * Deactivate plugins not present in the allowed list.
     *
     * Compares currently active plugins against the provided input and
     * deactivates any plugins that are not included in the allowed list.
     * Useful for maintaining a clean plugin environment.
     *
     * @param array $input Array of plugin specifications that should remain active.
     * @return void
     * @since 1.0.0
     */
    public static function deactivate_unlisted_plugins(array $input): void
    {
        self::ensure_wp_plugin_api();

        $allowed_files = self::extract_files($input);
        $active        = (array) get_option('active_plugins', []);

        $to_deactivate = \array_values(\array_diff($active, $allowed_files));

        if (empty($to_deactivate)) {
            return;
        }

        deactivate_plugins($to_deactivate, false, is_multisite());

        \error_log(\sprintf(
            '[PluginActivator] Deactivated unlisted plugins: %s',
            \implode(', ', $to_deactivate)
        ));
    }

    /**
     * Check if a plugin version satisfies a constraint.
     *
     * Simple version checking method that compares an installed plugin
     * version against a version constraint string.
     *
     * @param string $file       Plugin file path.
     * @param string $constraint Version constraint (e.g., '>=1.0.0').
     * @return bool True if constraint is satisfied, false otherwise.
     * @since 1.0.0
     */
    public static function check_version(string $file, string $constraint): bool
    {
        $plugins = self::get_all_plugins(); // ← Uses cache instead of direct get_plugins()
        if (!isset($plugins[$file]['Version'])) {
            return false;
        }

        $installed = $plugins[$file]['Version'];

        if (preg_match('/^(>=|<=|>|<|==|!=)\s*(.+)$/', $constraint, $matches)) {
            return version_compare($installed, $matches[2], $matches[1]);
        }

        return version_compare($installed, $constraint, '>=');
    }

    /**
     * Check if a plugin file is missing from the filesystem.
     *
     * @param string $plugin_file Plugin file path relative to WP_PLUGIN_DIR.
     * @return bool True if the plugin file is missing, false if it exists.
     * @since 1.0.0
     */
    public static function is_plugin_file_missing(string $plugin_file): bool
    {
        return ! self::plugin_file_exists($plugin_file);
    }

    /**
     * Log version mismatch information to the error log.
     *
     * Records detailed information about version conflicts for debugging
     * and monitoring purposes.
     *
     * @param string      $plugin_file   Plugin file path.
     * @param string      $required_expr Required version expression.
     * @param string|null $current       Current version (auto-detected if null).
     * @return void
     * @since 1.0.0
     */
    public static function log_version_mismatch(
        string $plugin_file,
        string $required_expr,
        ?string $current = null
    ): void {
        if ($current === null) {
            $current = self::get_plugin_version($plugin_file) ?? 'unknown';
        }

        error_log(
            sprintf(
                '[PluginActivator] Version mismatch for %s. Required %s, found %s.',
                $plugin_file,
                $required_expr,
                $current
            )
        );
    }

    /**
     * Log missing required plugin information to the error log.
     *
     * Records when a required plugin is missing from the system,
     * which may indicate a configuration or installation issue.
     *
     * @param string $plugin_file Plugin file path that is missing.
     * @return void
     * @since 1.0.0
     */
    public static function log_missing_plugin(string $plugin_file): void
    {
        error_log(sprintf('[PluginActivator] REQUIRED plugin missing: %s', $plugin_file));
    }
}
