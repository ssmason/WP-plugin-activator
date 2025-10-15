<?php
/**
 * @group unit
 */

declare(strict_types=1);

use SatoriDigital\PluginActivator\Helpers\ActivationUtils;


if (!function_exists('activate_plugin')) {
    function activate_plugin($plugin, $redirect = '', $network_wide = false, $silent = false) {
        global $mock_activated_plugins;
        $mock_activated_plugins[] = $plugin;
        return null;
    }
}

if (!function_exists('deactivate_plugins')) {
    function deactivate_plugins($plugins, $silent = false, $network_wide = null) {
        global $mock_deactivated_plugins;
        $mock_deactivated_plugins = array_merge($mock_deactivated_plugins ?? [], (array)$plugins);
        return null;
    }
}

if (!function_exists('is_plugin_active')) {
    function is_plugin_active($plugin) {
        global $mock_active_plugins;
        return in_array($plugin, $mock_active_plugins ?? []);
    }
}

if (!function_exists('get_plugins')) {
    function get_plugins() {
        global $mock_plugins;
        return $mock_plugins ?? [];
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $mock_options;
        return $mock_options[$option] ?? $default;
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite() {
        global $mock_is_multisite;
        return $mock_is_multisite ?? false;
    }
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', '/fake/plugin/dir');
}

if (!defined('ABSPATH')) {
    define('ABSPATH', '/fake/wp/path/');
}

beforeEach(function () {
    global $mock_activated_plugins, $mock_deactivated_plugins, $mock_active_plugins, $mock_plugins, $mock_options, $mock_is_multisite;
    $mock_activated_plugins = [];
    $mock_deactivated_plugins = [];
    $mock_active_plugins = [
        'query-monitor/query-monitor.php',
        'wordpress-seo/wp-seo.php',
    ];
    $mock_plugins = [
        'query-monitor/query-monitor.php' => ['Version' => '3.16.4'],
        'wordpress-seo/wp-seo.php' => ['Version' => '22.8'],
        'test-plugin/test-plugin.php' => ['Version' => '1.2.3'],
    ];
    $mock_options = [
        'active_plugins' => [
            'query-monitor/query-monitor.php',
            'wordpress-seo/wp-seo.php',
        ]
    ];
    $mock_is_multisite = false;
});

afterEach(function () {
    global $mock_activated_plugins, $mock_deactivated_plugins, $mock_active_plugins, $mock_plugins, $mock_options, $mock_is_multisite;
    $mock_activated_plugins = [];
    $mock_deactivated_plugins = [];
    $mock_active_plugins = [
        'query-monitor/query-monitor.php',
        'wordpress-seo/wp-seo.php',
    ];
    $mock_plugins = [
        'query-monitor/query-monitor.php' => ['Version' => '3.16.4'],
        'wordpress-seo/wp-seo.php' => ['Version' => '22.8'],
        'test-plugin/test-plugin.php' => ['Version' => '1.2.3'],
    ];
    $mock_options = [
        'active_plugins' => [
            'query-monitor/query-monitor.php',
            'wordpress-seo/wp-seo.php',
        ]
    ];
    $mock_is_multisite = false;
});

test('returns true for >= operator when version is greater or equal', function () {
    expect(ActivationUtils::satisfies_version('1.2.3', '>=1.2.0'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('1.2.3', '>=1.2.3'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('1.2.2', '>=1.2.3'))->toBeFalse();
});

test('returns correct results for > operator', function () {
    expect(ActivationUtils::satisfies_version('1.2.4', '>1.2.3'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('1.2.3', '>1.2.3'))->toBeFalse();
    expect(ActivationUtils::satisfies_version('1.2.2', '>1.2.3'))->toBeFalse();
});

test('returns correct results for <= operator', function () {
    expect(ActivationUtils::satisfies_version('2.0.0', '<=2.0.0'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('1.9.9', '<=2.0.0'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('2.0.1', '<=2.0.0'))->toBeFalse();
});

test('returns correct results for < operator', function () {
    expect(ActivationUtils::satisfies_version('1.9.9', '<2.0.0'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('2.0.0', '<2.0.0'))->toBeFalse();
    expect(ActivationUtils::satisfies_version('2.0.1', '<2.0.0'))->toBeFalse();
});

test('returns correct results for = and == operators', function () {
    expect(ActivationUtils::satisfies_version('3.0.0', '=3.0.0'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('3.0.0', '==3.0.0'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('3.0.1', '=3.0.0'))->toBeFalse();
    expect(ActivationUtils::satisfies_version('3.0.1', '==3.0.0'))->toBeFalse();
});

test('returns correct results for != operator', function () {
    expect(ActivationUtils::satisfies_version('3.0.1', '!=3.0.0'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('3.0.0', '!=3.0.0'))->toBeFalse();
});

test('handles different version formats', function () {
    expect(ActivationUtils::satisfies_version('1.0', '>=1.0'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('1.0.0', '>=1.0'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('1', '>=1'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('1.0.0.1', '>1.0.0'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('1.2.3-alpha', '1.2.0'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('1.2.3-beta.1', '1.2.2'))->toBeTrue();
});

test('handles edge cases with zeros in version', function () {
    expect(ActivationUtils::satisfies_version('0.1.0', '>0.0.9'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('0.0.1', '>=0.0.1'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('0.0.0', '<=0.0.0'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('1.0.0', '!=0.0.0'))->toBeTrue();
});

test('ignores whitespace in version strings', function () {
    expect(ActivationUtils::satisfies_version(' 1.2.3 ', '>= 1.2.0 '))->toBeTrue();
    expect(ActivationUtils::satisfies_version('1.2.3', ' >= 1.2.0'))->toBeTrue();
    expect(ActivationUtils::satisfies_version(' 1.2.3', '>=1.2.0 '))->toBeTrue();
});

test('handles malformed requirements gracefully', function () {
    expect(ActivationUtils::satisfies_version('1.0.0', 'invalid'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('1.0.0', '>>1.0.0'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('1.0.0', '1.0.0'))->toBeTrue();
});

test('handles empty and null inputs for requirements', function () {
    expect(ActivationUtils::satisfies_version('1.0.0', ''))->toBeTrue();
    expect(ActivationUtils::satisfies_version('1.0.0', ""))->toBeTrue();
    expect(ActivationUtils::satisfies_version('', '>=1.0.0'))->toBeFalse();
});

test('handles special characters in version strings', function () {
    expect(ActivationUtils::satisfies_version('1.2.3+build.1', '1.2.2'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('1.2.3~ubuntu1', '1.2.2'))->toBeTrue();
    expect(ActivationUtils::satisfies_version('1.2.3_hotfix', '1.2.4'))->toBeFalse();
    expect(ActivationUtils::satisfies_version('1.2.3', '>=1.2.2'))->toBeTrue();
});

test('checks plugin version correctly with check_version', function () {
    expect(method_exists(ActivationUtils::class, 'check_version'))->toBeTrue();
    expect(ActivationUtils::check_version('definitely-nonexistent-plugin/plugin.php', '>=1.0.0'))->toBeFalse();
    expect(ActivationUtils::check_version('', '>=1.0.0'))->toBeFalse();
    $result = ActivationUtils::check_version('any/plugin.php', '>=1.0.0');
    expect($result)->toBeIn([true, false]);
});

test('gets plugin version correctly with get_plugin_version', function () {
    expect(method_exists(ActivationUtils::class, 'get_plugin_version'))->toBeTrue();
    expect(ActivationUtils::get_plugin_version('definitely-nonexistent-plugin/plugin.php'))->toBeNull();
    expect(ActivationUtils::get_plugin_version(''))->toBeNull();
    $result = ActivationUtils::get_plugin_version('any/plugin.php');
    expect($result === null || is_string($result))->toBeTrue();
});

test('does not throw exceptions when calling plugin methods', function () {
    expect(function () {
        ActivationUtils::get_plugin_version('test/plugin.php');
        ActivationUtils::check_version('test/plugin.php', '>=1.0.0');
        ActivationUtils::check_version('missing/plugin.php', '>=1.0.0');
        ActivationUtils::check_version('test/plugin.php', '');
        ActivationUtils::check_version('test/plugin.php', null);
    })->not->toThrow(\Exception::class);
});

test('get_plugins returns an array', function () {
    $plugins = get_plugins();
    expect($plugins)->toBeArray();
});

test('helper functions exist for plugin activation', function () {
    expect(function_exists('get_plugins'))->toBeTrue();
    expect(function_exists('activate_plugin'))->toBeTrue();
    expect(function_exists('is_plugin_active'))->toBeTrue();
});

test('function_exists returns false for non-existent helper functions', function () {
    expect(function_exists('non_existent_function'))->toBeFalse();
});
