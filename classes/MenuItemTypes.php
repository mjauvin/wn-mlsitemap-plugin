<?php namespace StudioAzura\SitemapShopaholic\Classes;

class MenuItemTypes
{
    public function subscribe($obEvent)
    {
        $obEvent->listen('pages.menuitem.listTypes', function () {
            return [
                'all-catalog-categories' => trans('studioazura.sitemapshopaholic::lang.types.all-catalog-categories'),
                'all-catalog-products' => trans('studioazura.sitemapshopaholic::lang.types.all-catalog-products'),
            ];
        });
        $obEvent->listen('pages.menuitem.getTypeInfo', function ($type) {
            if (in_array($type, ['all-catalog-categories','all-catalog-products'])) {
                $theme = \Cms\Classes\Theme::getActiveTheme();
                $pages = \Cms\Classes\Page::listInTheme($theme, true);
                return [
                    'dynamicItems' => true,
                    'cmsPages' => $pages,
                ];
            }
        });
        $obEvent->listen('pages.menuitem.resolveItem', function ($type, $item, $url, $theme) {
            return self::resolveMenuItem($type, $item, $url, $theme);
        });
    }

    public function resolveMenuItem($type, $item, $url, $theme)
    {
        if ($type === 'all-catalog-categories') {
            $class = \Lovata\Shopaholic\Models\Category::class;
        } else if ($type === 'all-catalog-products') {
            $class = \Lovata\Shopaholic\Models\Product::class;
        } else {
            return null;
        }

        $pageName = $item->cmsPage;
        $cmsPage = \Cms\Classes\Page::loadCached($theme, $pageName);

        $result = ['items' => []];

        $items = $class::orderBy('name', 'ASC')->active()->get();
        foreach ($items as $item) {
            $pageUrl = $cmsPage->url($pageName, ['slug' => $item->slug]);
            $result['items'][] =  \Utopigs\Seo\Models\Sitemap::getMenuItem($cmsPage, $item, 'slug');
        }
        return $result;
    }
}
