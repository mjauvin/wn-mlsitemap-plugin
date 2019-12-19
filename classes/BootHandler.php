<?php namespace StudioAzura\MLSitemap\Classes;

use System\Classes\PluginManager;
use RainLab\Sitemap\Controllers\Definitions;


class BootHandler
{
    public function subscribe($obEvent)
    {   
        $catalog = false;
        $manager = PluginManager::instance();
        foreach (MenuItemTypes::$supportedPlugins as $plugin) {
            if ($catalog = $manager->exists($plugin)) {
                break;
            }
        }
        $fatalError = null;
        if (!$catalog) {
            $msg = sprintf('In order to use MLSitemap, You need to install one of the following catalog plugin: (%s)', implode(' | ', MenuItemTypes::$supportedPlugins));
            $fatalError = new \ApplicationException($msg);
        }

        Definitions::extend(function($controller) use ($fatalError) {
            if ($fatalError) {
                $controller->handleError($fatalError);
            }
        });
    }
}

