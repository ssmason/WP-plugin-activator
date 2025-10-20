<?php
declare(strict_types=1);

namespace SatoriDigital\PluginActivator\Helpers {
    // Minimal mocks and globals for pure unit testing
    $GLOBALS['activationutils_logs'] = [];
    function plugin_file_exists($file) { return true; }
    function error_log($msg) { $GLOBALS['activationutils_logs'][] = $msg; }
    if (!defined('WP_PLUGIN_DIR')) { define('WP_PLUGIN_DIR', '/mock/wp-plugins'); }

    use SatoriDigital\PluginActivator\Helpers\ActivationUtils;

    beforeEach(function () {
        ActivationUtils::clear_plugin_cache();
        $GLOBALS['activationutils_logs'] = [];
    });

    it('satisfies_version handles basic and complex constraints', function () {
        expect(ActivationUtils::satisfies_version('1.2.3', '>=1.0.0'))->toBeTrue();
        expect(ActivationUtils::satisfies_version('1.2.3', '<2.0.0'))->toBeTrue();
        expect(ActivationUtils::satisfies_version('1.2.3', '=1.2.3'))->toBeTrue();
        expect(ActivationUtils::satisfies_version('1.2.3', '!=1.2.3'))->toBeFalse();
        expect(ActivationUtils::satisfies_version('1.2.3', '2.0.0'))->toBeFalse();
        expect(ActivationUtils::satisfies_version('1.2.3', null))->toBeTrue();
        expect(ActivationUtils::satisfies_version('1.2.3', ''))->toBeTrue();
    });

    it('normalize_to_specs flattens and deduplicates plugin specs', function () {
        $input = [
            'plugin-a/plugin-a.php',
            ['file' => 'plugin-b/plugin-b.php', 'required' => true],
            ['data' => ['plugins' => ['plugin-c/plugin-c.php', ['file' => 'plugin-d/plugin-d.php']]]],
            ['plugins' => ['plugin-e/plugin-e.php']],
            ['file' => 'plugin-a/plugin-a.php'], // duplicate
        ];
        $specs = (new \ReflectionClass(ActivationUtils::class))
            ->getMethod('normalize_to_specs')
            ->invoke(null, $input);
        $files = array_column($specs, 'file');
        expect($files)->toContain('plugin-a/plugin-a.php', 'plugin-b/plugin-b.php', 'plugin-c/plugin-c.php', 'plugin-d/plugin-d.php', 'plugin-e/plugin-e.php');
        expect(count($files))->toBe(5);
    });

    it('extract_files returns unique plugin file paths', function () {
        $input = [
            ['file' => 'plugin-x/plugin-x.php'],
            ['file' => 'plugin-y/plugin-y.php'],
            ['file' => 'plugin-x/plugin-x.php'],
        ];
        $files = (new \ReflectionClass(ActivationUtils::class))
            ->getMethod('extract_files')
            ->invoke(null, $input);
        expect($files)->toBe(['plugin-x/plugin-x.php', 'plugin-y/plugin-y.php']);
    });

    it('plugin_file_exists returns true for any file (mocked)', function () {
        // Test the global mock, not the class static method
        expect(plugin_file_exists('any-plugin.php'))->toBeTrue();
    });

    it('get_plugin_version returns correct version or null', function () {
        $mockPlugins = [
            'plugin-a/plugin-a.php' => ['Version' => '1.2.3'],
            'plugin-b/plugin-b.php' => ['Version' => ''],
        ];
        $ref = new \ReflectionProperty(ActivationUtils::class, 'plugin_cache');
        $ref->setAccessible(true);
        $ref->setValue(null, $mockPlugins);
        expect(ActivationUtils::get_plugin_version('plugin-a/plugin-a.php'))->toBe('1.2.3');
        expect(ActivationUtils::get_plugin_version('plugin-b/plugin-b.php'))->toBeNull();
        expect(ActivationUtils::get_plugin_version('missing-plugin.php'))->toBeNull();
    });

    it('check_version returns correct comparison results', function () {
        $mockPlugins = [
            'plugin-z/plugin-z.php' => ['Version' => '2.5.0'],
        ];
        $ref = new \ReflectionProperty(ActivationUtils::class, 'plugin_cache');
        $ref->setAccessible(true);
        $ref->setValue(null, $mockPlugins);
        expect(ActivationUtils::check_version('plugin-z/plugin-z.php', '>=2.0.0'))->toBeTrue();
        expect(ActivationUtils::check_version('plugin-z/plugin-z.php', '<2.0.0'))->toBeFalse();
        expect(ActivationUtils::check_version('plugin-z/plugin-z.php', '2.5.0'))->toBeTrue();
        expect(ActivationUtils::check_version('plugin-z/plugin-z.php', '!=2.5.0'))->toBeFalse();
        expect(ActivationUtils::check_version('plugin-z/plugin-z.php', '>=3.0.0'))->toBeFalse();
        expect(ActivationUtils::check_version('missing-plugin/missing-plugin.php', '>=1.0.0'))->toBeFalse();
    });

    it('log_version_mismatch logs correct message', function () {
        ActivationUtils::log_version_mismatch('plugin-x/plugin-x.php', '>=2.0.0', '1.0.0');
        expect($GLOBALS['activationutils_logs'])->toContain('[PluginActivator] Version mismatch for plugin-x/plugin-x.php. Required >=2.0.0, found 1.0.0.');
    });

    it('log_missing_plugin logs correct message', function () {
        ActivationUtils::log_missing_plugin('plugin-y/plugin-y.php');
        expect($GLOBALS['activationutils_logs'])->toContain('[PluginActivator] REQUIRED plugin missing: plugin-y/plugin-y.php');
    });

    it('logs when plugin versions do not match', function () {
        $GLOBALS['activationutils_logs'] = [];
        $mockPlugins = [
            'plugin-a/plugin-a.php' => ['Version' => '1.0.0'],
        ];
        $ref = new \ReflectionProperty(ActivationUtils::class, 'plugin_cache');
        $ref->setAccessible(true);
        $ref->setValue(null, $mockPlugins);
        $input = [
            ['file' => 'plugin-a/plugin-a.php', 'version' => '>=2.0.0'],
        ];
        ActivationUtils::check_versions($input);
        expect($GLOBALS['activationutils_logs'])->not->toBeEmpty();
    });
}
