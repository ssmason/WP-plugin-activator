<?php
/**
 * @group mu-plugins/plugin-activator
 * @coversNothing
 */
use SatoriDigital\PluginActivator\Activators\FilterActivator;

class FilterActivatorTest extends WP_UnitTestCase
{
    public function test_activation_is_triggered_on_custom_hook()
    {
        $slug = create_dummy_plugin('dummy-filter-activator', '1.0.0');

        $config = [
            'filtered' => [
                [
                    'hook'     => 'my_test_activation_hook',
                    'priority' => 1,
                    'plugins'  => [ $slug ],
                ]
            ]
        ];

        $activator = new FilterActivator($config);
        $activator->activate();

        $this->assertFalse(is_plugin_active($slug), 'Should not be active before hook fires');

        // Trigger the hook
        do_action('my_test_activation_hook');

        $this->assertTrue(is_plugin_active($slug), 'Should be active after hook');
    }
}
