<?php namespace StudioAzura\MLSitemap;

use Event;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public $require = ['RainLab.Sitemap'];

    public function pluginDetails()
    {
        return [
            'name'        => 'Multilingual Sitemap addon',
            'description' => 'Also Adds "All Catalog Products/Categories" to SEO Sitemap item types',
            'author'      => 'StudioAzura',
            'icon'        => 'icon-sitemap'
        ];
    }

    public function boot()
    {
        Event::subscribe(\StudioAzura\MLSitemap\Classes\MenuItemTypes::class);
    }

}
