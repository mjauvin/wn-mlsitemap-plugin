<?php namespace StudioAzura\SitemapShopaholic;

use Event;
use System\Classes\PluginBase;

/**
 * SitemapShopaholic Plugin Information File
 */
class Plugin extends PluginBase
{
    //public $require = ['Lovata.Shopaholic', 'Utopigs.Seo'];


    public function pluginDetails()
    {
        return [
            'name'        => 'Sitemap for Shopaholic',
            'description' => 'Adds "All Catalog Products/Categories" to SEO Sitemap item types',
            'author'      => 'StudioAzura',
            'icon'        => 'icon-sitemap'
        ];
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return array
     */
    public function boot()
    {
        Event::subscribe(\StudioAzura\SitemapShopaholic\Classes\MenuItemTypes::class);
    }

}
