<?php
/**
 * @group mu-plugins/plugin-activator
 */

use SatoriDigital\PluginActivator\Helpers\ActivationUtils;

beforeEach(function () {
    // Create a dummy plugin folder & file
    $this->dummy_slug = 'dummy-activation-utils/dummy-activation-utils.php';
    $this->dummy_dir  = WP_PLUGIN_DIR . '/dummy-activation-utils';

    if ( ! is_dir( $this->dummy_dir ) ) {
        mkdir( $this->dummy_dir );
    }

    file_put_contents(
        $this->dummy_dir . '/dummy-activation-utils.php',
        "<?php\n/*\nPlugin Name: Dummy Activation Utils\nVersion: 1.2.3\n*/"
    );
});

afterEach(function () {
    // Clean up dummy file
    if ( file_exists( $this->dummy_dir . '/dummy-activation-utils.php' ) ) {
        unlink( $this->dummy_dir . '/dummy-activation-utils.php' );
    }
    if ( is_dir( $this->dummy_dir ) ) {
        rmdir( $this->dummy_dir );
    }
});

it('reads the plugin version correctly from the plugin header', function () {
    $abs_path = WP_PLUGIN_DIR . '/' . $this->dummy_slug;
    $version = ActivationUtils::get_plugin_version( $abs_path );

    expect($version)->toBe('1.2.3');
});
