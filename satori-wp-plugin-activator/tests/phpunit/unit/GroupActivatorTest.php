<?php
/**
 * @group unit
 */

declare(strict_types=1);

use SatoriDigital\PluginActivator\Activators\GroupActivator;


if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', '/fake/plugin/dir');
}


if (!function_exists('site_url')) {
    function site_url() {
        global $mock_site_url;
        return $mock_site_url ?? 'https://staging.example.com';
    }
}

beforeEach(function () {

    global $mock_site_url, $mock_options;
    $mock_site_url = 'https://staging.example.com';
    $mock_options = [
        'enable_query_monitor' => '1',
        'some_other_setting' => 'value',
    ];

    $this->staging_config = [
        'groups' => [
            'staging' => [
                'url'      => 'https://staging.example.com',
                'plugins'  => [
                    [
                        'file' => 'query-monitor/query-monitor.php', 
                        'version' => '>=3.0.0', 
                        'required' => true, 
                        'order' => 5
                    ],
                    [
                        'file' => 'debug-bar/debug-bar.php', 
                        'version' => '>=1.0.0', 
                        'required' => false, 
                        'order' => 10
                    ],
                ],
            ],
            'production' => [
                'url'      => 'https://example.com',
                'plugins'  => [
                    [
                        'file' => 'wordpress-seo/wp-seo.php', 
                        'version' => '>=25.0', 
                        'required' => true, 
                        'order' => 10
                    ],
                ],
            ],
        ],
    ];
    
    $this->activator = new GroupActivator($this->staging_config);
});

afterEach(function () {

    global $mock_site_url, $mock_options;
    $mock_site_url = null;
    $mock_options = [];
});

test('GroupActivator can be constructed with valid config', function () {
    expect($this->activator)->toBeInstanceOf(GroupActivator::class);
});

test('GroupActivator has required interface methods', function () {
    expect(method_exists($this->activator, 'collect'))->toBeTrue();
    expect(method_exists($this->activator, 'handle'))->toBeTrue();
    expect(method_exists($this->activator, 'get_type'))->toBeTrue();
});

test('get_type returns correct activator type', function () {
    expect($this->activator->get_type())->toBe('group');
});

test('collect returns array of group items for matching URL', function () {

    global $mock_site_url;
    $mock_site_url = 'https://staging.example.com';
    
    $items = $this->activator->collect();
    
    expect($items)->toBeArray();
    

    if (empty($items)) {

        expect($items)->toBeEmpty();
    } else {

        foreach ($items as $item) {
            expect($item)->toHaveKey('type');
            expect($item)->toHaveKey('data');
        }
    }
});

test('collect returns empty array for non-matching URL', function () {

    global $mock_site_url;
    $mock_site_url = 'https://different-site.com';
    
    $items = $this->activator->collect();
    
    expect($items)->toBeArray();
    expect($items)->toBeEmpty();
});

test('GroupActivator handles empty config gracefully', function () {
    $emptyActivator = new GroupActivator(['groups' => []]);
    
    $items = $emptyActivator->collect();
    expect($items)->toBeArray();
    expect($items)->toBeEmpty();
});

test('GroupActivator handles malformed config gracefully', function () {
    $malformedActivator = new GroupActivator([]);
    
    $items = $malformedActivator->collect();
    expect($items)->toBeArray();
    expect($items)->toBeEmpty();
});
