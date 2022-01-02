<?php namespace StudioAzura\MLSitemap\Classes;

use Flash;
use ApplicationException;
use System\Classes\PluginManager;
use Cms\Classes\Page;
use Cms\Classes\Theme;

class MenuItemTypes
{
    static $types = [
        'blog' => ['all-blog-categories', 'all-blog-posts'],
        'catalog' => ['all-catalog-categories', 'all-catalog-products'],
    ];

    static $supportedPlugins = ['Lovata.Shopaholic', 'OFFLINE.Mall'];

    protected $manager = null;
    protected $catalog = null;

    public function __construct()
    {
        $this->manager = PluginManager::instance();
        foreach (self::$supportedPlugins as $catalogPlugin) {
            if ($this->manager->exists($catalogPlugin)) {
                $this->catalog = $catalogPlugin;
                break;
            }
        }
    }

    public function subscribe($obEvent)
    {
	if ($this->catalog) {
            $obEvent->listen('pages.menuitem.listTypes', function () {
                $items = [];
                foreach (self::$types['catalog'] as $type) {
                    $items[$type] = '[StudioAzura.MLSitemap] ' . trans('studioazura.mlsitemap::lang.types.' . $type);
                }
                return $items;
            });
	}

        $obEvent->listen('pages.menuitem.getTypeInfo', function ($type) {
            if (!in_array($type, self::$types['catalog'])) {
                return;
            }
            $theme = Theme::getActiveTheme();
            $pages = Page::listInTheme($theme, true);
            return [
                'dynamicItems' => true,
                'cmsPages' => $pages,
            ];
        });

        $obEvent->listen('pages.menuitem.resolveItem', function ($type, $item, $url, $theme) {
            return $this->resolveMenuItem($type, $item, $url, $theme);
        });
    }

    public function resolveMenuItem($type, $item, $url, $theme)
    {
        if (in_array($type, self::$types['catalog']) && $this->catalog) {
            return $this->resolveCatalogMenuItems($type, $item, $url, $theme);
        } else if (in_array($type, self::$types['blog'])) {
            return $this->resolveBlogMenuItems($type, $item, $url, $theme);
        } else {
            return null;
        }
    }

    public function resolveCatalogMenuItems($type, $item, $url, $theme)
    {
        if (!(in_array($type, self::$types['catalog']))) {
            return null;
        }

	list($author, $plugin) = explode('.', $this->catalog);
	$classPrefix = sprintf('\\%s\\%s', $author, $plugin);

        if (!($classPrefix && ($pageName = $item->cmsPage))) {
            return null;
        }

        $cmsPage = Page::loadCached($theme, $pageName);

        $result = ['items' => []];

        $filter = 'active';
        if ($type == 'all-catalog-categories') {
            $class = sprintf('%s\\Models\\Category', $classPrefix);
            if ($catalog == 'OFFLINE.Mall') {
                $filter = '';
            }
        } else if ($type == 'all-catalog-products') {
            $class = sprintf('%s\\Models\\Product', $classPrefix);
            if ($catalog == 'OFFLINE.Mall') {
                $filter = 'published';
            }
        }

        $query = $class::orderBy('name', 'ASC');
        if ($filter) {
            $query = $query->where($filter, true);
        }
        foreach ($query->get() as $item) {
            $result['items'][] =  Definition::getMenuItem($cmsPage, $item, 'slug');
        }
        return $result['items'];
    }

    public function resolveBlogMenuItems($type, $item, $url, $theme)
    {
        if (!(in_array($type, self::$types['blog']) && $this->manager->exists('RainLab.Blog'))) {
            return null;
        }

        if (!($pageName = $item->cmsPage)) {
            return null;
        }

        if (!$cmsPage = Page::loadCached($theme, $pageName)) {
            return null;
        }

        $result = ['items' => []];

        $filter = '';
        $classPrefix = '\\RainLab\\Blog';
        if ($type == 'all-blog-categories') {
            $class = sprintf('%s\\Models\\Category', $classPrefix);
            $query = $class::orderBy('name', 'ASC');
        } else if ($type == 'all-blog-posts') {
            $class = sprintf('%s\\Models\\Post', $classPrefix);
            $query = $class::isPublished()->orderBy('title', 'ASC');
        }

        foreach ($query->get() as $item) {
            $result['items'][] =  Definition::getMenuItem($cmsPage, $item, 'slug');
        }
        return $result['items'];
    }
}
