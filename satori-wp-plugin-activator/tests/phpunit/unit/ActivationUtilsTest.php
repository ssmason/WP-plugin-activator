<?php
/**
 * @group mu-plugins/plugin-activator
 * @coversNothing
 */

use SatoriDigital\PluginActivator\Helpers\ActivationUtils;

beforeEach(function () {
    $this->slug = create_dummy_plugin('dummy-activation-utils', '2.5.0');
});

afterEach(function () {
    if (is_plugin_active($this->slug)) {
        deactivate_plugins($this->slug, true);
    }

    $pluginDir = WP_PLUGIN_DIR . '/dummy-activation-utils';
    if (is_dir($pluginDir)) {
        @unlink($pluginDir . '/dummy-activation-utils.php');
        @rmdir($pluginDir);
    }
});

it('reads the plugin version correctly from the plugin header', function () {
    $version = ActivationUtils::get_plugin_version($this->slug);

    expect($version)->toEqual('2.5.0');
});

it('evaluates version constraints correctly', function () {
    expect(ActivationUtils::satisfies_version('2.5.0', '>=2.0.0'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('2.5.0', '==2.5.0'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('2.5.0', '>=3.0.0'))->toBeFalse();
    expect(ActivationUtils::satisfies_version('2.5.0', ''))->toBeTrue(); // no constraint
});

it('activates plugins using activate_plugins helper', function () {
    expect(is_plugin_active($this->slug))->toBeFalse();

    ActivationUtils::activate_plugins([$this->slug]);

    expect(is_plugin_active($this->slug))->toBeTrue();
});
