<?php
/**
 * @group unit
 */

declare(strict_types=1);

namespace SatoriDigital\PluginActivator\Activators {
    function get_option($field) {
        $map = [
            'site_mode' => 'live', // equals: 'live'
            'site_status' => 'active', // not_equals: 'inactive'
            'site_tags' => 'wordpress,plugin,mu', // contains: 'plugin'
            'site_array' => 'foo', // in: ['foo', 'bar']
        ];
        return $map[$field] ?? false;
    }
}

namespace {
    use SatoriDigital\PluginActivator\Activators\SettingsActivator;

    beforeEach(function () {
        $this->config = [
            'settings' => [
                [
                    'field' => 'site_mode',
                    'operator' => 'equals',
                    'value' => 'live',
                    'order' => 1,
                    'plugins' => [['file' => 'plugin-a/plugin-a.php']],
                ],
                [
                    'field' => 'site_status',
                    'operator' => 'not_equals',
                    'value' => 'inactive',
                    'order' => 2,
                    'plugins' => [['file' => 'plugin-b/plugin-b.php']],
                ],
                [
                    'field' => 'site_tags',
                    'operator' => 'contains',
                    'value' => 'plugin',
                    'order' => 3,
                    'plugins' => [['file' => 'plugin-c/plugin-c.php']],
                ],
                [
                    'field' => 'site_array',
                    'operator' => 'in',
                    'value' => ['foo', 'bar'],
                    'order' => 4,
                    'plugins' => [['file' => 'plugin-d/plugin-d.php']],
                ],
                [
                    // Missing field, should be skipped
                    'operator' => 'equals',
                    'value' => 'baz',
                    'order' => 5,
                    'plugins' => [['file' => 'plugin-e/plugin-e.php']],
                ],
                [
                    // Missing plugins, should be skipped
                    'field' => 'site_mode',
                    'operator' => 'equals',
                    'value' => 'live',
                    'order' => 6,
                ],
            ],
        ];
        $this->activator = new SettingsActivator($this->config);
    });

    it('returns correct type from get_type', function () {
        expect($this->activator->get_type())->toBe('setting');
    });

    it('collect returns correct settings items for all operators', function () {
        $items = $this->activator->collect();
        expect($items)->toBeArray();
        expect($items)->toHaveCount(4); // Only valid and passing conditions
        foreach ($items as $item) {
            expect($item)->toHaveKeys(['type', 'order', 'data']);
            expect($item['type'])->toBe('setting');
            expect($item['data'])->toHaveKey('field');
            expect($item['data'])->toHaveKey('plugins');
            expect($item['data']['plugins'])->toBeArray();
            expect($item['data']['plugins'])->not->toBeEmpty();
        }
    });

    it('collect sets correct order values', function () {
        $items = $this->activator->collect();
        $orders = array_map(fn($item) => $item['order'], $items);
        expect($orders)->toBe([1, 2, 3, 4]);
    });

    it('collect skips settings with missing field or plugins', function () {
        $items = $this->activator->collect();
        foreach ($items as $item) {
            expect($item['data']['field'])->not->toBeEmpty();
            expect($item['data']['plugins'])->toBeArray();
            expect($item['data']['plugins'])->not->toBeEmpty();
        }
    });

    it('handle returns true for matching equals operator', function () {
        $item = [
            'field' => 'site_mode',
            'operator' => 'equals',
            'value' => 'live',
            'plugins' => [['file' => 'plugin-a/plugin-a.php']],
        ];
        expect($this->activator->handle($item))->toBeTrue();
    });

    it('handle returns true for matching not_equals operator', function () {
        $item = [
            'field' => 'site_status',
            'operator' => 'not_equals',
            'value' => 'inactive',
            'plugins' => [['file' => 'plugin-b/plugin-b.php']],
        ];
        expect($this->activator->handle($item))->toBeTrue();
    });

    it('handle returns true for matching contains operator', function () {
        $item = [
            'field' => 'site_tags',
            'operator' => 'contains',
            'value' => 'plugin',
            'plugins' => [['file' => 'plugin-c/plugin-c.php']],
        ];
        expect($this->activator->handle($item))->toBeTrue();
    });

    it('handle returns true for matching in operator', function () {
        $item = [
            'field' => 'site_array',
            'operator' => 'in',
            'value' => ['foo', 'bar'],
            'plugins' => [['file' => 'plugin-d/plugin-d.php']],
        ];
        expect($this->activator->handle($item))->toBeTrue();
    });

    it('handle returns false for non-matching equals operator', function () {
        $item = [
            'field' => 'site_mode',
            'operator' => 'equals',
            'value' => 'staging',
            'plugins' => [['file' => 'plugin-a/plugin-a.php']],
        ];
        expect($this->activator->handle($item))->toBeFalse();
    });

    it('handle returns false for missing field', function () {
        $item = [
            'operator' => 'equals',
            'value' => 'live',
            'plugins' => [['file' => 'plugin-a/plugin-a.php']],
        ];
        expect($this->activator->handle($item))->toBeFalse();
    });

    it('handle returns false for missing plugins', function () {
        $item = [
            'field' => 'site_mode',
            'operator' => 'equals',
            'value' => 'live',
        ];
        expect($this->activator->handle($item))->toBeFalse();
    });
}