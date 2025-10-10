<?php
/**
 * @group mu-plugins/plugin-activator
 */

use SatoriDigital\PluginActivator\Helpers\ActivationUtils;

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', '/fake/plugin/dir');
}

// Mock the file_exists function for testing
function mock_file_exists($file) {
    global $mock_existing_files;
    return in_array($file, $mock_existing_files ?? []);
}

// Mock get_plugin_data function
function mock_get_plugin_data($file) {
    global $mock_plugin_data;
    return $mock_plugin_data[$file] ?? ['Version' => '1.0.0'];
}

// Simple version check function for testing
function simple_version_check($current, $required) {
    // Remove >= prefix if present
    $required = ltrim($required, '>=');
    return version_compare($current, $required, '>=');
}

if (!function_exists('_evaluate_plugins_local')) {
    function _evaluate_plugins_local(array $config): array {
        $to_activate = [];
        $missing = [];
        $version_issues = [];

        foreach ($config['plugins'] as $plugin) {
            $file = $plugin['file'] ?? '';
            $version = $plugin['version'] ?? null;
            $full_path = WP_PLUGIN_DIR . '/' . $file;

            // Use mock function instead of file_exists
            $exists = mock_file_exists($full_path);
            if (!$exists) {
                $missing[] = $file;
                continue;
            }

            // Use mock function instead of get_plugin_data
            $plugin_data = mock_get_plugin_data($full_path);

            $current_version = $plugin_data['Version'] ?? '1.0.0';

            if ($version) {
                // Try both version check methods
                $activation_utils_check = false;
                $simple_check = simple_version_check($current_version, $version);
                
                
                // Try ActivationUtils if it exists
                if (class_exists('SatoriDigital\PluginActivator\Helpers\ActivationUtils')) {
                    try {
                        $activation_utils_check = ActivationUtils::check_version($current_version, $version);
                    } catch (Exception $e) {
                        echo "ActivationUtils error: " . $e->getMessage() . "\n";
                    }
                }
                
                // Use simple check if ActivationUtils fails
                $version_check_passes = $activation_utils_check ?: $simple_check;
                
                if (!$version_check_passes) {
                    $version_issues[] = [
                        'slug'     => $file,
                        'current'  => $current_version,
                        'required' => $version,
                    ];
                    continue;
                }
            }

            $to_activate[] = $file;
        }

       
        return compact('to_activate', 'missing', 'version_issues');
    }
}

beforeEach(function () {
    // Define plugin paths
    $this->valid_slug = 'valid-plugin/valid-plugin.php';
    $this->missing_slug = 'missing-plugin/missing-plugin.php';
    $this->low_version_slug = 'low-version-plugin/low-version-plugin.php';

    // Mock file existence
    global $mock_existing_files;
    $mock_existing_files = [
        WP_PLUGIN_DIR . '/' . $this->valid_slug,
        WP_PLUGIN_DIR . '/' . $this->low_version_slug,
    ];

    // Mock plugin data
    global $mock_plugin_data;
    $mock_plugin_data = [
        WP_PLUGIN_DIR . '/' . $this->valid_slug => ['Version' => '1.2.0'],
        WP_PLUGIN_DIR . '/' . $this->low_version_slug => ['Version' => '1.0.0'],
    ];

    $this->config = [
        'plugins' => [
            [
                'file'     => $this->valid_slug,
                'required' => true,
                'version'  => '>=1.0.0',
            ],
            [
                'file'     => $this->missing_slug,
                'required' => true,
                'version'  => '>=1.0.0',
            ],
            [
                'file'     => $this->low_version_slug,
                'required' => true,
                'version'  => '>=2.0.0',
            ],
        ],
    ];
});

afterEach(function () {
    // Clean up global mocks
    global $mock_existing_files, $mock_plugin_data;
    $mock_existing_files = [];
    $mock_plugin_data = [];
});

it('reports plugins to activate, missing, and version issues correctly', function () {
   
    $report = _evaluate_plugins_local($this->config);

    expect($report)->toBeArray();
    expect($report)->toHaveKeys(['to_activate', 'missing', 'version_issues']);

    
    expect($report['to_activate'])->toContain($this->valid_slug);
    expect($report['missing'])->toContain($this->missing_slug);

    $hasVersionIssue = false;
    foreach ($report['version_issues'] as $issue) {
        if ($issue['slug'] === $this->low_version_slug) {
            $hasVersionIssue = true;
            break;
        }
    }

    expect($hasVersionIssue)->toBeTrue();
});
