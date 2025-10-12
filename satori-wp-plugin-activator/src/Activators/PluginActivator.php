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
     * Orchestrates the plugin activation process by validating
     * requirements and activating if all conditions are met.
     *
     * @param array $item Plugin item containing activation data.
     * @return void
     * @since 1.0.0
     */
    public function handle(array $item): void
    {
        $plugin_config = $this->extract_plugin_config($item);

        if (!$this->validate_plugin_requirements($plugin_config)) {
            return; // Validation failed, don't activate.
        }

        $this->activate_plugin($plugin_config);
    }

    /**
     * Extract plugin configuration from item data.
     *
     * @param array $item Plugin item data.
     * @return array Plugin configuration with normalized fields.
     * @since 1.0.0
     */
    private function extract_plugin_config(array $item): array
    {
        $data = $item['data'];

        return [
            'file'     => $data['file'],
            'required' => $data['required'] ?? false,
            'version'  => $data['version'] ?? null,
            'data'     => $data,
        ];
    }

    /**
     * Validate all plugin requirements.
     *
     * @param array $config Plugin configuration.
     * @return bool True if all requirements are satisfied.
     * @since 1.0.0
     */
    private function validate_plugin_requirements(array $config): bool
    {
        if (!$this->validate_file_exists($config)) {
            return false;
        }

        if (!$this->validate_version_constraint($config)) {
            return false;
        }

        return true;
    }

    /**
     * Validate plugin file existence.
     *
     * @param array $config Plugin configuration.
     * @return bool True if file exists or validation passes.
     * @since 1.0.0
     */
    private function validate_file_exists(array $config): bool
    {
        $file = $config['file'];
        $required = $config['required'];

        if (ActivationUtils::plugin_file_exists($file)) {
            return true;
        }

        $this->handle_missing_file($file, $required);
        return false;
    }

    /**
     * Handle missing plugin file.
     *
     * @param string $file Plugin file path.
     * @param bool   $required Whether plugin is required.
     * @return void
     * @since 1.0.0
     */
    private function handle_missing_file(string $file, bool $required): void
    {
        error_log(sprintf('[PluginActivator] Plugin file not found: %s', $file));

        if ($required) {
            error_log(sprintf('[PluginActivator] REQUIRED plugin missing: %s', $file));
        }
    }

    /**
     * Validate plugin version constraint.
     *
     * @param array $config Plugin configuration.
     * @return bool True if version constraint is satisfied or not applicable.
     * @since 1.0.0
     */
    private function validate_version_constraint(array $config): bool
    {
        $file = $config['file'];
        $version_constraint = $config['version'];
        $required = $config['required'];

        // No version constraint specified.
        if (empty($version_constraint)) {
            return true;
        }

        $current_version = ActivationUtils::get_plugin_version($file);

        if ($current_version === null) {
            $this->handle_missing_version($file);
            return true; // Continue activation even without version info.
        }

        if (ActivationUtils::satisfies_version($current_version, $version_constraint)) {
            return true;
        }

        $this->handle_version_mismatch($file, $version_constraint, $current_version, $required);
        return true; // Continue activation despite version mismatch (current behavior).
    }

    /**
     * Handle missing version information.
     *
     * @param string $file Plugin file path.
     * @return void
     * @since 1.0.0
     */
    private function handle_missing_version(string $file): void
    {
        error_log(sprintf('[PluginActivator] No version information found for: %s', $file));
    }

    /**
     * Handle version constraint mismatch.
     *
     * @param string $file Plugin file path.
     * @param string $required_version Required version constraint.
     * @param string $current_version Current installed version.
     * @param bool   $required Whether plugin is required.
     * @return void
     * @since 1.0.0
     */
    private function handle_version_mismatch(
        string $file,
        string $required_version,
        string $current_version,
        bool $required
    ): void {
        error_log(sprintf(
            '[PluginActivator] Version mismatch for %s. Required %s, found %s.',
            $file,
            $required_version,
            $current_version
        ));

        if ($required) {
            error_log(sprintf(
                '[PluginActivator] REQUIRED plugin %s does not meet version %s. Proceeding to activate anyway.',
                $file,
                $required_version
            ));
        }
    }

    /**
     * Activate the plugin using utility methods.
     *
     * @param array $config Plugin configuration.
     * @return void
     * @since 1.0.0
     */
    private function activate_plugin(array $config): void
    {
        ActivationUtils::activate_plugins([$config['data']]);
    }
}
