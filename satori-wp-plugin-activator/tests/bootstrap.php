<?php
// Load .env.testing (one shared source of truth)
require_once dirname(__DIR__) . '/vendor/autoload.php';
if (class_exists(\Dotenv\Dotenv::class)) {
    \Dotenv\Dotenv::createImmutable(dirname(__DIR__), '.env.testing')->safeLoad();
}

// Resolve wp-tests-lib + core locations (env wins, then phpunit defaults)
$tests_dir = getenv('WP_TESTS_DIR') ?: dirname(__DIR__) . '/.wp-tests/wp-tests-lib';
$core_dir  = getenv('WP_CORE_DIR')  ?: dirname(__DIR__) . '/.wp-tests/wordpress';

if ( ! file_exists($tests_dir . '/includes/functions.php') ) {
    fwrite(STDERR, "Could not find wp-tests-lib in {$tests_dir}.\n".
                   "Run: composer run install-wp-tests\n");
    exit(1);
}

if (!defined('WP_ADMIN')) define('WP_ADMIN', true);

// Theme activation
// $theme_slug = getenv('THEME_SLUG') ?: 'ct-custom';
$theme_root = realpath( dirname( dirname( __DIR__ ) ) ); // .../wp-content/themes

// Pre-load test functions to hook before WP boots
require_once $tests_dir . '/includes/functions.php';

// Point WordPress at our theme and force it active
tests_add_filter('theme_root', fn() => $theme_root);
// tests_add_filter('pre_option_template',   fn() => $theme_slug);
// tests_add_filter('pre_option_stylesheet', fn() => $theme_slug);

// (Optional) ensure directory is registered
tests_add_filter('muplugins_loaded', function() use ($theme_root) {
    if (function_exists('register_theme_directory')) {
        register_theme_directory($theme_root);
    }
});

// Finally, boot the WP test suite
require_once $tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/phpunit/helpers.php';