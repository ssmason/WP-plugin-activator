<?php
/**
 * @group mu-plugins/plugin-activator
 * @coversNothing
 */

use SatoriDigital\PluginActivator\Helpers\ConfigLoader;

beforeAll(function () {
    if (!defined('PLUGIN_ACTIVATION_CONFIG')) {
        define('PLUGIN_ACTIVATION_CONFIG', dirname(__DIR__, 3) . '/src/config');
    }

    // ✅ Force get_option('stylesheet') to return our fake theme *before* WP reads it.
    add_filter('pre_option_stylesheet', function () {
        return 'unit-test-theme';
    });
});

beforeEach(function () {
    $this->configDir = PLUGIN_ACTIVATION_CONFIG;
    wp_mkdir_p($this->configDir);

    $this->themeKey = 'unit-test-theme';
    $this->slug = create_dummy_plugin('dummy-configloader', '1.0.0');

    // Clean up any old file
    $configPath = "{$this->configDir}/{$this->themeKey}.json";
    if (file_exists($configPath)) {
        unlink($configPath);
    }
});

afterEach(function () {
    $configPath = "{$this->configDir}/{$this->themeKey}.json";
    if (file_exists($configPath)) {
        unlink($configPath);
    }

    if (is_plugin_active($this->slug)) {
        deactivate_plugins($this->slug, true);
    }

    $pluginDir = WP_PLUGIN_DIR . '/dummy-configloader';
    if (is_dir($pluginDir)) {
        @unlink($pluginDir . '/dummy-configloader.php');
        @rmdir($pluginDir);
    }
});

it('loads and normalizes a valid config file for the current theme', function () {
    $configPath = "{$this->configDir}/{$this->themeKey}.json";

    $configData = [
        'plugins' => [
            [
                'slug'     => $this->slug,
                'required' => true,
                'version'  => '>=1.0.0',
                'order'    => 10,
            ],
        ],
    ];

    file_put_contents($configPath, wp_json_encode($configData));

    // sanity check: file exists
    expect(file_exists($configPath))->toBeTrue();

    $loader = new ConfigLoader();
    $config = $loader->load();

    expect($config)->toBeArray();
    expect($config)->toHaveKey('plugins');
    expect($config['plugins'][0])->toHaveKeys(['slug', 'required', 'version', 'order']);
    expect($config['plugins'][0]['slug'])->toEqual($this->slug);
});

it('returns an empty array if the config file does not exist', function () {
    // Temporarily override to a theme that doesn't exist
    add_filter('pre_option_stylesheet', function () {
        return 'non-existent-theme';
    });

    $loader = new ConfigLoader();
    $config = $loader->load();

    expect($config)->toBeArray();
    expect($config)->toBeEmpty();

    // Restore for the next test
    remove_all_filters('pre_option_stylesheet');
    add_filter('pre_option_stylesheet', function () {
        return 'unit-test-theme';
    });
});

it('handles malformed JSON gracefully', function () {
    $configPath = "{$this->configDir}/{$this->themeKey}.json";
    file_put_contents($configPath, '{this is not valid json}');

    $loader = new ConfigLoader();
    $config = $loader->load();

    expect($config)->toBeArray();
    expect($config)->toBeEmpty();
});
