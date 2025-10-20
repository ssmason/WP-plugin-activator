<?php
/**
 * @group unit
 */

declare(strict_types=1);

namespace SatoriDigital\PluginActivator\Activators {
    $GLOBALS['__test_site_url'] = 'https://example.com';
    function site_url() {
        return $GLOBALS['__test_site_url'];
    }
}

namespace {
    use SatoriDigital\PluginActivator\Activators\GroupActivator;

    beforeEach(function () {
        $GLOBALS['__test_site_url'] = 'https://example.com';
        $this->config = [
            'groups' => [
                'production' => [
                    'url' => 'https://example.com',
                    'plugins' => [
                        ['file' => 'plugin-a/plugin-a.php', 'order' => 1],
                        ['file' => 'plugin-b/plugin-b.php', 'order' => 2],
                    ],
                ],
                'staging' => [
                    'url' => 'https://staging.example.com',
                    'plugins' => [
                        ['file' => 'plugin-c/plugin-c.php', 'order' => 3],
                    ],
                ],
                'dev' => [
                    'url' => 'https://dev.example.com',
                    'plugins' => [
                        ['file' => 'plugin-d/plugin-d.php', 'order' => 4],
                    ],
                ],
            ],
        ];
        $this->activator = new GroupActivator($this->config);
    });

    it('returns correct type from get_type', function () {
        expect($this->activator->get_type())->toBe('group');
    });

    it('collect returns correct plugins for matching environment', function () {
        $items = $this->activator->collect();
        expect($items)->toBeArray();
        expect($items)->toHaveCount(2);
        foreach ($items as $item) {
            expect($item)->toHaveKeys(['type', 'order', 'data']);
            expect($item['type'])->toBe('group');
            expect($item['data'])->toHaveKey('file');
        }
    });

    it('collect sets correct order values', function () {
        $items = $this->activator->collect();
        $orders = array_map(fn($item) => $item['order'], $items);
        expect($orders)->toBe([1, 2]);
    });

    it('collect skips plugins with missing file', function () {
        $config = [
            'groups' => [
                'production' => [
                    'url' => 'https://example.com',
                    'plugins' => [
                        ['order' => 1], // missing file
                        ['file' => 'plugin-b/plugin-b.php', 'order' => 2],
                    ],
                ],
            ],
        ];
        $activator = new GroupActivator($config);
        $items = $activator->collect();
        expect($items)->toHaveCount(1);
        expect($items[0]['data']['file'])->toBe('plugin-b/plugin-b.php');
    });

    it('collect returns empty array for no matching group', function () {
        $config = [
            'groups' => [
                'staging' => [
                    'url' => 'https://staging.example.com',
                    'plugins' => [
                        ['file' => 'plugin-c/plugin-c.php', 'order' => 3],
                    ],
                ],
            ],
        ];
        $activator = new GroupActivator($config);
        $items = $activator->collect();
        expect($items)->toBeArray();
        expect($items)->toHaveCount(0);
    });

    it('collect returns empty array for empty config', function () {
        $activator = new GroupActivator([]);
        $items = $activator->collect();
        expect($items)->toBeArray();
        expect($items)->toHaveCount(0);
    });

    it('collect handles multiple plugins with same order value', function () {
        $config = [
            'groups' => [
                'production' => [
                    'url' => 'https://example.com',
                    'plugins' => [
                        ['file' => 'plugin-a/plugin-a.php', 'order' => 1],
                        ['file' => 'plugin-b/plugin-b.php', 'order' => 1],
                    ],
                ],
            ],
        ];
        $activator = new GroupActivator($config);
        $items = $activator->collect();
        expect($items)->toHaveCount(2);
        $orders = array_map(fn($item) => $item['order'], $items);
        expect($orders)->toBe([1, 1]);
    });

    it('collect accepts plugin with version key', function () {
        $config = [
            'groups' => [
                'production' => [
                    'url' => 'https://example.com',
                    'plugins' => [
                        ['file' => 'plugin-a/plugin-a.php', 'order' => 1, 'version' => '1.2.3'],
                    ],
                ],
            ],
        ];
        $activator = new GroupActivator($config);
        $items = $activator->collect();
        expect($items)->toHaveCount(1);
        expect($items[0]['data']['version'])->toBe('1.2.3');
    });

    it('collect handles group missing plugins key', function () {
        $config = [
            'groups' => [
                'production' => [
                    'url' => 'https://example.com',
                    // no plugins key
                ],
            ],
        ];
        $activator = new GroupActivator($config);
        $items = $activator->collect();
        expect($items)->toBeArray();
        expect($items)->toHaveCount(0);
    });

    it('collect matches group URL with trailing slash', function () {
        $GLOBALS['__test_site_url'] = 'https://example.com/';
        $config = [
            'groups' => [
                'production' => [
                    'url' => 'https://example.com/',
                    'plugins' => [
                        ['file' => 'plugin-a/plugin-a.php', 'order' => 1],
                    ],
                ],
            ],
        ];
        $activator = new GroupActivator($config);
        $items = $activator->collect();
        expect($items)->toHaveCount(1);
        expect($items[0]['data']['file'])->toBe('plugin-a/plugin-a.php');
        $GLOBALS['__test_site_url'] = 'https://example.com'; // reset
    });

    it('collect accepts plugin with extra irrelevant keys', function () {
        $config = [
            'groups' => [
                'production' => [
                    'url' => 'https://example.com',
                    'plugins' => [
                        ['file' => 'plugin-a/plugin-a.php', 'order' => 1, 'foo' => 'bar', 'baz' => 123],
                    ],
                ],
            ],
        ];
        $activator = new GroupActivator($config);
        $items = $activator->collect();
        expect($items)->toHaveCount(1);
        expect($items[0]['data']['foo'])->toBe('bar');
        expect($items[0]['data']['baz'])->toBe(123);
    });
} 