<?php
/**
 * @group mu-plugins/plugin-activator
 * @coversNothing
 */
use SatoriDigital\PluginActivator\Helpers\ConfigLoader;

class ConfigLoaderTest extends WP_UnitTestCase
{
    public function test_load_reads_json_from_defined_directory_and_normalizes()
    {
        // Create a temp config directory and point constant there
        $tmp = sys_get_temp_dir() . '/pa_config_' . wp_generate_password(8, false, false);
        wp_mkdir_p($tmp);

        if (!defined('PLUGIN_ACTIVATION_CONFIG')) {
            define('PLUGIN_ACTIVATION_CONFIG', $tmp);
        }

        // Write a config JSON that matches expected schema
        $slug = create_dummy_plugin('dummy-configloader', '1.0.0');
        $config = [
            "plugins" => [
                [ "slug" => $slug, "required" => true, "version" => ">=1.0.0", "order" => 10 ],
            ]
        ];
        file_put_contents($tmp . '/mytheme.json', wp_json_encode($config));

        $loader = new ConfigLoader();
        $loaded = $loader->load('mytheme');

        $this->assertIsArray($loaded);
        $this->assertArrayHasKey('plugins', $loaded);
        $this->assertIsArray($loaded['plugins']);
        $this->assertNotEmpty($loaded['plugins']);

        $first = $loaded['plugins'][0];
        $this->assertArrayHasKey('slug', $first);
        $this->assertArrayHasKey('required', $first);
        $this->assertArrayHasKey('version', $first);
        $this->assertArrayHasKey('order', $first);
        $this->assertSame($slug, $first['slug']);
    }
}
