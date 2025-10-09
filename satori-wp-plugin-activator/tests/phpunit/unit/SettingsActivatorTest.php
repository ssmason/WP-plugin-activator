<?php
/**
 * @group mu-plugins/plugin-activator
 */

// Define the constant ONCE globally — pointing to a shared temp base folder.
if ( ! defined( 'PLUGIN_ACTIVATION_CONFIG' ) ) {
	define( 'PLUGIN_ACTIVATION_CONFIG', sys_get_temp_dir() . '/pa_test_config_global' );
	if ( ! is_dir( PLUGIN_ACTIVATION_CONFIG ) ) {
		mkdir( PLUGIN_ACTIVATION_CONFIG, 0777, true );
	}
}

beforeEach(function () {
	// Create dummy plugin
	$this->plugin_slug = 'dummy-toggle-plugin/dummy-toggle-plugin.php';
	$plugin_dir = WP_PLUGIN_DIR . '/dummy-toggle-plugin';

	if ( ! is_dir( $plugin_dir ) ) {
		mkdir( $plugin_dir );
	}

	file_put_contents(
		$plugin_dir . '/dummy-toggle-plugin.php',
		"<?php\n/*\nPlugin Name: Dummy Toggle Plugin\n*/"
	);

	if ( is_plugin_active( $this->plugin_slug ) ) {
		deactivate_plugins( $this->plugin_slug, true );
	}

	delete_option( 'pa_enable_tools' );

	// Reset plugins.json for each test inside the already-defined config folder
	$configFile = PLUGIN_ACTIVATION_CONFIG . '/plugins.json';
	$configData = [
		'plugins' => [
			[
				'slug'     => 'dummy-toggle-plugin',
				'file'     => 'dummy-toggle-plugin/dummy-toggle-plugin.php',
				'required' => true,
			],
		],
	];
	file_put_contents( $configFile, json_encode( $configData, JSON_PRETTY_PRINT ) );
});

afterEach(function () {
	if ( is_plugin_active( $this->plugin_slug ) ) {
		deactivate_plugins( $this->plugin_slug, true );
	}

	$plugin_dir = WP_PLUGIN_DIR . '/dummy-toggle-plugin';
	if ( is_file( $plugin_dir . '/dummy-toggle-plugin.php' ) ) {
		@unlink( $plugin_dir . '/dummy-toggle-plugin.php' );
	}
	if ( is_dir( $plugin_dir ) ) {
		@rmdir( $plugin_dir );
	}

	// Clean up config file but keep folder (constant stays defined)
	$configFile = PLUGIN_ACTIVATION_CONFIG . '/plugins.json';
	if ( file_exists( $configFile ) ) {
		@unlink( $configFile );
	}
});

function include_plugin_activator_bootstrap() {
	$bootstrap_path = dirname(__DIR__, 4) . '/satori-wp-plugin-activator.php';
	if ( ! file_exists( $bootstrap_path ) ) {
		throw new Exception( "Bootstrap file not found at: {$bootstrap_path}" );
	}
	require $bootstrap_path;
	do_action( 'after_setup_theme' );
}

it('does not activate plugins when the toggle is enabled', function () {
	update_option( 'pa_enable_tools', 'on' );

	include_plugin_activator_bootstrap();

	expect( is_plugin_active( $this->plugin_slug ) )->toBeFalse();
});

it('activates plugins when the toggle is disabled', function () {
	update_option( 'pa_enable_tools', 'off' );

	include_plugin_activator_bootstrap();

	expect( is_plugin_active( $this->plugin_slug ) )->toBeTrue();
});

it('activates plugins when the toggle is missing', function () {
	delete_option( 'pa_enable_tools' );

	include_plugin_activator_bootstrap();

	expect( is_plugin_active( $this->plugin_slug ) )->toBeTrue();
});
