<?php namespace StudioAzura\SitemapShopaholic;

use Event;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public $require = ['Lovata.Shopaholic', 'Utopigs.Seo'];

    public function pluginDetails()
    {
        return [
            'name'        => 'Multilingual Sitemap Helper for Shopaholic',
            'description' => 'Adds "All Catalog Products/Categories" to SEO Sitemap item types',
            'author'      => 'StudioAzura',
            'icon'        => 'icon-sitemap'
        ];
    }

    public function boot()
    {
        Event::subscribe(\StudioAzura\SitemapShopaholic\Classes\MenuItemTypes::class);
    }

}
