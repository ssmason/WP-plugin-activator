<?php
/**
 * Plugin Activator Options Page
 *
 * Provides a simple settings page with an on/off toggle to disable
 * the plugin activator entirely. This class is loaded before the
 * activator controller runs, so the toggle is always available.
 *
 * @category Plugin_Activator
 * @package  SatoriDigital\PluginActivator\Config
 * @author   Satori Digital
 * @license  GPL-2.0+
 * @link     https://satoridigital.com
 */

declare(strict_types=1);

namespace SatoriDigital\PluginActivator\Config;

/**
 * Class ActivatorOptions
 *
 * Manages the WordPress admin settings page for the Plugin Activator system.
 * Provides a toggle interface to enable/disable the entire activation system
 * and handles the storage of this preference in WordPress options.
 *
 * @package SatoriDigital\PluginActivator\Config
 * @since   1.0.0
 */
class ActivatorOptions
{
    /**
     * Option name used to store the "disable activator" toggle in the database.
     *
     * @since 1.0.0
     */
    private const OPTION_NAME = 'satori_plugin_activator_disabled';

    /**
     * Constructor.
     *
     * Hooks into WordPress to register the settings page, fields, and styles.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_options_page']);
        add_action('admin_init', [$this, 'register_settings_configuration']);
        add_action('admin_head', [$this, 'maybe_output_styles']);
    }

    /**
     * Register the "Plugin Activator" settings page under the Settings menu.
     *
     * @since 1.0.0
     */
    public function register_options_page(): void
    {
        add_options_page(
            __('Plugin Activator', 'satori-plugin-activator'),
            __('Plugin Activator', 'satori-plugin-activator'),
            'manage_options',
            'satori-plugin-activator',
            [$this, 'render_options_page']
        );
    }

    /**
     * Register all settings configuration.
     *
     * @since 1.0.0
     */
    public function register_settings_configuration(): void
    {
        $this->register_option();
        $this->register_section();
        $this->register_field();
    }

    /**
     * Register the main setting option.
     *
     * @since 1.0.0
     */
    private function register_option(): void
    {
        register_setting(
            'satori_plugin_activator_settings',
            self::OPTION_NAME,
            [
                'type'              => 'boolean',
                'sanitize_callback' => static fn($value): bool => (bool) $value,
                'default'           => false,
            ]
        );
    }

    /**
     * Register the settings section.
     *
     * @since 1.0.0
     */
    private function register_section(): void
    {
        add_settings_section(
            'satori_plugin_activator_main_section',
            __('Plugin Activator Settings', 'satori-plugin-activator'),
            '__return_false',
            'satori-plugin-activator'
        );
    }

    /**
     * Register the settings field.
     *
     * @since 1.0.0
     */
    private function register_field(): void
    {
        add_settings_field(
            'disable_activator',
            __('Disable Plugin Activator', 'satori-plugin-activator'),
            [$this, 'render_toggle_field'],
            'satori-plugin-activator',
            'satori_plugin_activator_main_section'
        );
    }

    /**
     * Render the toggle input field.
     *
     * @since 1.0.0
     */
    public function render_toggle_field(): void
    {
        $value = (bool) get_option(self::OPTION_NAME, false);
        ?>
        <label class="satori-switch">
            <input
                type="checkbox"
                name="<?php echo esc_attr(self::OPTION_NAME); ?>"
                value="1"
                <?php checked($value); ?>
            />
            <span class="satori-slider"></span>
        </label>
        <?php
        $this->render_field_description();
    }

    /**
     * Render the field description.
     *
     * @since 1.0.0
     */
    private function render_field_description(): void
    {
        echo '<p class="description">';
        esc_html_e('Turn this on to disable automatic plugin activation.', 'satori-plugin-activator');
        echo '</p>';
    }

    /**
     * Render the full options page wrapper.
     * Only allow access for users with the 'administrator' role or super-admin
     * @since 1.0.0
     */
    public function render_options_page(): void
    {
        $user = wp_get_current_user();
        $is_admin = in_array('administrator', (array) $user->roles, true);
        $is_super_admin = is_super_admin();

        if (!$is_admin && !$is_super_admin) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'satori-plugin-activator'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Plugin Activator Settings', 'satori-plugin-activator'); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('satori_plugin_activator_settings');
                do_settings_sections('satori-plugin-activator');
                submit_button();
                ?>
            </form>

            <?php
            // Render theme config JSON
            $theme_slug = (string) get_option('stylesheet', '');
            $config_dir = defined('PLUGIN_ACTIVATION_CONFIG') ? PLUGIN_ACTIVATION_CONFIG : WP_CONTENT_DIR . '/private/plugin-config';
            $config_file = trailingslashit($config_dir) . $theme_slug . '.json';
            if (file_exists($config_file)) {
                $json_data = json_decode(file_get_contents($config_file), true);
                if (is_array($json_data)) {
                    echo '<h2> Currrent '. esc_html($theme_slug) . ' Plugin Config</h2>';
                    echo '<pre style="background:#f6f6f6;padding:1em;border-radius:4px;max-height:400px;overflow:auto;">' . esc_html(json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
                } else {
                    echo '<p class="error">Invalid JSON in config file.</p>';
                }
            } else {
                echo '<p class="error">Config file not found: ' . esc_html($config_file) . '</p>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * Check if the activator is currently disabled via the option.
     *
     * @since 1.0.0
     */
    public function is_disabled(): bool
    {
        return (bool) get_option(self::OPTION_NAME, false);
    }

    /**
     * Output styles only if on the correct page.
     *
     * @since 1.0.0
     */
    public function maybe_output_styles(): void
    {
        if (!$this->is_settings_page()) {
            return;
        }

        $this->output_toggle_styles();
    }

    /**
     * Check if currently on the plugin activator settings page.
     *
     * @since 1.0.0
     */
    private function is_settings_page(): bool
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        return $screen && $screen->id === 'settings_page_satori-plugin-activator';
    }

    /**
     * Output CSS styles for the toggle switch.
     *
     * @since 1.0.0
     */
    private function output_toggle_styles(): void
    {
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
                background-color: #fff;
                transition: 0.3s;
                border-radius: 50%;
            }
            .satori-switch input:checked + .satori-slider {
                background-color: #d63638; /* WP red for disabled state */
            }
            .satori-switch input:checked + .satori-slider:before {
                transform: translateX(26px);
            }
        </style>
        <?php
    }
}
