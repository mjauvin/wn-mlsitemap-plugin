<?php namespace StudioAzura\MLSitemap;

use Event;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public $require = ['Winter.Sitemap', 'Winter.Translate'];

    public function pluginDetails()
    {
        return [
            'name'        => 'studioazura.mlsitemap::lang.plugin.name',
            'description' => 'studioazura.mlsitemap::lang.plugin.description',
            'author'      => 'StudioAzura',
            'icon'        => 'icon-sitemap'
        ];
    }

    public function boot()
    {
        Event::subscribe(Classes\MenuItemTypes::class);
    }
}
