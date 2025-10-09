<?php
/**
 * @group mu-plugins/plugin-activator
 * @covers \SatoriDigital\PluginActivator\Activators\FilterActivator
 */

use SatoriDigital\PluginActivator\Activators\FilterActivator;

beforeEach(function () {
    $this->slug = create_dummy_plugin('dummy-filter-execution', '1.0.0');
});

afterEach(function () {
    if (is_plugin_active($this->slug)) {
        deactivate_plugins($this->slug, true);
    }

    $pluginDir = WP_PLUGIN_DIR . '/dummy-filter-execution';
    if (is_dir($pluginDir)) {
        @unlink($pluginDir . '/dummy-filter-execution.php');
        @rmdir($pluginDir);
    }
});

it('activates the plugin when the filtered hook is triggered', function () {
    $hook = 'satori_activation_test_hook';

    $config = [
        'filtered' => [
            [
                'hook'    => $hook,
                'plugins' => [ $this->slug ],
                'priority'=> 10,
            ],
        ],
    ];

    $activator = new FilterActivator($config);
    $activator->activate();

    // Trigger the hook manually
    do_action($hook);

    expect(is_plugin_active($this->slug))->toBeTrue();
});


it('does not activate the plugin when the filter returns false', function () {
    $filterName = 'satori_plugin_activation_test_false';
    add_filter($filterName, fn() => false);

    $config = [
        'plugins' => [
            [
                'file'   => $this->slug,
                'type'   => 'filter',
                'filter' => $filterName,
                'order'  => 10,
            ],
        ],
    ];

    $activator = new FilterActivator($config);
    $activator->activate();

    expect(is_plugin_active($this->slug))->toBeFalse();

    remove_all_filters($filterName);
});

it('handles missing filters gracefully', function () {
    $filterName = 'non_existent_filter';

    $config = [
        'plugins' => [
            [
                'file'   => $this->slug,
                'type'   => 'filter',
                'filter' => $filterName,
                'order'  => 10,
            ],
        ],
    ];

    $activator = new FilterActivator($config);
    $activator->activate();

    expect(is_plugin_active($this->slug))->toBeFalse();
});

it('handles filters that throw exceptions gracefully', function () {
    $filterName = 'satori_plugin_activation_test_exception';

    add_filter($filterName, function () {
        throw new \Exception('Boom!');
    });

    $config = [
        'plugins' => [
            [
                'file'   => $this->slug,
                'type'   => 'filter',
                'filter' => $filterName,
                'order'  => 10,
            ],
        ],
    ];

    // No uncaught exception should bubble up here
    $activator = new FilterActivator($config);
    $activator->activate();

    expect(is_plugin_active($this->slug))->toBeFalse();

    remove_all_filters($filterName);
});

it('handles exceptions thrown during filtered activation gracefully', function () {
    $slug = create_dummy_plugin('dummy-filter-exception', '1.0.0');

    $config = [
        'filtered' => [
            [
                'hook'    => 'satori_activation_test_hook',
                'plugins' => [ $slug ],
            ],
        ],
    ];

    $activator = new FilterActivator($config);
    $activator->activate();

    // Add a "boom" callback that runs after our activator
    add_action('satori_activation_test_hook', function () {
        throw new \Exception('Boom from filtered hook!');
    }, 20);

    // Catch the exception so the test itself doesn't fail
    try {
        do_action('satori_activation_test_hook');
    } catch (\Throwable $e) {
        expect($e->getMessage())->toBe('Boom from filtered hook!');
    }

    // Assert that our activator still did its job
    expect(is_plugin_active($slug))->toBeTrue();
});

