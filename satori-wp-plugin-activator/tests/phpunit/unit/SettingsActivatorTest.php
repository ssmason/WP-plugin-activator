<?php
/**
 * @group mu-plugins/plugin-activator
 * @coversNothing
 */
use SatoriDigital\PluginActivator\Activators\SettingsActivator;

class SettingsActivatorTest extends WP_UnitTestCase
{
    public function test_activation_when_option_matches_condition()
    {
        $slug = create_dummy_plugin('dummy-settings-activator', '1.0.0');

        // Ensure option is set to expected value for the rule
        update_option('pa_enable_tools', 'on');

        $config = [
            'settings' => [
                [
                    'field'    => 'pa_enable_tools',
                    'operator' => '==',
                    'value'    => 'on',
                    'plugins'  => [ $slug ],
                ]
            ]
        ];

        $activator = new SettingsActivator($config);
        $activator->activate();

        $this->assertTrue(is_plugin_active($slug), 'Plugin should be active when condition true');

        // Flip the option and assert it would NOT activate new plugins when falsey
        deactivate_plugins($slug, true);
        update_option('pa_enable_tools', 'off');
        $activator->activate();
        $this->assertFalse(is_plugin_active($slug), 'Plugin should remain inactive when condition false');
    }
}
