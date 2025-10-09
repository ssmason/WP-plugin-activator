<?php
declare( strict_types=1 );

namespace SatoriDigital\PluginActivator\Activators;

class GroupActivator {

	/**
	 * Merge group plugins into config based on current site URL.
	 *
	 * @param array $config Original config.
	 * @return array Merged config with matched group plugins.
	 */
	public static function merge_group_plugins( array $config ): array {
		if ( empty( $config['groups'] ) || ! is_array( $config['groups'] ) ) {
			return $config;
		}

		$current_url = home_url();

		foreach ( $config['groups'] as $group_name => $group ) {
			$group_url     = $group['url'] ?? '';
			$group_plugins = $group['plugins'] ?? [];

			if ( ! empty( $group_url ) && $group_url === $current_url ) {
				error_log( sprintf(
					'[GroupActivator] Merging %d plugins from group "%s" for URL "%s"',
					count( $group_plugins ),
					$group_name,
					$current_url
				) );

				$config['plugins'] = array_merge(
					$config['plugins'] ?? [],
					$group_plugins
				);

				break; // Only match one group
			}
		}

		return $config;
	}
}
