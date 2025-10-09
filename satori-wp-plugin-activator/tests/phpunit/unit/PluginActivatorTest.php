<?php
/**
 * @group mu-plugins/plugin-activator
 * @coversNothing
 */
use SatoriDigital\PluginActivator\Activators\PluginActivator;

class PluginActivatorTest extends WP_UnitTestCase
{
    public function test_evaluate_and_activate_required_plugin()
    {
        $slug = create_dummy_plugin('dummy-plugin-activator', '1.1.0');
        $config = [
            'plugins' => [
                [ 'slug' => $slug, 'required' => true, 'version' => '>=1.0.0', 'order' => 5 ],
            ],
        ];

        $activator = new PluginActivator($config);

        // Evaluate should plan to activate and report no missing or version issues
        $ref = new \ReflectionClass($activator);
        $method = $ref->getMethod('evaluate_plugins');
        $method->setAccessible(true);
        $report = $method->invoke($activator);

        $this->assertIsArray($report);
        $this->assertArrayHasKey('to_activate', $report);
        $this->assertArrayHasKey('missing', $report);
        $this->assertArrayHasKey('version_issues', $report);
        $this->assertContains($slug, $report['to_activate']);
        $this->assertEmpty($report['missing']);
        $this->assertEmpty($report['version_issues']);

        // Now actually activate
        $activator->activate();
        $this->assertTrue(is_plugin_active($slug));
    }
}
