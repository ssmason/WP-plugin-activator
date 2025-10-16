<?php
/**
 * @group unit
 */

declare(strict_types=1);

use SatoriDigital\PluginActivator\Activators\FilterActivator;

beforeEach(function () {
    $this->config = [
        'filtered' => [ 
            [
                'hook' => 'init',
                'priority' => 10,
                'order' => 5,
                'plugins' => [
                    ['file' => 'plugin-a/plugin-a.php'],
                    ['file' => 'plugin-b/plugin-b.php'],
                ],
            ],
            [
                'hook' => 'wp_loaded',
                'priority' => 20,
                'order' => 15,
                'plugins' => [
                    ['file' => 'plugin-c/plugin-c.php'],
                ],
            ],
            [
                // Missing 'hook', should be skipped
                'priority' => 99,
                'order' => 99,
                'plugins' => [
                    ['file' => 'plugin-d/plugin-d.php'],
                ],
            ],
            [
                // Missing 'plugins', should be skipped
                'hook' => 'admin_init',
                'priority' => 30,
                'order' => 30,
            ],
        ],
    ];
    $this->activator = new FilterActivator($this->config);
});

it('returns correct type from get_type', function () {
    expect($this->activator->get_type())->toBe('filter');
});

it('collect returns correct filtered items', function () {
    $items = $this->activator->collect();
    expect($items)->toBeArray();
    expect($items)->toHaveCount(2); // Only valid filters
    foreach ($items as $item) {
        expect($item)->toHaveKeys(['type', 'order', 'data']);
        expect($item['type'])->toBe('filter');
        expect($item['data'])->toHaveKey('hook');
        expect($item['data'])->toHaveKey('plugins');
    }
});

it('collect sets correct order values', function () {
    $items = $this->activator->collect();
    $orders = array_map(fn($item) => $item['order'], $items);
    expect($orders)->toBe([5, 15]);
});

it('collect skips filters with missing hook or plugins', function () {
    $items = $this->activator->collect();
    $hooks = array_map(fn($item) => $item['data']['hook'], $items);
    expect($hooks)->not->toContain(null);
    expect($hooks)->not->toContain('');
    foreach ($items as $item) {
        expect($item['data']['plugins'])->toBeArray();
        expect($item['data']['plugins'])->not->toBeEmpty();
    }
});

it('collects filter with extra config keys', function () {
    $config = [
        'filtered' => [
            [
                'hook' => 'custom_hook',
                'priority' => 99,
                'order' => 1,
                'custom' => 'value',
                'plugins' => [
                    ['file' => 'plugin-x/plugin-x.php'],
                ],
            ],
        ],
    ];
    $activator = new FilterActivator($config);
    $items = $activator->collect();
    expect($items)->toHaveCount(1);
    expect($items[0]['data']['priority'])->toBe(99);
    expect($items[0]['data']['custom'])->toBe('value');
});

it('collects multiple filters with same order value', function () {
    $config = [
        'filtered' => [
            [
                'hook' => 'hook-a',
                'order' => 1,
                'plugins' => [ ['file' => 'plugin-a/plugin-a.php'] ],
            ],
            [
                'hook' => 'hook-b',
                'order' => 1,
                'plugins' => [ ['file' => 'plugin-b/plugin-b.php'] ],
            ],
        ],
    ];
    $activator = new FilterActivator($config);
    $items = $activator->collect();
    expect($items)->toHaveCount(2);
    $orders = array_map(fn($item) => $item['order'], $items);
    expect($orders)->toBe([1, 1]);
});

it('collects empty filtered array', function () {
    $activator = new FilterActivator(['filtered' => []]);
    $items = $activator->collect();
    expect($items)->toBeArray();
    expect($items)->toHaveCount(0);
});

it('skips filter with empty string or null hook', function () {
    $config = [
        'filtered' => [
            [ 'hook' => '', 'plugins' => [ ['file' => 'plugin-c/plugin-c.php'] ] ],
            [ 'hook' => null, 'plugins' => [ ['file' => 'plugin-d/plugin-d.php'] ] ],
            [ 'hook' => 'valid_hook', 'plugins' => [ ['file' => 'plugin-e/plugin-e.php'] ] ],
        ],
    ];
    $activator = new FilterActivator($config);
    $items = $activator->collect();
    expect($items)->toHaveCount(1);
    expect($items[0]['data']['hook'])->toBe('valid_hook');
});

it('skips non-array/malformed filter entry', function () {
    $config = [
        'filtered' => [
            'not-an-array',
            [ 'hook' => 'hook-x', 'plugins' => [ ['file' => 'plugin-x/plugin-x.php'] ] ],
        ],
    ];
    $activator = new FilterActivator($config);
    $items = $activator->collect();
    expect($items)->toHaveCount(1);
    expect($items[0]['data']['hook'])->toBe('hook-x');
});

it('collects filter missing optional keys', function () {
    $config = [
        'filtered' => [
            [ 'hook' => 'hook-y', 'plugins' => [ ['file' => 'plugin-y/plugin-y.php'] ] ], // no priority, order
        ],
    ];
    $activator = new FilterActivator($config);
    $items = $activator->collect();
    expect($items)->toHaveCount(1);
    expect($items[0]['data']['hook'])->toBe('hook-y');
    expect($items[0]['order'])->toBe(0); // default order
});

it('collects filters with duplicate hook values', function () {
    $config = [
        'filtered' => [
            [ 'hook' => 'dup_hook', 'plugins' => [ ['file' => 'plugin-z/plugin-z.php'] ], 'order' => 1 ],
            [ 'hook' => 'dup_hook', 'plugins' => [ ['file' => 'plugin-w/plugin-w.php'] ], 'order' => 2 ],
        ],
    ];
    $activator = new FilterActivator($config);
    $items = $activator->collect();
    expect($items)->toHaveCount(2);
    $hooks = array_map(fn($item) => $item['data']['hook'], $items);
    expect($hooks)->toBe(['dup_hook', 'dup_hook']);
});
