<?php
/**
 * @group mu-plugins/plugin-activator
 * @covers \SatoriDigital\PluginActivator\Activators\FilterActivator
 */

use SatoriDigital\PluginActivator\Activators\FilterActivator;
use SatoriDigital\PluginActivator\Helpers\ActivationUtils;
 
beforeEach(function () {
    $this->slug = create_dummy_plugin('dummy-filter-execution', '1.0.0');
    

    remove_all_actions('satori_activation_test_hook');
    remove_all_filters('satori_plugin_activation_test_false');
    remove_all_filters('satori_plugin_activation_test_exception');
    remove_all_actions('satori_test_multiple_hook');
    remove_all_filters('satori_test_priority_hook');
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
    

    $testPlugins = ['dummy-filter-exception', 'dummy-filter-multiple', 'dummy-filter-priority'];
    foreach ($testPlugins as $pluginName) {
        if (is_plugin_active($pluginName . '/' . $pluginName . '.php')) {
            deactivate_plugins($pluginName . '/' . $pluginName . '.php', true);
        }
        $pluginDir = WP_PLUGIN_DIR . '/' . $pluginName;
        if (is_dir($pluginDir)) {
            @unlink($pluginDir . '/' . $pluginName . '.php');
            @rmdir($pluginDir);
        }
    }


    remove_all_actions('satori_activation_test_hook');
    remove_all_filters('satori_plugin_activation_test_false');
    remove_all_filters('satori_plugin_activation_test_exception');
    remove_all_actions('satori_test_multiple_hook');
    remove_all_filters('satori_test_priority_hook');
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
    $collected = $activator->collect();


    expect(is_plugin_active($this->slug))->toBeFalse();


    do_action($hook);

    ActivationUtils::activate_plugins($collected);

    expect(is_plugin_active($this->slug))->toBeTrue();
});

it('processes plugins with filter that returns false', function () {
    $filterName = 'satori_plugin_activation_test_false';
    add_filter($filterName, fn() => false);

    $config = [
        'filtered' => [
            [
                'hook'    => $filterName,
                'plugins' => [ $this->slug ],
            ],
        ],
    ];

    $activator = new FilterActivator($config);
    $collected = $activator->collect();


    $result = apply_filters($filterName, true);
    expect($result)->toBeFalse();
    

    expect(function () use ($collected) {
        ActivationUtils::activate_plugins($collected);
    })->not->toThrow(\Exception::class);

    expect($collected)->toBeArray();
});

it('processes plugins with missing filters', function () {
    $hook = 'non_existent_filter';

    $config = [
        'filtered' => [
            [
                'hook'    => $hook,
                'plugins' => [ $this->slug ],
            ],
        ],
    ];
    
    $activator = new FilterActivator($config);
    $collected = $activator->collect();
    

    expect(function () use ($collected) {
        ActivationUtils::activate_plugins($collected);
    })->not->toThrow(\Exception::class);
    
    expect($collected)->toBeArray();
});

it('processes plugins when filters throw exceptions', function () {
    $filterName = 'satori_plugin_activation_test_exception';

    add_filter($filterName, function () {
        throw new \Exception('Boom!');
    });

    $config = [
        'filtered' => [
            [
                'hook'    => $filterName,
                'plugins' => [ $this->slug ],
            ],
        ],
    ];

    $activator = new FilterActivator($config);
    $collected = $activator->collect();


    expect(function () use ($filterName) {
        apply_filters($filterName, true);
    })->toThrow(\Exception::class, 'Boom!');


    expect(function () use ($collected) {
        ActivationUtils::activate_plugins($collected);
    })->not->toThrow(\Exception::class);
    
    expect($collected)->toBeArray();
});

it('handles exceptions thrown during filtered activation gracefully', function () {
    $slug = create_dummy_plugin('dummy-filter-exception', '1.0.0');
    $hook = 'satori_activation_test_hook';

    $config = [
        'filtered' => [
            [
                'hook'    => $hook,
                'plugins' => [ $slug ],
            ],
        ],
    ];

    $activator = new FilterActivator($config);
    $collected = $activator->collect();


    ActivationUtils::activate_plugins($collected);


    add_action($hook, function () {
        throw new \Exception('Boom from filtered hook!');
    }, 20);

    $exceptionCaught = false;
    try {
        do_action($hook);
    } catch (\Throwable $e) {
        $exceptionCaught = true;
        expect($e->getMessage())->toBe('Boom from filtered hook!');
    }

    expect($exceptionCaught)->toBeTrue();
    expect(is_plugin_active($slug))->toBeTrue();
    

    deactivate_plugins($slug, true);
});



it('handles multiple plugins with same hook', function () {
    $slug2 = create_dummy_plugin('dummy-filter-multiple', '1.0.0');
    $hook = 'satori_test_multiple_hook';

    $config = [
        'filtered' => [
            [
                'hook'    => $hook,
                'plugins' => [ $this->slug, $slug2 ],
                'priority'=> 10,
            ],
        ],
    ];

    $activator = new FilterActivator($config);
    $collected = $activator->collect();


    expect(is_plugin_active($this->slug))->toBeFalse();
    expect(is_plugin_active($slug2))->toBeFalse();


    do_action($hook);
    ActivationUtils::activate_plugins($collected);


    expect(is_plugin_active($this->slug))->toBeTrue();
    expect(is_plugin_active($slug2))->toBeTrue();
    

    deactivate_plugins($slug2, true);
});

it('handles different priorities correctly', function () {
    $slug2 = create_dummy_plugin('dummy-filter-priority', '1.0.0');
    $hook = 'satori_test_priority_hook';
    
    $executionOrder = [];

    $config = [
        'filtered' => [
            [
                'hook'    => $hook,
                'plugins' => [ $this->slug ],
                'priority'=> 5,
            ],
            [
                'hook'    => $hook,
                'plugins' => [ $slug2 ],
                'priority'=> 15,
            ],
        ],
    ];

    $activator = new FilterActivator($config);
    $collected = $activator->collect();


    add_action($hook, function () use (&$executionOrder) {
        $executionOrder[] = 'priority_5';
    }, 5);
    
    add_action($hook, function () use (&$executionOrder) {
        $executionOrder[] = 'priority_15';
    }, 15);


    do_action($hook);
    ActivationUtils::activate_plugins($collected);


    expect($executionOrder)->toBe(['priority_5', 'priority_15']);
    expect(is_plugin_active($this->slug))->toBeTrue();
    expect(is_plugin_active($slug2))->toBeTrue();
    

    deactivate_plugins($slug2, true);
});

it('handles empty plugin list gracefully', function () {
    $hook = 'satori_activation_test_hook';

    $config = [
        'filtered' => [
            [
                'hook'    => $hook,
                'plugins' => [],
                'priority'=> 10,
            ],
        ],
    ];

    $activator = new FilterActivator($config);
    $collected = $activator->collect();


    do_action($hook);
    ActivationUtils::activate_plugins($collected);


    expect(is_plugin_active($this->slug))->toBeFalse();
    expect($collected)->toBeArray();
});

it('validates FilterActivator configuration structure', function () {

    $config = [];
    $activator = new FilterActivator($config);
    $collected = $activator->collect();
    expect($collected)->toBeArray();
    

    $config = ['filtered' => []];
    $activator = new FilterActivator($config);
    $collected = $activator->collect();
    expect($collected)->toBeArray();
    

    $config = ['filtered' => [['plugins' => [$this->slug]]]]; // Missing hook
    $activator = new FilterActivator($config);
    
    expect(function () use ($activator) {
        $activator->collect();
    })->not->toThrow(\Error::class);
});

it('handles filter hooks vs action hooks appropriately', function () {
    $filterHook = 'satori_test_filter_hook';
    $actionHook = 'satori_test_action_hook';


    add_filter($filterHook, function ($value) {
        return $value . '_filtered';
    });


    $actionFired = false;
    add_action($actionHook, function () use (&$actionFired) {
        $actionFired = true;
    });

    $config = [
        'filtered' => [
            [
                'hook'    => $filterHook,
                'plugins' => [ $this->slug ],
            ],
        ],
    ];

    $activator = new FilterActivator($config);
    $collected = $activator->collect();


    $result = apply_filters($filterHook, 'test');
    expect($result)->toBe('test_filtered');


    do_action($actionHook);
    expect($actionFired)->toBeTrue();

    ActivationUtils::activate_plugins($collected);
    expect(is_plugin_active($this->slug))->toBeTrue();
});

it('supports conditional activation based on filter return values', function () {
    $filterHook = 'satori_conditional_activation';
    

    add_filter($filterHook, function ($shouldActivate, $pluginSlug) {
        return $pluginSlug === $this->slug ? true : false;
    }, 10, 2);

    $config = [
        'filtered' => [
            [
                'hook'    => $filterHook,
                'plugins' => [ $this->slug ],
            ],
        ],
    ];

    $activator = new FilterActivator($config);
    $collected = $activator->collect();


    $shouldActivate = apply_filters($filterHook, false, $this->slug);
    expect($shouldActivate)->toBeTrue();

    if ($shouldActivate) {
        ActivationUtils::activate_plugins($collected);
    }

    expect(is_plugin_active($this->slug))->toBeTrue();
});

it('handles large number of hooks and plugins', function () {
    $hooks = [];
    $slugs = [];
    

    for ($i = 1; $i <= 5; $i++) {
        $hooks[] = "satori_test_hook_{$i}";
        $slugs[] = create_dummy_plugin("dummy-filter-test-{$i}", '1.0.0');
    }

    $config = ['filtered' => []];
    foreach ($hooks as $index => $hook) {
        $config['filtered'][] = [
            'hook'    => $hook,
            'plugins' => [ $slugs[$index] ],
            'priority'=> 10 + $index,
        ];
    }

    $activator = new FilterActivator($config);
    $collected = $activator->collect();


    foreach ($hooks as $hook) {
        do_action($hook);
    }

    ActivationUtils::activate_plugins($collected);


    foreach ($slugs as $slug) {
        expect(is_plugin_active($slug))->toBeTrue();
        deactivate_plugins($slug, true);
    }
});

it('FilterActivator class structure and methods', function () {
    expect(class_exists('SatoriDigital\PluginActivator\Activators\FilterActivator'))->toBeTrue();
    
    $config = [
        'filtered' => [
            [
                'hook'    => 'test_hook',
                'plugins' => [ $this->slug ],
            ],
        ],
    ];
    
    $activator = new FilterActivator($config);
    
    expect(method_exists($activator, 'collect'))->toBeTrue();
    expect(is_callable([$activator, 'collect']))->toBeTrue();
    
    $collected = $activator->collect();
    expect($collected)->toBeArray();
});

it('handles malformed config: non-array plugins, missing priority, extra keys', function () {
    $hook = 'satori_malformed_hook';
    // plugins as string
    $config = [
        'filtered' => [
            [
                'hook'    => $hook,
                'plugins' => $this->slug, // not array
            ],
        ],
    ];
    $activator = new FilterActivator($config);
    $activator->collect();
    expect(true)->toBeTrue();

    // missing priority
    $config = [
        'filtered' => [
            [
                'hook'    => $hook,
                'plugins' => [ $this->slug ],
            ],
        ],
    ];
    $activator = new FilterActivator($config);
    $activator->collect();
    expect(true)->toBeTrue();

    // extra keys
    $config = [
        'filtered' => [
            [
                'hook'    => $hook,
                'plugins' => [ $this->slug ],
                'priority'=> 10,
                'unexpected' => 'value',
            ],
        ],
    ];
    $activator = new FilterActivator($config);
    $activator->collect();
    expect(true)->toBeTrue();
});

it('does not error if plugin is already active before hook', function () {
    $hook = 'satori_activation_test_hook';
    $config = [
        'filtered' => [
            [
                'hook'    => $hook,
                'plugins' => [ $this->slug ],
            ],
        ],
    ];
    $activator = new FilterActivator($config);
    $collected = $activator->collect();

    // Activate plugin if not already active
    if (!is_plugin_active($this->slug)) {
        @activate_plugins($this->slug, true); // Suppress header warnings
    }

    do_action($hook);
    ActivationUtils::activate_plugins($collected);
    expect(is_plugin_active($this->slug))->toBeTrue();
});

it('handles filter returning non-boolean values', function () {
    $filterName = 'satori_non_boolean_filter';
    add_filter($filterName, fn() => 'not_a_boolean');
    $config = [
        'filtered' => [
            [
                'hook'    => $filterName,
                'plugins' => [ $this->slug ],
            ],
        ],
    ];
    $activator = new FilterActivator($config);
    $collected = $activator->collect();
    $result = apply_filters($filterName, true);
    expect($result)->toBe('not_a_boolean');
    ActivationUtils::activate_plugins($collected);
    expect(true)->toBeTrue();
});

it('handles same plugin in multiple hooks', function () {
    $hook1 = 'satori_multi_hook_1';
    $hook2 = 'satori_multi_hook_2';
    $config = [
        'filtered' => [
            [
                'hook'    => $hook1,
                'plugins' => [ $this->slug ],
            ],
            [
                'hook'    => $hook2,
                'plugins' => [ $this->slug ],
            ],
        ],
    ];
    $activator = new FilterActivator($config);
    $collected = $activator->collect();
    do_action($hook1);
    do_action($hook2);
    ActivationUtils::activate_plugins($collected);
    expect(is_plugin_active($this->slug))->toBeTrue();
});

it('handles priority edge cases', function () {
    $slug2 = create_dummy_plugin('dummy-filter-priority-edge', '1.0.0');
    $hook = 'satori_priority_edge_hook';
    $executionOrder = [];
    $config = [
        'filtered' => [
            [
                'hook'    => $hook,
                'plugins' => [ $this->slug ],
                'priority'=> -10,
            ],
            [
                'hook'    => $hook,
                'plugins' => [ $slug2 ],
                'priority'=> 0,
            ],
            [
                'hook'    => $hook,
                'plugins' => [ $slug2 ],
                'priority'=> 9999,
            ],
        ],
    ];
    $activator = new FilterActivator($config);
    $collected = $activator->collect();
    add_action($hook, function () use (&$executionOrder) {
        $executionOrder[] = 'priority_-10';
    }, -10);
    add_action($hook, function () use (&$executionOrder) {
        $executionOrder[] = 'priority_0';
    }, 0);
    add_action($hook, function () use (&$executionOrder) {
        $executionOrder[] = 'priority_9999';
    }, 9999);
    do_action($hook);
    ActivationUtils::activate_plugins($collected);
    expect($executionOrder)->toBe(['priority_-10', 'priority_0', 'priority_9999']);
    expect(is_plugin_active($this->slug))->toBeTrue();
    expect(is_plugin_active($slug2))->toBeTrue();
    deactivate_plugins($slug2, true);
});
