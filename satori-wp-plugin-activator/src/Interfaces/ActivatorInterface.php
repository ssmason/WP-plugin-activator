<?php
/**
 * Activator Interface
 *
 * Defines the contract for all plugin activators.
 * Each activator must implement an activate() method.
 *
 * @category Plugin_Activator
 * @package  SatoriDigital\PluginActivator\Interfaces
 */

declare( strict_types=1 );

namespace SatoriDigital\PluginActivator\Interfaces;

interface ActivatorInterface {

	/**
	 * Execute the activation process for this activator.
	 *
	 * @return void
	 */
	public function activate(): void;
}
