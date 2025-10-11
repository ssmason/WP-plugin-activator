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
        $to_activate   = [];
        $to_deactivate = [];

        foreach ($items as $item) {
            $file     = $item['file'] ?? null;
            $version  = $item['version'] ?? null;
            $required = $item['required'] ?? false;

            if (! $file) {
                continue;
            }

            // Check if file exists and handle missing files.
            if (ActivationUtils::is_plugin_file_missing($file)) {
                if ($required) {
                    ActivationUtils::log_missing_plugin($file);
                }

                $to_deactivate[] = $file;
                continue;
            }

            // Version constraint validation.
            if ($version && ! ActivationUtils::check_version($file, $version)) {
                ActivationUtils::log_version_mismatch($file, $version);
                $to_deactivate[] = $file;
                continue;
            }

            // Add to activation queue.
            $to_activate[] = $file;
        }

        // Deactivate plugins not required or failing checks.
        if (! empty($to_deactivate)) {
            ActivationUtils::deactivate_plugins($to_deactivate);
        }

        // Activate required plugins in sorted order.
        if (! empty($to_activate)) {
            ActivationUtils::activate_plugins($to_activate);
        }
    }
}
