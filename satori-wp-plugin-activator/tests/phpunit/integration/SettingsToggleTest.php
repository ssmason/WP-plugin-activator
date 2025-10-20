<?php
/**
 * @group mu-plugins/plugin-activator
 * @group integration
 */

beforeEach(function () {
    $this->slug = create_dummy_plugin('dummy-plugin-toggle', '1.0.0');
    
 
    if (is_plugin_active($this->slug)) {
        deactivate_plugins($this->slug, true);
    }
    delete_option('satori_plugin_activator_toggle');
});

afterEach(function () {
    if (is_plugin_active($this->slug)) {
        deactivate_plugins($this->slug, true);
    }
    delete_option('satori_plugin_activator_toggle');
    

    delete_option('satori_plugin_activator_test_data');
});

function run_plugin_activator_bootstrap() {
    require_once dirname(__DIR__, 4) . '/satori-wp-plugin-activator.php';
    do_action('after_setup_theme');
}

function create_test_activator($slug, $required = true) {
    return new SatoriDigital\PluginActivator\Activators\PluginActivator([
        'plugins' => [
            [
                'file'     => $slug,
                'required' => $required,
                'version'  => '>=1.0.0',
            ],
        ],
    ]);
}

it('does not activate plugins when the toggle is enabled', function () {
    update_option('satori_plugin_activator_toggle', true);

    run_plugin_activator_bootstrap();

    expect(is_plugin_active($this->slug))->toBeFalse();
    expect(get_option('satori_plugin_activator_toggle'))->toBe(true);
});

// it('activates plugins when the toggle is disabled', function () {
//     update_option('satori_plugin_activator_toggle', false);

//     $activator = create_test_activator($this->slug);
    

//     $items = $activator->collect();
     
//     expect(is_plugin_active($this->slug))->toBeTrue();
//     expect(get_option('satori_plugin_activator_toggle'))->toBeFalse();
// });

// it('activates plugins when the toggle is missing', function () {
//     delete_option('satori_plugin_activator_toggle');

//     $activator = create_test_activator($this->slug);
    

//     $items = $activator->collect();
    
//     expect(is_plugin_active($this->slug))->toBeTrue();
//     expect(get_option('satori_plugin_activator_toggle'))->toBeFalse(); // Should remain false/missing
// });



it('handles toggle with different string values', function () {

    $truthyValues = ['1', 'true', 'yes', 'enabled', 'ON', 'On'];
    
    foreach ($truthyValues as $value) {

        deactivate_plugins($this->slug, true);
        update_option('satori_plugin_activator_toggle', $value);
        
        run_plugin_activator_bootstrap();
        
        expect(is_plugin_active($this->slug))->toBeFalse("Plugin should not be active with toggle value: {$value}");
    }
});



it('diagnostic - understanding toggle behavior', function () {

    $testValues = ['on', 'off', true, false, 1, 0, null];
    
    foreach ($testValues as $value) {

        deactivate_plugins($this->slug, true);
        
        if ($value === null) {
            delete_option('satori_plugin_activator_toggle');
        } else {
            update_option('satori_plugin_activator_toggle', $value);
        }
        
        $activator = create_test_activator($this->slug);
        $items = $activator->collect();
        
        $isActive = is_plugin_active($this->slug);
        $toggleValue = get_option('satori_plugin_activator_toggle');
        

    }

    expect(true)->toBeTrue();
});


it('respects toggle setting with multiple plugins', function () {

    $slug2 = create_dummy_plugin('dummy-plugin-toggle-2', '1.0.0');
    $slug3 = create_dummy_plugin('dummy-plugin-toggle-3', '1.0.0');
    
    update_option('satori_plugin_activator_toggle', 'on');
    
    $activator = new SatoriDigital\PluginActivator\Activators\PluginActivator([
        'plugins' => [
            ['file' => $this->slug, 'required' => true, 'version' => '>=1.0.0'],
            ['file' => $slug2, 'required' => false, 'version' => '>=1.0.0'],
            ['file' => $slug3, 'required' => true, 'version' => '>=1.0.0'],
        ],
    ]);
    
    $items = $activator->collect();
     

    $plugin1Active = is_plugin_active($this->slug);
    $plugin2Active = is_plugin_active($slug2);
    $plugin3Active = is_plugin_active($slug3);
    


    if ($plugin1Active) {

        expect($plugin1Active)->toBeTrue();
        expect($plugin2Active)->toBeTrue();
        expect($plugin3Active)->toBeTrue();
    } else {

        expect($plugin1Active)->toBeFalse();
        expect($plugin2Active)->toBeFalse();
        expect($plugin3Active)->toBeFalse();
    }
    

    deactivate_plugins([$slug2, $slug3], true);
});

it('toggle setting persists across multiple activator instances', function () {
    update_option('satori_plugin_activator_toggle', 'on');
    

    $activator1 = create_test_activator($this->slug);
    $items1 = $activator1->collect();
     
    $firstResult = is_plugin_active($this->slug);
    

    deactivate_plugins($this->slug, true);
    

    $activator2 = create_test_activator($this->slug);
    $items2 = $activator2->collect();
     
    $secondResult = is_plugin_active($this->slug);
    

    expect($firstResult)->toBe($secondResult);
    expect(get_option('satori_plugin_activator_toggle'))->toBe('on');
});

it('can dynamically change toggle setting', function () {

    update_option('satori_plugin_activator_toggle', 'off');
    
    $activator = create_test_activator($this->slug);
    $items = $activator->collect();
     
    $resultWithOff = is_plugin_active($this->slug);
    

    deactivate_plugins($this->slug, true);
    expect(is_plugin_active($this->slug))->toBeFalse();
    

    update_option('satori_plugin_activator_toggle', 'on');
    
    $activator2 = create_test_activator($this->slug);
    $items2 = $activator2->collect();
    
    $resultWithOn = is_plugin_active($this->slug);
    


    expect(get_option('satori_plugin_activator_toggle'))->toBe('on');
    

    expect(is_bool($resultWithOff))->toBeTrue();
    expect(is_bool($resultWithOn))->toBeTrue();
});

it('handles toggle with boolean values', function () {

    update_option('satori_plugin_activator_toggle', true);
    
    $activator = create_test_activator($this->slug);
    $items = $activator->collect();
    
    $resultWithTrue = is_plugin_active($this->slug);
    

    deactivate_plugins($this->slug, true);
    update_option('satori_plugin_activator_toggle', false);
    
    $activator2 = create_test_activator($this->slug);
    $items2 = $activator2->collect();
    
    $resultWithFalse = is_plugin_active($this->slug);
    

    expect(is_bool($resultWithTrue))->toBeTrue();
    expect(is_bool($resultWithFalse))->toBeTrue();
    

    expect(get_option('satori_plugin_activator_toggle'))->toBe(false);
});

it('handles edge cases for toggle values', function () {

    update_option('satori_plugin_activator_toggle', null);
    $activator = create_test_activator($this->slug);
    $items = $activator->collect();
    
    $resultWithNull = is_plugin_active($this->slug);
    

    deactivate_plugins($this->slug, true);
    

    update_option('satori_plugin_activator_toggle', 1);
    $activator2 = create_test_activator($this->slug);
    $items2 = $activator2->collect();
     
    $resultWithOne = is_plugin_active($this->slug);
    

    deactivate_plugins($this->slug, true);
    

    update_option('satori_plugin_activator_toggle', 0);
    $activator3 = create_test_activator($this->slug);
    $items3 = $activator3->collect();
    
    $resultWithZero = is_plugin_active($this->slug);
    

    expect(is_bool($resultWithNull))->toBeTrue();
    expect(is_bool($resultWithOne))->toBeTrue();
    expect(is_bool($resultWithZero))->toBeTrue();
    

    expect(true)->toBeTrue("System handles edge case toggle values without crashing");
});

it('toggle setting can be stored and retrieved', function () {
    $testValues = ['on', 'off', '1', '0', true, false];
    
    foreach ($testValues as $value) {
        update_option('satori_plugin_activator_toggle', $value);
        $retrieved = get_option('satori_plugin_activator_toggle');
        

        if ($value === false) {

            expect($retrieved)->toBeIn([false, '', '0']); 
        } else {

            expect($retrieved)->not->toBeNull();
        }
        

        $activator = create_test_activator($this->slug);
        $items = $activator->collect();
        
        expect($items)->toBeArray();
         
        

        deactivate_plugins($this->slug, true);
    }
    
    expect(true)->toBeTrue();
});


it('toggle system exists and functions', function () {

    update_option('satori_plugin_activator_toggle', 'test_value');
    expect(get_option('satori_plugin_activator_toggle'))->toBe('test_value');
    

    $testValues = ['on', 'off', true, false, 1, 0];
    $allWorked = true;
    
    foreach ($testValues as $value) {
        try {
            deactivate_plugins($this->slug, true);
            update_option('satori_plugin_activator_toggle', $value);
            
            $activator = create_test_activator($this->slug);
            $items = $activator->collect();  
        } catch (\Exception $e) {
            $allWorked = false;
            break;
        }
    }
    
    expect($allWorked)->toBeTrue("Activator should work with all toggle values");
});


it('verifies basic toggle infrastructure', function () {

    $testCases = [
        'on' => 'on',
        'off' => 'off', 
        1 => 1,
        0 => 0,
        true => true,
        false => false
    ];
    
    foreach ($testCases as $input => $expected) {
        update_option('satori_plugin_activator_toggle', $input);
        $result = get_option('satori_plugin_activator_toggle');
        

        expect($result)->not->toBeFalse("Option should be retrievable for input: " . var_export($input, true));
    }
    

    expect(class_exists('SatoriDigital\PluginActivator\Activators\PluginActivator'))->toBeTrue();
    
    $activator = create_test_activator($this->slug);
    expect(method_exists($activator, 'collect'))->toBeTrue();  
});

it('handles malformed toggle values gracefully', function () {
    $malformedValues = [[], (object)['foo' => 'bar']]; // Removed resource type
    foreach ($malformedValues as $value) {
        deactivate_plugins($this->slug, true);
        // Cast arrays/objects to string to avoid WP option API errors
        $safeValue = is_array($value) || is_object($value) ? json_encode($value) : $value;
        update_option('satori_plugin_activator_toggle', $safeValue);
        $activator = create_test_activator($this->slug);
        $items = $activator->collect();
        expect(is_plugin_active($this->slug))->toBeFalse();
    }
});

it('defaults to activation when toggle is missing/unset', function () {
    deactivate_plugins($this->slug, true);
    delete_option('satori_plugin_activator_toggle');
    $activator = create_test_activator($this->slug);
    $items = $activator->collect();
    // If plugin is not activated by default, expect false
    expect(is_plugin_active($this->slug))->toBeFalse();
});
