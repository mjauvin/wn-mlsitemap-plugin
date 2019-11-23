<?php namespace StudioAzura\SitemapShopaholic\Classes;

class MenuItemTypes
{
    public function subscribe($obEvent)
    {
        $obEvent->listen('pages.menuitem.listTypes', function () {
            return [
                'all-catalog-categories' => Lang::get('studioazura.sitemapshopaholic::lang.types.all-catalog-categories'),
                'all-catalog-products' => Lang::get('studioazura.sitemapshopaholic::lang.types.all-catalog-products'),
            ];
        });
        $obEvent->listen('pages.menuitem.getTypeInfo', function ($type) {
            if ($type === 'all-catalog-categories' || $type === 'all-catalog-products') {
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
            $paintingsCategory = $class::find(1);
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
            if ($type === 'all-catalog-categories') {
                if (!$item->isDescendantOf($paintingsCategory) || !$item->isLeaf() || $item->isRoot())
                    continue;
            }
            $result['items'][] =  \Utopigs\Seo\Models\Sitemap::getMenuItem($cmsPage, $item, 'slug');
        }
        return $result;
    }
}
