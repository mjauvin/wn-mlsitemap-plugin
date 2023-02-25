<?php namespace StudioAzura\MLSitemap\Classes;

use ApplicationException;
use Flash;
use Url;

use Cms\Classes\Page;
use Cms\Classes\Theme;
use System\Classes\PluginManager;

use Winter\Storm\Router\Router;

use Winter\Translate\Models\Locale;
use Winter\Translate\Classes\Translator;

class MenuItemTypes
{
    static $types = [
        'blog' => ['azura-all-blog-categories', 'azura-all-blog-posts'],
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
        $obEvent->listen('pages.menuitem.listTypes', function () {
            $items = [];
            foreach (self::$types['blog'] as $type) {
                $items[$type] = '[StudioAzura.MLSitemap] ' . trans('studioazura.mlsitemap::lang.types.' . $type);
            }
            if ($this->catalog) {
                foreach (self::$types['catalog'] as $type) {
                    $items[$type] = '[StudioAzura.MLSitemap] ' . trans('studioazura.mlsitemap::lang.types.' . $type);
                }
            }
            return $items;
        }, 500);

        $obEvent->listen('pages.menuitem.getTypeInfo', function ($type) {
            if (!in_array($type, array_merge(self::$types['catalog'], self::$types['blog']))) {
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
            if ($this->catalog == 'OFFLINE.Mall') {
                $filter = '';
            }
        } else if ($type == 'all-catalog-products') {
            $class = sprintf('%s\\Models\\Product', $classPrefix);
            if ($this->catalog == 'OFFLINE.Mall') {
                $filter = 'published';
            }
        }

        $query = $class::orderBy('name', 'ASC');
        if ($filter) {
            $query = $query->where($filter, true);
        }
        foreach ($query->get() as $item) {
            $result['items'][] =  self::getMenuItem($cmsPage, $item, 'slug', $url);
        }
        return $result;
    }

    public function resolveBlogMenuItems($type, $item, $url, $theme)
    {
        if (!(in_array($type, self::$types['blog']) && $this->manager->exists('Winter.Blog'))) {
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
        $classPrefix = '\\Winter\\Blog';
        if ($type == 'azura-all-blog-categories') {
            $class = sprintf('%s\\Models\\Category', $classPrefix);
            $query = $class::orderBy('name', 'ASC');
        } else if ($type == 'azura-all-blog-posts') {
            $class = sprintf('%s\\Models\\Post', $classPrefix);
            $query = $class::isPublished()->orderBy('title', 'ASC');
        }

        foreach ($query->get() as $item) {
            $result['items'][] =  self::getMenuItem($cmsPage, $item, 'slug', $url);
        }
        return $result;
    }

    public static function getMenuItem($page, $menuItem, $paramName, $url, $locale = null)
    {
        $result = [];

        $locale = $locale ?: Locale::getDefault()->code;
        $pageUrl = self::getPageLocaleUrl($page, $menuItem, $locale, [$paramName => 'slug']);

        #$alternateLocales = array_keys(Locale::listEnabled());

        #if (count($alternateLocales) > 1) {
        #    foreach ($alternateLocales as $locale) {
        #        $result['alternate_locale_urls'][$locale] = self::getPageLocaleUrl($page, $menuItem, $locale, [$paramName => 'slug']);
        #    }
        #}

        $result['title'] = $menuItem->name;
        $result['url'] = $pageUrl;
        $result['isActive'] = $pageUrl == $url;
        $result['mtime'] = $menuItem->updated_at;

        return $result;
    }

    /**
     * Returns the localized URL of a page, translating the page params.
     * @param \Cms\Classes\Page $page
     * @param Model $item Object
     * @param string $locale Code of the locale
     * @param array $paramMap Array containing the equivalence between page parameters and model attributes ['slug' => 'slug']
     * @return string Returns an string with the localized page url
     */
    protected static function getPageLocaleUrl($page, $item, $locale, $paramMap)
    {
        $translator = Translator::instance();

        if ($page->hasTranslatablePageUrl($locale)) {
            $page->rewriteTranslatablePageUrl($locale);
            $item->lang($locale);
        }

        $params = [];
        foreach ($paramMap as $paramName => $fieldName) {
            $params[$paramName] = $item->$fieldName ?? $item->url ?? null;
        }

        $url = $translator->getPathInLocale($page->url, $locale);
        $url = (new Router)->urlFromPattern($url, $params);
        $url = Url::to($url);

        return $url;
    }
}
