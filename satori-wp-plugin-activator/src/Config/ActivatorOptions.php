<?php
/**
 * Plugin Activator Options Page
 *
 * Provides a simple settings page with an on/off toggle to disable
 * the plugin activator entirely. This class is loaded before the
 * activator controller runs, so the toggle is always available.
 *
 * @package SatoriDigital\PluginActivator\Config
 */

declare( strict_types=1 );

namespace SatoriDigital\PluginActivator\Config;

class ActivatorOptions {

    /**
     * Option name used to store the "disable activator" toggle in the database.
     *
     * @var string
     */
    private string $option_name = 'satori_plugin_activator_disabled';

    /**
     * Constructor.
     *
     * Hooks into WordPress to register the settings page, fields, and styles.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_options_page' ] );
        add_action( 'admin_init', [ $this, 'register_setting' ] );
        add_action( 'admin_head', [ $this, 'toggle_styles' ] );
    }

    /**
     * Register the "Plugin Activator" settings page under the Settings menu.
     *
     * @return void
     */
    public function register_options_page(): void {
        add_options_page(
            __( 'Plugin Activator', 'satori-plugin-activator' ),
            __( 'Plugin Activator', 'satori-plugin-activator' ),
            'manage_options',
            'satori-plugin-activator',
            [ $this, 'render_options_page' ]
        );
    }

    /**
     * Register the setting, section, and custom toggle field.
     *
     * @return void
     */
    public function register_setting(): void {
        register_setting(
            'satori_plugin_activator_settings',
            $this->option_name,
            [
                'type'              => 'boolean',
                'sanitize_callback' => fn( $value ): bool => (bool) $value,
                'default'           => false,
            ]
        );

        add_settings_section(
            'satori_plugin_activator_main_section',
            __( 'Plugin Activator Settings', 'satori-plugin-activator' ),
            '__return_false',
            'satori-plugin-activator'
        );

        add_settings_field(
            'disable_activator',
            __( 'Disable Plugin Activator', 'satori-plugin-activator' ),
            [ $this, 'render_field' ],
            'satori-plugin-activator',
            'satori_plugin_activator_main_section'
        );
    }

    /**
     * Render the toggle field for enabling/disabling the plugin activator.
     * Uses custom markup to display a modern sliding toggle.
     *
     * @return void
     */
    public function render_field(): void {
        $value = (bool) get_option( $this->option_name, false );
        ?>
        <label class="satori-switch">
            <input
                type="checkbox"
                name="<?php echo esc_attr( $this->option_name ); ?>"
                value="1"
                <?php checked( $value ); ?>
            />
            <span class="satori-slider"></span>
        </label>
        <p class="description">
            <?php esc_html_e( 'Turn this on to disable automatic plugin activation.', 'satori-plugin-activator' ); ?>
        </p>
        <?php
    }

    /**
     * Render the full options page wrapper.
     *
     * @return void
     */
    public function render_options_page(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Plugin Activator Settings', 'satori-plugin-activator' ); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'satori_plugin_activator_settings' ); ?>
                <?php do_settings_sections( 'satori-plugin-activator' ); ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Check if the activator is currently disabled via the option.
     *
     * @return bool True if the activator should be disabled, false otherwise.
     */
    public function is_disabled(): bool {
        return (bool) get_option( $this->option_name, false );
    }

    /**
     * Output custom CSS for the toggle switch, only on the settings page.
     *
     * @return void
     */
    public function toggle_styles(): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'settings_page_satori-plugin-activator' ) {
            return;
        }
        ?>
        <style>
            .satori-switch {
                position: relative;
                display: inline-block;
                width: 50px;
                height: 24px;
            }
            .satori-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            .satori-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                transition: 0.3s;
                border-radius: 34px;
            }
            .satori-slider:before {
                position: absolute;
                content: "";
                height: 18px;
                width: 18px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: 0.3s;
                border-radius: 50%;
            }
            .satori-switch input:checked + .satori-slider {
                background-color: #d63638; /* WP blue */
            }
            .satori-switch input:checked + .satori-slider:before {
                transform: translateX(26px);
            }
        </style>
        <?php
    }
}
