<?php
/**
 * @group mu-plugins/plugin-activator
 * @coversNothing
 */

use SatoriDigital\PluginActivator\Activators\FilterActivator;

beforeEach(function () {
    $this->slug = create_dummy_plugin('dummy-filter-activator', '1.0.0');
    $this->hook = 'test_filter_activation_hook';

    $this->config = [
        'filtered' => [
            [
                'hook'     => $this->hook,
                'priority' => 1,
                'plugins'  => [ $this->slug ],
            ],
        ],
    ];
});

afterEach(function () {
    if (is_plugin_active($this->slug)) {
        deactivate_plugins($this->slug, true);
    }
    // Clean up hooks to avoid bleeding between tests
    remove_all_actions($this->hook);
});

it('registers a hook-based activation callback', function () {
    $activator = new FilterActivator($this->config);
    $activator->activate();

    // WordPress stores callbacks in global $wp_filter
    global $wp_filter;

    expect($wp_filter)->toHaveKey($this->hook);
    expect($wp_filter[$this->hook]->callbacks)->not->toBeEmpty();
});

it('activates plugins when the configured hook is triggered', function () {
    $activator = new FilterActivator($this->config);
    $activator->activate();

    expect(is_plugin_active($this->slug))->toBeFalse();

    do_action($this->hook);

    expect(is_plugin_active($this->slug))->toBeTrue();
});
