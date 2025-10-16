<?php
/**
 * @group mu-plugins/plugin-activator
 */

use SatoriDigital\PluginActivator\Helpers\ActivationUtils;

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', '/fake/plugin/dir');
}


function mock_file_exists($file) {
    global $mock_existing_files;
    return in_array($file, $mock_existing_files ?? []);
}

 
function mock_get_plugin_data($file) {
    global $mock_plugin_data;
    return $mock_plugin_data[$file] ?? ['Version' => '1.0.0'];
}


function simple_version_check($current, $required) {

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
            if (empty($file)) {
                continue; // Skip malformed plugin config
            }
            $version = $plugin['version'] ?? null;
            $required = $plugin['required'] ?? false; // ✅ ADD THIS LINE
            $full_path = WP_PLUGIN_DIR . '/' . $file;

            $exists = mock_file_exists($full_path);
            if (!$exists) {
                // ✅ ADD THESE LINES
                if ($required) {
                    error_log('[PluginActivator] REQUIRED plugin missing: ' . $file);
                } else {
                    error_log('[PluginActivator] Optional plugin missing: ' . $file);
                }
                $missing[] = $file;
                continue;
            }


            $plugin_data = mock_get_plugin_data($full_path);

            $current_version = $plugin_data['Version'] ?? '1.0.0';

            if ($version) {

                $activation_utils_check = false;
                $simple_check = simple_version_check($current_version, $version);
                
                

                if (class_exists('SatoriDigital\PluginActivator\Helpers\ActivationUtils')) {
                    try {
                        $activation_utils_check = ActivationUtils::check_version($current_version, $version);
                    } catch (Exception $e) {
                        echo "ActivationUtils error: " . $e->getMessage() . "\n";
                    }
                }
                

                $version_check_passes = $activation_utils_check ?: $simple_check;
                
                if (!$version_check_passes) {
                    // ✅ ADD THESE LINES
                    if ($required) {
                        error_log('[PluginActivator] REQUIRED plugin version mismatch: ' . $file);
                    } else {
                        error_log('[PluginActivator] Optional plugin version mismatch: ' . $file);
                    }
                    
                    $version_issues[] = [
                        'slug'     => $file,
                        'current'  => $current_version,
                        'required' => $version,
                        'is_required' => $required, // ✅ ADD THIS LINE
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

    $this->valid_slug = 'valid-plugin/valid-plugin.php';
    $this->missing_slug = 'missing-plugin/missing-plugin.php';
    $this->low_version_slug = 'low-version-plugin/low-version-plugin.php';


    global $mock_existing_files;
    $mock_existing_files = [
        WP_PLUGIN_DIR . '/' . $this->valid_slug,
        WP_PLUGIN_DIR . '/' . $this->low_version_slug,
    ];


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

it('reports optional plugin missing correctly', function () {
    $optional_slug = 'optional-plugin/optional-plugin.php';
    global $mock_existing_files;
    $mock_existing_files = [WP_PLUGIN_DIR . '/' . $this->valid_slug];
    $config = [
        'plugins' => [
            [
                'file'     => $this->valid_slug,
                'required' => true,
            ],
            [
                'file'     => $optional_slug,
                'required' => false,
            ],
        ],
    ];
    $report = _evaluate_plugins_local($config);
    expect($report['missing'])->toContain($optional_slug);
});

it('reports optional plugin version mismatch correctly', function () {
    $optional_slug = 'optional-plugin/optional-plugin.php';
    global $mock_existing_files, $mock_plugin_data;
    $mock_existing_files = [WP_PLUGIN_DIR . '/' . $optional_slug];
    $mock_plugin_data = [WP_PLUGIN_DIR . '/' . $optional_slug => ['Version' => '1.0.0']];
    $config = [
        'plugins' => [
            [
                'file'     => $optional_slug,
                'required' => false,
                'version'  => '>=2.0.0',
            ],
        ],
    ];
    $report = _evaluate_plugins_local($config);
    $hasVersionIssue = false;
    foreach ($report['version_issues'] as $issue) {
        if ($issue['slug'] === $optional_slug && !$issue['is_required']) {
            $hasVersionIssue = true;
            break;
        }
    }
    expect($hasVersionIssue)->toBeTrue();
});

it('activates plugin with no version constraint if present', function () {
    $slug = 'noversion-plugin/noversion-plugin.php';
    global $mock_existing_files;
    $mock_existing_files = [WP_PLUGIN_DIR . '/' . $slug];
    $config = [
        'plugins' => [
            [
                'file'     => $slug,
                'required' => true,
            ],
        ],
    ];
    $report = _evaluate_plugins_local($config);
    expect($report['to_activate'])->toContain($slug);
});

it('skips malformed plugin config (missing file key)', function () {
    $config = [
        'plugins' => [
            [
                'required' => true,
                'version'  => '>=1.0.0',
            ],
        ],
    ];
    $report = _evaluate_plugins_local($config);
    expect($report['to_activate'])->toBeEmpty();
    expect($report['missing'])->toBeEmpty();
    expect($report['version_issues'])->toBeEmpty();
});
