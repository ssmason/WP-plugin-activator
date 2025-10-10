<?php
declare(strict_types=1);

namespace SatoriDigital\PluginActivator\Helpers;

use function activate_plugin;
use function deactivate_plugins;
use function get_option;
use function get_plugins;
use function is_multisite;
use function is_plugin_active;

final class ActivationUtils
{
    /**
     * Ensure WP plugin API is loaded (needed for activate_plugin(), get_plugins(), etc.).
     */
    private static function ensureWpPluginApi(): void
    {
        if (!\function_exists('activate_plugin')) {
            require_once \ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    /**
     * Normalize any input (strings, specs, collected items) into a flat list of plugin specs.
     */
    private static function normalizeToSpecs(array $input): array
    {
        $specs = [];

        $appendSpec = static function (array $maybe) use (&$specs): void {
            if (empty($maybe['file'])) {
                return;
            }
            $specs[] = [
                'file'     => $maybe['file'],
                'required' => $maybe['required'] ?? false,
                'version'  => $maybe['version'] ?? null,
                'defer'    => $maybe['defer'] ?? false,
            ];
        };

        foreach ($input as $item) {
            if (\is_string($item)) {
                $appendSpec(['file' => $item]);
                continue;
            }

            if (!\is_array($item)) {
                continue;
            }

            if (!empty($item['file'])) {
                $appendSpec($item);
                continue;
            }

            if (!empty($item['data']) && \is_array($item['data'])) {
                $d = $item['data'];

                if (!empty($d['file'])) {
                    $appendSpec($d);
                    continue;
                }

                if (!empty($d['plugins']) && \is_array($d['plugins'])) {
                    foreach ($d['plugins'] as $p) {
                        if (\is_string($p)) {
                            $appendSpec(['file' => $p]);
                        } elseif (\is_array($p)) {
                            $appendSpec($p);
                        }
                    }
                }
            }

            if (!empty($item['plugins']) && \is_array($item['plugins'])) {
                foreach ($item['plugins'] as $p) {
                    if (\is_string($p)) {
                        $appendSpec(['file' => $p]);
                    } elseif (\is_array($p)) {
                        $appendSpec($p);
                    }
                }
            }
        }

        $dedup = [];
        foreach ($specs as $s) {
            $dedup[$s['file']] = $s;
        }

        return \array_values($dedup);
    }

    /**
     * Extract just the plugin file paths from any mixed input.
     */
    private static function extractFiles(array $input): array
    {
        $files = [];
        $specs = self::normalizeToSpecs($input);

        foreach ($specs as $s) {
            $files[] = $s['file'];
        }

        return \array_values(\array_unique(\array_filter($files)));
    }

    /**
     * True if the plugin file exists under WP_PLUGIN_DIR.
     */
    public static function plugin_file_exists(string $file): bool
    {
        return \file_exists(\WP_PLUGIN_DIR . '/' . $file);
    }

    /**
     * Return installed version for a plugin file, or null if not found.
     */
    public static function get_plugin_version(string $file): ?string
    {
        self::ensureWpPluginApi();
        $all = get_plugins();
        if (!isset($all[$file]['Version'])) {
            return null;
        }
        $ver = (string) $all[$file]['Version'];
        return $ver !== '' ? $ver : null;
    }

    /**
     * Compare version using an expression like '>=2.1.0', '<1.0', '=3.0', etc.
     */
    public static function satisfies_version(string $current, ?string $requiredExpr): bool
    {
        if ($requiredExpr === null || $requiredExpr === '') {
            return true;
        }
        if (!\preg_match('/^(>=|<=|>|<|=|==|!=)?\s*([0-9][0-9\.]*)$/', $requiredExpr, $m)) {
            return \version_compare($current, $requiredExpr, '>=');
        }
        $op  = $m[1] ?: '>=';
        $ver = $m[2];
        return \version_compare($current, $ver, $op);
    }

    /**
     * Check versions without activation.
     */
    public static function check_versions(array $input): void
    {
        $specs = self::normalizeToSpecs($input);
        foreach ($specs as $s) {
            $file = $s['file'];
            $req  = $s['version'] ?? null;
            if (!$req) {
                continue;
            }
            $current = self::get_plugin_version($file);
            if ($current !== null && !self::satisfies_version($current, $req)) {
                \error_log(\sprintf('[PluginActivator] Version mismatch: %s requires %s, found %s.', $file, $req, $current));
            }
        }
    }

    /**
     * Strict activation with version enforcement.
     */
    public static function activate_plugins(array $input): void
    {
        self::ensureWpPluginApi();

        $specs    = self::normalizeToSpecs($input);
        $deferred = [];

        foreach ($specs as $s) {
            $file     = $s['file'];
            $required = (bool)($s['required'] ?? false);
            $expr     = $s['version']  ?? null;
            $defer    = (bool)($s['defer']    ?? false);

            if (!self::plugin_file_exists($file)) {
                \error_log(\sprintf('[PluginActivator] Plugin file not found: %s', $file));
                if ($required) {
                    self::log_missing_plugin($file);
                }
                continue;
            }

            if ($expr) {
                $current = self::get_plugin_version($file);
                    $file, $current ?? 'null', $expr));

                if ($current === null || !self::satisfies_version($current, $expr)) {
                        $file, $expr, $current ?? 'unknown'));
                    // If active, deactivate it
                    if (is_plugin_active($file)) {
                        deactivate_plugins([$file], false, is_multisite());
                        \error_log(\sprintf('[PluginActivator] Deactivated due to version mismatch: %s', $file));
                    }
                    continue; // Skip activation entirely
                }
            }

            if ($defer) {
                $deferred[] = $file;
                continue;
            }

            if (!is_plugin_active($file)) {
                activate_plugin($file, '', false, true);
                \error_log(\sprintf('[PluginActivator] Plugin activated: %s', $file));
            }
        }

        foreach ($deferred as $file) {
            if (!is_plugin_active($file)) {
                activate_plugin($file, '', false, true);
                \error_log(\sprintf('[PluginActivator] Plugin activated (deferred): %s', $file));
            }
        }
    }

    /**
     * Deactivate plugins NOT present in the provided input.
     */
    public static function deactivate_unlisted_plugins(array $input): void
    {
        self::ensureWpPluginApi();

        $allowedFiles = self::extractFiles($input);
        $active       = (array) get_option('active_plugins', []);

        $toDeactivate = \array_values(\array_diff($active, $allowedFiles));

        if (empty($toDeactivate)) {
            return;
        }

        deactivate_plugins($toDeactivate, false, is_multisite());

        \error_log(\sprintf(
            '[PluginActivator] Deactivated unlisted plugins: %s',
            \implode(', ', $toDeactivate)
        ));
    }

    public static function check_version(string $file, string $constraint): bool
    {
        self::ensureWpPluginApi();

        $plugins = get_plugins();
        if (!isset($plugins[$file]['Version'])) {
            return false;
        }

        $installed = $plugins[$file]['Version'];

        if (preg_match('/^(>=|<=|>|<|==|!=)\s*(.+)$/', $constraint, $matches)) {
            return version_compare($installed, $matches[2], $matches[1]);
        }

        return version_compare($installed, $constraint, '>=');
    }

    public static function is_plugin_file_missing(string $plugin_file): bool
    {
        return ! self::plugin_file_exists($plugin_file);
    }

    public static function log_version_mismatch(string $plugin_file, string $requiredExpr, ?string $current = null): void
    {
        if ($current === null) {
            $current = self::get_plugin_version($plugin_file) ?? 'unknown';
        }
        error_log(
            sprintf('[PluginActivator] Version mismatch for %s. Required %s, found %s.', $plugin_file, $requiredExpr, $current)
        );
    }

    public static function log_missing_plugin(string $plugin_file): void
    {
        error_log(sprintf('[PluginActivator] REQUIRED plugin missing: %s', $plugin_file));
    }
}
