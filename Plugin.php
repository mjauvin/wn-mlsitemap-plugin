<?php namespace StudioAzura\MLSitemap;

use Event;
use Request;
use Url;

use Cms\Classes\Page;
use System\Classes\PluginBase;

use Winter\Storm\Router\Router;

use Winter\Translate\Models\Locale;
use Winter\Translate\Classes\Translator;

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

        $alternateLocales = [];

        $router = new Router;
        $translator = Translator::instance();
        $defaultLocale = Locale::getDefault()->code;
        $alternateLocales = collect(array_keys(Locale::listEnabled()))->filter(function ($item) use ($defaultLocale) {
            if ( $item != $defaultLocale ) {
                return $item;
            }
        });

        $translator->setLocale($defaultLocale, false);

        Event::listen('winter.sitemap.makeUrlElement', function ($definition, $xml, $pageUrl, $lastModified, $item, $itemReference, $urlElement) use ($router, $translator, $defaultLocale, $alternateLocales) {
            if ($item->type === 'cms-page' && $itemReference) {
                $page = Page::loadCached($definition->theme, $itemReference);

                if ($page->hasTranslatablePageUrl($defaultLocale)) {
                    $page->rewriteTranslatablePageUrl($defaultLocale);
                }

                $url = $translator->getPathInLocale($page->url, $defaultLocale);
                $url = $router->urlFromPattern($url);
                $url = Url::to($url);

                foreach ($alternateLocales as $locale) {
                    if ($page->hasTranslatablePageUrl($locale)) {
                        $page->rewriteTranslatablePageUrl($locale);
                    }
                    $altUrl = $translator->getPathInLocale($page->url, $locale);
                    $altUrl = $router->urlFromPattern($altUrl);
                    $altUrl = Url::to($altUrl);

                    $linkElement = $xml->createElement('xhtml:link');
                    $linkElement->setAttribute('rel', 'alternate');
                    $linkElement->setAttribute('hreflang', $locale);
                    $linkElement->setAttribute('href', $altUrl);
                    $urlElement->appendChild($linkElement);
                }
            } else {
                $cmsRouter = new \Cms\Classes\Router(\Cms\Classes\Theme::load($definition->theme));
                $request = parse_url( $pageUrl );
                $page = $cmsRouter->findByUrl($request['path']);
                if (!$params = $cmsRouter->getParameters()) {
                    return;
                }

                foreach ($alternateLocales as $locale) {
                    $translatedParams = Event::fire('translate.localePicker.translateParams', [
                        $page,
                        $params,
                        $defaultLocale,
                        $locale
                    ], true);

                    if ($translatedParams) {
                        $params = $translatedParams;
                    }

                    $localeUrl = $router->urlFromPattern($page->url, $params);
                    $url = $translator->getPathInLocale($localeUrl, $locale);
                    $altUrl = Url::to($translator->getPathInLocale($localeUrl, $locale));

                    $linkElement = $xml->createElement('xhtml:link');
                    $linkElement->setAttribute('rel', 'alternate');
                    $linkElement->setAttribute('hreflang', $locale);
                    $linkElement->setAttribute('href', $altUrl);
                    $urlElement->appendChild($linkElement);
                }
            }
        });
    }
}
