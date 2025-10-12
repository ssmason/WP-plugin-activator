<?php
/**
 * Activation Controller
 *
 * Main controller that orchestrates plugin activation across all activator types.
 * Collects activation instructions from multiple sources, sorts globally by order,
 * and applies them in a coordinated manner with proper validation.
 *
 * @category Plugin_Activator
 * @package  SatoriDigital\PluginActivator\Controllers
 * @author   Satori Digital
 * @license  GPL-2.0+
 * @link     https://satoridigital.com
 */

declare(strict_types=1);

namespace SatoriDigital\PluginActivator\Controllers;

use SatoriDigital\PluginActivator\Helpers\ConfigLoader;
use SatoriDigital\PluginActivator\Activators\PluginActivator;
use SatoriDigital\PluginActivator\Activators\FilterActivator;
use SatoriDigital\PluginActivator\Activators\SettingsActivator;
use SatoriDigital\PluginActivator\Activators\GroupActivator;
use SatoriDigital\PluginActivator\Helpers\ActivationUtils;

/**
 * Class ActivationController
 *
 * Main activation controller that collects all activation instructions from
 * multiple activator types, sorts them globally by order priority, and
 * applies them in a coordinated manner with comprehensive validation.
 *
 * @package SatoriDigital\PluginActivator\Controllers
 * @since   1.0.0
 */
class ActivationController
{
    /**
     * Configuration array loaded from JSON files.
     *
     * @var array
     * @since 1.0.0
     */
    protected array $config;

    /**
     * Array of activator instances.
     *
     * @var array
     * @since 1.0.0
     */
    protected array $activators = [];

    /**
     * Controller constructor.
     *
     * Loads configuration into $this->config and initializes activators.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $loader        = new ConfigLoader();
        $this->config  = $loader->load();

        $this->activators = [
            new PluginActivator($this->config),
            new FilterActivator($this->config),
            new SettingsActivator($this->config),
            new GroupActivator($this->config),
        ];
    }

    /**
     * Run the complete activation workflow.
     *
     * Collects items from all activators, sorts globally by order,
     * then processes activation with proper validation and logging.
     *
     * @return void
     * @since 1.0.0
     */
    public function run(): void
    {
        $collected = [];

        foreach ($this->activators as $activator) {
            $collected = array_merge($collected, $activator->collect());
        }

        // Sort globally by order.
        usort($collected, function ($a, $b) {
            return ($a['order'] ?? 10) <=> ($b['order'] ?? 10);
        });

        // Deactivate unlisted plugins.
        ActivationUtils::deactivate_unlisted_plugins($collected);

        // Check version constraints.
        ActivationUtils::check_versions($collected);

        // Activate plugins in priority order.
        ActivationUtils::activate_plugins($collected);
    }

    /**
     * Process activation, deactivation, and version checks for collected items.
     *
     * Validates plugin files, checks version constraints, and determines
     * which plugins should be activated or deactivated based on requirements.
     *
     * @param array $items Array of plugin items to process.
     * @return void
     * @since 1.0.0
     */
    protected function process_activation(array $items): void
    {
        $validation_results = $this->validate_plugin_items($items);

        $this->execute_deactivations($validation_results['to_deactivate']);
        $this->execute_activations($validation_results['to_activate']);
    }

    /**
     * Validate plugin items and categorize for activation/deactivation.
     *
     * @param array $items Plugin items to validate.
     * @return array Arrays of plugins to activate and deactivate.
     * @since 1.0.0
     */
    private function validate_plugin_items(array $items): array
    {
        $to_activate = [];
        $to_deactivate = [];

        foreach ($items as $item) {
            $validation = $this->validate_single_item($item);

            if ($validation['should_activate']) {
                $to_activate[] = $validation['file'];
            } elseif ($validation['should_deactivate']) {
                $to_deactivate[] = $validation['file'];
            }
        }

        return [
            'to_activate' => $to_activate,
            'to_deactivate' => $to_deactivate,
        ];
    }

    /**
     * Validate a single plugin item.
     *
     * @param array $item Plugin item to validate.
     * @return array Validation result with actions to take.
     * @since 1.0.0
     */
    private function validate_single_item(array $item): array
    {
        $file = $item['file'] ?? null;
        $version = $item['version'] ?? null;
        $required = $item['required'] ?? false;

        if (!$file) {
            return ['should_activate' => false, 'should_deactivate' => false];
        }

        // Check file existence
        if (ActivationUtils::is_plugin_file_missing($file)) {
            $this->handle_missing_plugin($file, $required);
            return ['should_activate' => false, 'should_deactivate' => true, 'file' => $file];
        }

        // Check version constraints
        if ($version && !ActivationUtils::check_version($file, $version)) {
            ActivationUtils::log_version_mismatch($file, $version);
            return ['should_activate' => false, 'should_deactivate' => true, 'file' => $file];
        }

        return ['should_activate' => true, 'should_deactivate' => false, 'file' => $file];
    }

    /**
     * Handle missing plugin file.
     *
     * @param string $file Plugin file path.
     * @param bool $required Whether plugin is required.
     * @return void
     * @since 1.0.0
     */
    private function handle_missing_plugin(string $file, bool $required): void
    {
        if ($required) {
            ActivationUtils::log_missing_plugin($file);
        }
    }

    /**
     * Execute plugin deactivations.
     *
     * @param array $plugins Array of plugin files to deactivate.
     * @return void
     * @since 1.0.0
     */
    private function execute_deactivations(array $plugins): void
    {
        if (!empty($plugins)) {
            ActivationUtils::deactivate_plugins($plugins);
        }
    }

    /**
     * Execute plugin activations.
     *
     * @param array $plugins Array of plugin files to activate.
     * @return void
     * @since 1.0.0
     */
    private function execute_activations(array $plugins): void
    {
        if (!empty($plugins)) {
            ActivationUtils::activate_plugins($plugins);
        }
    }
}
