<?php
/**
 * Satori-Digital Plugin Activator
 *
 * @category Plugin_Loader
 * @package  SatoriDigital\PluginActivator
 */

if ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) { 
	include_once dirname( __FILE__ ) . '/vendor/autoload.php';
}

use SatoriDigital\PluginActivator\Activate; 
$activate = new Activate();
$activate->run();
