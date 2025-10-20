<?php
/**
 * @group mu-plugins/plugin-activator
 * @coversNothing
 */

declare(strict_types=1);

use SatoriDigital\PluginActivator\Activators\PluginActivator;
use SatoriDigital\PluginActivator\Activators\FilterActivator;
use SatoriDigital\PluginActivator\Activators\SettingsActivator;
use SatoriDigital\PluginActivator\Activators\GroupActivator;
use SatoriDigital\PluginActivator\Interfaces\ActivatorInterface;

it('all activators implement ActivatorInterface and required methods', function () {
    $activators = [
        PluginActivator::class,
        FilterActivator::class,
        SettingsActivator::class,
        GroupActivator::class,
    ];
    foreach ($activators as $class) {
        $instance = new $class([]);
        expect($instance)->toBeInstanceOf(ActivatorInterface::class);
        expect(method_exists($instance, 'collect'))->toBeTrue();
        expect(method_exists($instance, 'get_type'))->toBeTrue();
    }
});