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

declare(strict_types=1);

namespace SatoriDigital\PluginActivator\Interfaces;

interface ActivatorInterface
{
    /**
     * Collect normalized items for global ordering.
     *
     * Shape per item:
     * [
     *   'type'  => 'plugin'|'filter'|'setting'|'group',
     *   'order' => int,        // default 0 if not provided
     *   'data'  => array       // original activator-specific payload
     * ]
     *
     * @return array Array of collected items.
     */
    public function collect(): array;

    /**
     * The type key this activator is responsible for.
     * Used by the controller to route items back to the correct activator.
     *
     * @return string One of: 'plugin'|'filter'|'setting'|'group'
     */
    public function get_type(): string;
}
