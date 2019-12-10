<?php namespace StudioAzura\MLSitemap\Classes;

use ApplicationException;
use System\Classes\PluginManager;

class MenuItemTypes
{
    private $types = ['all-catalog-categories','all-catalog-products'];

    public function subscribe($obEvent)
    {
        $obEvent->listen('pages.menuitem.listTypes', function () {
            $items = [];
            foreach ($this->types as $type) {
                $items[$type] = '[StudioAzura.MLSitemap] ' . trans('studioazura.mlsitemap::lang.types.' . $type);
            }
            return $items;
        });

        $obEvent->listen('pages.menuitem.getTypeInfo', function ($type) {
            if (!in_array($type, $this->types)) {
                return;
            }
            $theme = \Cms\Classes\Theme::getActiveTheme();
            $pages = \Cms\Classes\Page::listInTheme($theme, true);
            return [
                'dynamicItems' => true,
                'cmsPages' => $pages,
            ];
        });

        $obEvent->listen('pages.menuitem.resolveItem', function ($type, $item, $url, $theme) {
            return self::resolveMenuItem($type, $item, $url, $theme);
        });
    }

    public function resolveMenuItem($type, $item, $url, $theme)
    {
        if (!in_array($type, $this->types)) {
            return null;
        }

        $catalog = null;
        $classPrefix = null;
        $supportedPlugins = ['Lovata.Shopaholic', 'Offline.Mall'];
        foreach ($supportedPlugins as $catalogPlugin) {
            if (PluginManager::instance()->hasPlugin($catalogPlugin)) {
                list($author, $plugin) = explode('.', $catalogPlugin);
                $classPrefix = sprintf('\\%s\\%s', $author, $plugin);
                $catalog = $catalogPlugin;
                break;
            }
        }
        if (!$classPrefix) {
            throw new ApplicationException('You need to installone of the following catalog plugin: ' . implode(', ', $supportedPlugins));
        }

        $filter = 'active';
        if ($type == 'all-catalog-categories') {
            $class = sprintf('%s\\Models\\Category', $classPrefix);
            if ($catalog == 'Offline.Mall') {
                $filter = '';
            }
        } else if ($type == 'all-catalog-products') {
            $class = sprintf('%s\\Models\\Product', $classPrefix);
            if ($catalog == 'Offline.Mall') {
                $filter = 'published';
            }
        }

        $pageName = $item->cmsPage;
        $cmsPage = \Cms\Classes\Page::loadCached($theme, $pageName);

        $result = ['items' => []];

        if ($filter) {
            $items = $class::orderBy('name', 'ASC')->where($filter, true)->get();
        } else {
            $items = $class::orderBy('name', 'ASC')->get();
        }
        foreach ($items as $item) {
            $pageUrl = $cmsPage->url($pageName, ['slug' => $item->slug]);
            $result['items'][] =  \Utopigs\Seo\Models\Sitemap::getMenuItem($cmsPage, $item, 'slug');
        }
        return $result;
    }
}
