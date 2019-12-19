<?php namespace StudioAzura\MLSitemap\Classes;

use ApplicationException;
use System\Classes\PluginManager;
use Cms\Classes\Page;
use Cms\Classes\Theme;

class MenuItemTypes
{
    private $types = [
        'blog' => ['all-blog-categories', 'all-blog-posts'],
        'catalog' => ['all-catalog-categories', 'all-catalog-products'],
    ];

    public function subscribe($obEvent)
    {
        $obEvent->listen('pages.menuitem.listTypes', function () {
            $items = [];
            foreach ($this->types['catalog'] as $type) {
                $items[$type] = '[StudioAzura.MLSitemap] ' . trans('studioazura.mlsitemap::lang.types.' . $type);
            }
            return $items;
        });

        $obEvent->listen('pages.menuitem.getTypeInfo', function ($type) {
            if (!in_array($type, $this->types)) {
                return;
            }
            $theme = Theme::getActiveTheme();
            $pages = Page::listInTheme($theme, true);
            return [
                'dynamicItems' => true,
                'cmsPages' => $pages,
            ];
        });

        $obEvent->listen('studioazura.mlsitemap.resolveItem', function ($type, $item, $url, $theme) {
            return self::resolveMenuItem($type, $item, $url, $theme);
        });
    }

    protected function resolveMenuItem($type, $item, $url, $theme)
    {
        if (in_array($type, $this->types['catalog'])) {
            return self::resolveCatalogMenuItems($type, $item, $url, $theme);
        } else if (in_array($type, $this->types['blog'])) {
            return self::resolveBlogMenuItems($type, $item, $url, $theme);
        } else {
            return null;
        }
    }

    protected function resolveCatalogMenuItems($type, $item, $url, $theme)
    {
        if (!in_array($type, $this->types['catalog'])) {
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

        $pageName = $item->cmsPage;
        $cmsPage = Page::loadCached($theme, $pageName);

        $result = ['items' => []];

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

        $query = $class::orderBy('name', 'ASC');
        if ($filter) {
            $query = $query->where($filter, true);
        }
        foreach ($query->get() as $item) {
            $pageUrl = $cmsPage->url($pageName, ['slug' => $item->slug]);
            $result['items'][] =  \StudioAzura\MlSitemap\Classes\Definition::getMenuItem($cmsPage, $item, 'slug');
        }
        return $result;
    }

    protected function resolveBlogMenuItems($type, $item, $url, $theme)
    {
        $plugin = PluginManager::instance()->findByIdentifier('RainLab.Blog');
        if (!(in_array($type, $this->types['blog']) && $plugin && !$plugin->disabled)) {
            return null;
        }

        $pageName = $item->cmsPage;
        $cmsPage = Page::loadCached($theme, $pageName);

        $result = ['items' => []];

        $filter = '';
        $classPrefix = '\\Rainlab\\Blog';
        if ($type == 'all-blog-categories') {
            $class = sprintf('%s\\Models\\Category', $classPrefix);
            $query = $class::orderBy('name', 'ASC');
        } else if ($type == 'all-blog-posts') {
            $class = sprintf('%s\\Models\\Post', $classPrefix);
            $query = $class::isPublished()->orderBy('title', 'ASC');
        }

        foreach ($query->get() as $item) {
            $pageUrl = $cmsPage->url($pageName, ['slug' => $item->slug]);
            $result['items'][] =  \StudioAzura\MlSitemap\Classes\Definition::getMenuItem($cmsPage, $item, 'slug');
        }
        return $result;
    }
}
