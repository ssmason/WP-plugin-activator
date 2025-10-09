<?php
/**
 * @group mu-plugins/plugin-activator
 * @coversNothing
 */
use SatoriDigital\PluginActivator\Helpers\ActivationUtils;

class ActivationUtilsTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        // Create dummy plugin with a known version
        $this->plugin_slug = create_dummy_plugin('dummy-activation-utils', '2.5.0');
    }

    public function tearDown(): void {
        parent::tearDown();
        // Best-effort cleanup: deactivate and remove
        if ( is_plugin_active($this->plugin_slug) ) {
            deactivate_plugins($this->plugin_slug, true);
        }
        $dir = WP_PLUGIN_DIR . '/dummy-activation-utils';
        if ( file_exists($dir) ) {
            // remove file and dir
            @unlink($dir . '/dummy-activation-utils.php');
            @rmdir($dir);
        }
    }

    public function test_plugin_version_reads_from_header(): void {
        $ver = ActivationUtils::plugin_version($this->plugin_slug);
        $this->assertSame('2.5.0', $ver);
    }

    public function test_satisfies_version_constraints(): void {
        $this->assertTrue(ActivationUtils::satisfies_version('2.5.0', '>=2.0.0'));
        $this->assertTrue(ActivationUtils::satisfies_version('2.5.0', '==2.5.0'));
        $this->assertFalse(ActivationUtils::satisfies_version('2.5.0', '>=3.0.0'));
        $this->assertTrue(ActivationUtils::satisfies_version('2.5.0', '')); // empty => no constraint
    }

    public function test_activate_plugins_activates_list(): void {
        $this->assertFalse(is_plugin_active($this->plugin_slug));
        ActivationUtils::activate_plugins([ $this->plugin_slug ]);
        $this->assertTrue(is_plugin_active($this->plugin_slug));
    }
}
