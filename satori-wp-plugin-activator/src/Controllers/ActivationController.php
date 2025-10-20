<?php
/**
 * Activation Controller
 *
 * Orchestrates plugin activation across all activator types.
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
 * Collects, validates, and executes plugin activation actions from
 * multiple activator types in globally sorted order.
 *
 * @package SatoriDigital\PluginActivator\Controllers
 * @since   1.0.0
 */
class ActivationController
{
    /**
     * Loaded configuration array.
     *
     * @var array
     * @since 1.0.0
     */
    protected array $config = [];

    /**
     * List of activator instances.
     *
     * @var array<int, object>
     * @since 1.0.0
     */
    protected array $activators = [];

    /**
     * Constructor.
     *
     * Loads configuration and initializes all activators.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $loader       = new ConfigLoader();
        $this->config = $loader->load();

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
     * Collects activation items from all activators, sorts by priority,
     * and delegates activation and validation to ActivationUtils.
     *
     * @return void
     * @since 1.0.0
     */
    public function run(): void
    {
        $collected = [];

        foreach ($this->activators as $activator) {
            $items = $activator->collect();
            if (!empty($items)) {
                $collected = array_merge($collected, $items);
            }
        }

        if (empty($collected)) {
            return;
        }

        usort($collected, static function (array $a, array $b): int {
            return ($a['order'] ?? 10) <=> ($b['order'] ?? 10);
        });

        ActivationUtils::deactivate_unlisted_plugins($collected);
        ActivationUtils::check_versions($collected);
        ActivationUtils::activate_plugins($collected);
    }

    /**
     * Process activation and deactivation for collected items.
     *
     * @param array $items Array of plugin items to process.
     * @return void
     * @since 1.0.0
     */
    protected function process_activation(array $items): void
    {
        if (empty($items)) {
            return;
        }

        $validation = $this->validate_plugin_items($items);

        if (!empty($validation['to_deactivate'])) {
            $this->execute_deactivations($validation['to_deactivate']);
        }

        if (!empty($validation['to_activate'])) {
            $this->execute_activations($validation['to_activate']);
        }
    }

    /**
     * Validate plugin items and categorize them for activation or deactivation.
     *
     * @param array $items Plugin items to validate.
     * @return array{to_activate:array,to_deactivate:array}
     * @since 1.0.0
     */
    private function validate_plugin_items(array $items): array
    {
        $to_activate   = [];
        $to_deactivate = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $result = $this->validate_single_item($item);

            if (($result['should_activate'] ?? false) && !empty($result['file'])) {
                $to_activate[] = $result['file'];
            } elseif (($result['should_deactivate'] ?? false) && !empty($result['file'])) {
                $to_deactivate[] = $result['file'];
            }
        }

        return [
            'to_activate'   => $to_activate,
            'to_deactivate' => $to_deactivate,
        ];
    }

    /**
     * Validate a single plugin item.
     *
     * @param array $item Plugin item to validate.
     * @return array{file:?string,should_activate:bool,should_deactivate:bool}
     * @since 1.0.0
     */
    private function validate_single_item(array $item): array
    {
        $file     = $item['file'] ?? null;
        $version  = $item['version'] ?? null;
        $required = (bool)($item['required'] ?? false);

        if (!$file || !is_string($file)) {
            return ['file' => null, 'should_activate' => false, 'should_deactivate' => false];
        }

        if (ActivationUtils::is_plugin_file_missing($file)) {
            $this->handle_missing_plugin($file, $required);
            return ['file' => $file, 'should_activate' => false, 'should_deactivate' => true];
        }

        if ($version && !ActivationUtils::check_version($file, $version)) {
            ActivationUtils::log_version_mismatch($file, $version);
            return ['file' => $file, 'should_activate' => false, 'should_deactivate' => true];
        }

        return ['file' => $file, 'should_activate' => true, 'should_deactivate' => false];
    }

    /**
     * Handle missing plugin file logging for required plugins.
     *
     * @param string $file Plugin file path.
     * @param bool   $required Whether plugin is required.
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
     * @param array<int,string> $plugins Plugin files to deactivate.
     * @return void
     * @since 1.0.0
     */
    private function execute_deactivations(array $plugins): void
    {
        if (empty($plugins)) {
            return;
        }

        // Defensive check in case ActivationUtils changes signature.
        if (method_exists(ActivationUtils::class, 'deactivate_plugins')) {
            ActivationUtils::deactivate_plugins($plugins);
        }
    }

    /**
     * Execute plugin activations.
     *
     * @param array<int,string> $plugins Plugin files to activate.
     * @return void
     * @since 1.0.0
     */
    private function execute_activations(array $plugins): void
    {
        if (empty($plugins)) {
            return;
        }

        ActivationUtils::activate_plugins($plugins);
    }
}
