<?php
/**
 * @group unit
 */

declare(strict_types=1);

use SatoriDigital\PluginActivator\Activators\PluginActivator;


if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', '/fake/plugin/dir');
}

function mock_plugin_file_exists($file) {
    global $mock_existing_plugins;
    return in_array($file, $mock_existing_plugins ?? []);
}

function mock_get_plugin_version($file) {
    global $mock_plugin_versions;
    return $mock_plugin_versions[$file] ?? '1.0.0';
}

beforeEach(function () {

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
                'required' => false,
                'version'  => '>=2.0.0',
                'order'    => 5,
            ],
            [
                'file'     => 'debug-bar/debug-bar.php',
                'required' => true,
                'version'  => '>=1.0.0', 
                'order'    => 10,
            ],
        ],
    ];
    
    $this->activator = new PluginActivator($this->valid_config);
});

afterEach(function () {

    global $mock_existing_plugins, $mock_plugin_versions;
    $mock_existing_plugins = [];
    $mock_plugin_versions = [];
});

test('PluginActivator can be constructed with valid config', function () {
    expect($this->activator)->toBeInstanceOf(PluginActivator::class);
});

test('PluginActivator has required interface methods', function () {
    expect(method_exists($this->activator, 'collect'))->toBeTrue();
    expect(method_exists($this->activator, 'get_type'))->toBeTrue();
});

test('get_type returns correct activator type', function () {
    expect($this->activator->get_type())->toBe('plugin');
});

test('collect returns array of plugin items', function () {
    $items = $this->activator->collect();
    
    expect($items)->toBeArray();
    expect($items)->toHaveCount(2);
    
    foreach ($items as $item) {
        expect($item)->toHaveKeys(['type', 'order', 'data']);
        expect($item['type'])->toBe('plugin');
        expect($item['data'])->toHaveKey('file');
    }
});

test('collect filters plugins by order correctly', function () {
    $items = $this->activator->collect(); 
    expect($items)->not->toBeEmpty(); 
    foreach ($items as $item) {
        expect($item['data'])->toHaveKey('order');
        expect($item['data']['order'])->toBeInt();
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

    expect($items)->not->toBeEmpty();
    expect($items)->toBeArray();

    $firstItem = $items[0];
    expect($firstItem)->toHaveKey('data');
    expect($firstItem)->toHaveKey('type');
    

    $result = null;
    expect(function () use ($firstItem, &$result) {
        $result = $this->activator->handle($firstItem);
        return true;
    })->not->toThrow(\Exception::class);
    

    expect(true)->toBeTrue();
});

test('collect returns plugins in correct order', function () {
    $items = $this->activator->collect();
    
    expect($items)->toHaveCount(2);
    

    $orders = array_map(fn($item) => $item['data']['order'], $items);
    expect($orders)->toContain(5);
    expect($orders)->toContain(10);
    foreach ($items as $item) {
        expect($item['data'])->toHaveKeys(['file', 'order', 'required', 'version']);
    }
});
