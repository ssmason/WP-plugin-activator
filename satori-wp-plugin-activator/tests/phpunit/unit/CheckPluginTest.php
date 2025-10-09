<?php
/**
 * @group mu-plugins/plugin-activator
 * @coversNothing
 */
use SatoriDigital\PluginActivator\Helpers\ActivationUtils;

class CheckPluginTest extends WP_UnitTestCase
{
    public function test_dummy_plugin_activation_flow()
    {
        $slug = create_dummy_plugin('dummy-check-plugin', '1.0.0');
        $this->assertFalse(is_plugin_active($slug), 'Plugin should start inactive');

        // Activate via helper
        ActivationUtils::activate_plugins([$slug]);
        $this->assertTrue(is_plugin_active($slug), 'Plugin should now be active');
    }
}
