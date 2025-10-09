<?php
/**
 * @group mu-plugins/plugin-activator
 * @coversNothing
 */

use SatoriDigital\PluginActivator\Activators\PluginActivator;
use SatoriDigital\PluginActivator\Helpers\ActivationUtils;

beforeEach(function () {
    $this->slug = create_dummy_plugin('dummy-plugin-activator', '1.1.0');
    $this->config = [
        'plugins' => [
            [ 'file' => $this->slug, 'required' => true, 'version' => '>=1.0.0', 'order' => 5 ],
        ],
    ];
});

afterEach(function () {
    if (is_plugin_active($this->slug)) {
        deactivate_plugins($this->slug, true);
    }
});

it('evaluates required plugins correctly', function () {
    $activator = new PluginActivator($this->config);

    // New direct call to the helper
    $report = ActivationUtils::evaluate_plugins($this->config);

    expect($report)
        ->toBeArray()
        ->toHaveKeys(['to_activate', 'missing', 'version_issues']);

    expect($report['to_activate'])->toContain($this->slug);
    expect($report['missing'])->toBeEmpty();
    expect($report['version_issues'])->toBeEmpty();
});

it('activates the required plugin', function () {
    $activator = new PluginActivator($this->config);
    $activator->activate();

    expect(is_plugin_active($this->slug))->toBeTrue();
});
