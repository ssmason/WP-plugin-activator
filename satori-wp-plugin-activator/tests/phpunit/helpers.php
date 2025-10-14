<?php
function create_dummy_plugin($slug = 'dummy-plugin', $version = '1.2.3') {
    $base = WP_PLUGIN_DIR . '/' . $slug;
    if (!file_exists($base)) {
        wp_mkdir_p($base);
    }
    $file = $base . '/' . $slug . '.php';
    if (!file_exists($file)) {
        $contents = "<?php\n/*
        Plugin Name: Dummy Plugin (" . $slug . ")
        Description: Dummy for tests
        Version: " . $version . "
        Author: Test
        */\n";
        file_put_contents($file, $contents);
    }

    if (function_exists('wp_clean_plugins_cache')) {
        wp_clean_plugins_cache();
    }
    return $slug . '/' . $slug . '.php';
}
