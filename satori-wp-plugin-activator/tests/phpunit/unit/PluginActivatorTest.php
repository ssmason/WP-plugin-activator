<?php
/**
 * @group unit
 */

declare(strict_types=1);

use SatoriDigital\PluginActivator\Activators\PluginActivator;

beforeEach(function () {
    $this->config = [
        'plugins' => [
            [
                'file' => 'plugin-a/plugin-a.php',
                'order' => 5,
                'required' => true,
                'version' => '>=1.0.0',
            ],
            [
                'file' => 'plugin-b/plugin-b.php',
                'order' => 10,
            ],
            [
                // Missing 'file', should be skipped
                'order' => 15,
            ],
        ],
    ];
    $this->activator = new PluginActivator($this->config);
});

it('returns correct type from get_type', function () {
    expect($this->activator->get_type())->toBe('plugin');
});

it('collects and returns correct plugin items', function () {
    $items = $this->activator->collect();
    expect($items)->toBeArray();
    expect($items)->toHaveCount(2); // Only valid plugins
    foreach ($items as $item) {
        expect($item)->toHaveKeys(['type', 'order', 'data']);
        expect($item['type'])->toBe('plugin');
        expect($item['data'])->toHaveKey('file');
    }
});

it('collects and sets correct order values', function () {
    $items = $this->activator->collect();
    $orders = array_map(fn($item) => $item['order'], $items);
    expect($orders)->toBe([5, 10]);
});

it('collects and skips plugins with missing file', function () {
    $items = $this->activator->collect();
    $files = array_map(fn($item) => $item['data']['file'], $items);
    expect($files)->not->toContain(null);
    expect($files)->not->toContain('');
});

it('collects plugin with extra config keys', function () {
    $config = [
        'plugins' => [
            [
                'file' => 'plugin-x/plugin-x.php',
                'order' => 1,
                'required' => true,
                'version' => '>=2.0.0',
            ],
        ],
    ];
    $activator = new PluginActivator($config);
    $items = $activator->collect();
    expect($items)->toHaveCount(1);
    expect($items[0]['data']['required'])->toBeTrue();
    expect($items[0]['data']['version'])->toBe('>=2.0.0');
});

it('collects multiple plugins with same order value', function () {
    $config = [
        'plugins' => [
            ['file' => 'plugin-a/plugin-a.php', 'order' => 1],
            ['file' => 'plugin-b/plugin-b.php', 'order' => 1],
        ],
    ];
    $activator = new PluginActivator($config);
    $items = $activator->collect();
    expect($items)->toHaveCount(2);
    $orders = array_map(fn($item) => $item['order'], $items);
    expect($orders)->toBe([1, 1]);
});

it('collects empty plugins array', function () {
    $activator = new PluginActivator(['plugins' => []]);
    $items = $activator->collect();
    expect($items)->toBeArray();
    expect($items)->toHaveCount(0);
});

it('skips plugin with empty string or null file', function () {
    $config = [
        'plugins' => [
            ['file' => '', 'order' => 1],
            ['file' => null, 'order' => 2],
            ['file' => 'plugin-c/plugin-c.php', 'order' => 3],
        ],
    ];
    $activator = new PluginActivator($config);
    $items = $activator->collect();
    expect($items)->toHaveCount(1);
    expect($items[0]['data']['file'])->toBe('plugin-c/plugin-c.php');
});

it('skips non-array/malformed plugin entry', function () {
    $config = [
        'plugins' => [
            'not-an-array',
            ['file' => 'plugin-d/plugin-d.php', 'order' => 1],
        ],
    ];
    $activator = new PluginActivator($config);
    $items = $activator->collect();
    expect($items)->toHaveCount(1);
    expect($items[0]['data']['file'])->toBe('plugin-d/plugin-d.php');
});

it('collects plugin missing optional keys', function () {
    $config = [
        'plugins' => [
            ['file' => 'plugin-e/plugin-e.php'], // no order, required, version
        ],
    ];
    $activator = new PluginActivator($config);
    $items = $activator->collect();
    expect($items)->toHaveCount(1);
    expect($items[0]['data']['file'])->toBe('plugin-e/plugin-e.php');
    expect($items[0]['order'])->toBe(0); // default order
});

it('collects plugins with duplicate file values', function () {
    $config = [
        'plugins' => [
            ['file' => 'plugin-f/plugin-f.php', 'order' => 1],
            ['file' => 'plugin-f/plugin-f.php', 'order' => 2],
        ],
    ];
    $activator = new PluginActivator($config);
    $items = $activator->collect();
    expect($items)->toHaveCount(2);
    $files = array_map(fn($item) => $item['data']['file'], $items);
    expect($files)->toBe(['plugin-f/plugin-f.php', 'plugin-f/plugin-f.php']);
});
