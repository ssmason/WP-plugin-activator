<?php
/**
 * @group mu-plugins/plugin-activator
 */

beforeEach(function () {
    $this->slug = create_dummy_plugin('dummy-plugin-toggle', '1.0.0');
});

afterEach(function () {
    if (is_plugin_active($this->slug)) {
        deactivate_plugins($this->slug, true);
    }
    delete_option('satori_plugin_activator_toggle');
});

function run_plugin_activator_bootstrap() {
    require_once dirname(__DIR__, 4) . '/satori-wp-plugin-activator.php';
    do_action('after_setup_theme');
}

it('does not activate plugins when the toggle is enabled', function () {
    update_option('satori_plugin_activator_toggle', 'on');

    run_plugin_activator_bootstrap();

    expect(is_plugin_active($this->slug))->toBeFalse();
});

it('activates plugins when the toggle is disabled', function () {
    update_option('satori_plugin_activator_toggle', 'off');

    $activator = new SatoriDigital\PluginActivator\Activators\PluginActivator([
        'plugins' => [
            [
                'file'     => $this->slug,
                'required' => true,
                'version'  => '>=1.0.0',
            ],
        ],
    ]);
    $activator->activate();

    expect(is_plugin_active($this->slug))->toBeTrue();
});

it('activates plugins when the toggle is missing', function () {
    delete_option('satori_plugin_activator_toggle');

    $activator = new SatoriDigital\PluginActivator\Activators\PluginActivator([
        'plugins' => [
            [
                'file'     => $this->slug,
                'required' => true,
                'version'  => '>=1.0.0',
            ],
        ],
    ]);
    $activator->activate();

    expect(is_plugin_active($this->slug))->toBeTrue();
});
