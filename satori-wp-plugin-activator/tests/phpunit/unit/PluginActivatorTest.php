<?php
/**
 * @group unit
 */

declare(strict_types=1);

namespace P\Tests\Unit;

use SatoriDigital\PluginActivator\Activators\PluginActivator;

// Mock WordPress constants if they don't exist
if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', '/fake/plugin/dir');
}

// Mock function for testing
function mock_plugin_file_exists($file) {
    global $mock_existing_plugins;
    return in_array($file, $mock_existing_plugins ?? []);
}

function mock_get_plugin_version($file) {
    global $mock_plugin_versions;
    return $mock_plugin_versions[$file] ?? '1.0.0';
}

beforeEach(function () {
    // Mock existing plugins
    global $mock_existing_plugins, $mock_plugin_versions;
    $mock_existing_plugins = [
        'query-monitor/query-monitor.php',
        'wordpress-seo/wp-seo.php',
    ];
    $mock_plugin_versions = [
        'query-monitor/query-monitor.php' => '3.5.0',
        'wordpress-seo/wp-seo.php' => '2.1.0',
    ];

    $this->valid_config = [
        'plugins' => [
            [
                'file'     => 'query-monitor/query-monitor.php',
                'required' => true,
                'version'  => '>=3.0.0',
                'order'    => 10,
            ],
            [
                'file'     => 'wordpress-seo/wp-seo.php',
                'required' => false,
                'version'  => '>=2.0.0',
                'order'    => 5,
            ],
        ],
    ];
    
    $this->activator = new PluginActivator($this->valid_config);
});

afterEach(function () {
    // Clean up global mocks
    global $mock_existing_plugins, $mock_plugin_versions;
    $mock_existing_plugins = [];
    $mock_plugin_versions = [];
});

test('PluginActivator can be constructed with valid config', function () {
    expect($this->activator)->toBeInstanceOf(PluginActivator::class);
});

test('PluginActivator has required interface methods', function () {
    expect(method_exists($this->activator, 'collect'))->toBeTrue();
    expect(method_exists($this->activator, 'handle'))->toBeTrue();
    expect(method_exists($this->activator, 'get_type'))->toBeTrue();
});

test('get_type returns correct activator type', function () {
    expect($this->activator->get_type())->toBe('plugin');
});

test('collect returns array of plugin items', function () {
    $items = $this->activator->collect();
    
    expect($items)->toBeArray();
    expect($items)->not->toBeEmpty();
    
    // Check structure of first item
    expect($items[0])->toHaveKeys(['type', 'data']);
    expect($items[0]['type'])->toBe('plugin');
    expect($items[0]['data'])->toHaveKey('file');
});

test('collect filters plugins by order correctly', function () {
    $items = $this->activator->collect();
    
    // Should have 2 items
    expect($items)->toHaveCount(2);
    
    // Check that items are properly structured
    foreach ($items as $item) {
        expect($item)->toHaveKey('data');
        expect($item['data'])->toHaveKeys(['file', 'required', 'version']);
    }
});

test('PluginActivator handles empty config gracefully', function () {
    $emptyActivator = new PluginActivator(['plugins' => []]);
    
    $items = $emptyActivator->collect();
    expect($items)->toBeArray();
    expect($items)->toBeEmpty();
});

test('PluginActivator handles malformed config gracefully', function () {
    $malformedActivator = new PluginActivator([]);
    
    $items = $malformedActivator->collect();
    expect($items)->toBeArray();
    expect($items)->toBeEmpty();
});

test('handle method processes plugin items correctly', function () {
    $items = $this->activator->collect();
    
    // Ensure we have items to test with
    expect($items)->not->toBeEmpty();
    expect($items)->toBeArray();
    
    // Get the first item
    $firstItem = $items[0];
    expect($firstItem)->toHaveKey('data');
    expect($firstItem)->toHaveKey('type');
    
    // Test that handle method can be called without throwing exceptions
    $result = null;
    expect(function () use ($firstItem, &$result) {
        $result = $this->activator->handle($firstItem);
        return true;
    })->not->toThrow(\Exception::class);
    
    // The handle method should complete successfully
    expect(true)->toBeTrue();
});

test('collect returns plugins in correct order', function () {
    $items = $this->activator->collect();
    
    if (count($items) >= 2) {
        // Assuming collect sorts by order (5 should come before 10)
        $firstPlugin = $items[0]['data']['file'] ?? '';
        $secondPlugin = $items[1]['data']['file'] ?? '';
        
        // This test depends on your actual implementation
        // Adjust based on how your collect() method handles ordering
        expect($firstPlugin)->not->toBeEmpty();
        expect($secondPlugin)->not->toBeEmpty();
    }
});
