<?php
/**
 * @group mu-plugins/plugin-activator
 */

use SatoriDigital\PluginActivator\Activators\PluginActivator;
use SatoriDigital\PluginActivator\Helpers\ActivationUtils;

beforeEach(function () {
    // Create a dummy plugin for valid activation
    $this->valid_slug = create_dummy_plugin('valid-plugin', '1.2.0');

    // A fake slug that does not exist
    $this->missing_slug = 'missing-plugin/missing-plugin.php';

    // A dummy plugin with a lower version than required
    $this->low_version_slug = create_dummy_plugin('low-version-plugin', '1.0.0');

    $this->config = [
        'plugins' => [
            [
                'file'     => $this->valid_slug,
                'required' => true,
                'version'  => '>=1.0.0',
            ],
            [
                'file'     => $this->missing_slug,
                'required' => true,
                'version'  => '>=1.0.0',
            ],
            [
                'file'     => $this->low_version_slug,
                'required' => true,
                'version'  => '>=2.0.0',
            ],
        ],
    ];
});

afterEach(function () {
    if ( is_plugin_active( $this->valid_slug ) ) {
        deactivate_plugins( $this->valid_slug, true );
    }

    if ( is_plugin_active( $this->low_version_slug ) ) {
        deactivate_plugins( $this->low_version_slug, true );
    }
});

it('reports plugins to activate, missing, and version issues correctly', function () {
    $report = ActivationUtils::evaluate_plugins($this->config);

    expect($report)->toBeArray();
    expect($report)->toHaveKeys(['to_activate', 'missing', 'version_issues']);

    expect($report['to_activate'])->toContain($this->valid_slug);
    expect($report['missing'])->toContain($this->missing_slug);

    // Check version_issues by searching manually
    $hasVersionIssue = false;
    foreach ($report['version_issues'] as $issue) {
        if ($issue['slug'] === $this->low_version_slug) {
            $hasVersionIssue = true;
            break;
        }
    }

    expect($hasVersionIssue)->toBeTrue();
});
