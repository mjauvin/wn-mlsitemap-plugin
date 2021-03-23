<?php namespace StudioAzura\MLSitemap\Classes;

use Url;
use Event;
use Request;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use October\Rain\Router\Router;
use RainLab\Translate\Models\Locale;
use RainLab\Translate\Classes\Translator;

/**
 * Definition Model
 */
class Definition extends \RainLab\Sitemap\Models\Definition
{
    public function generateSitemap()
    {
        if (!$this->items) {
            return;
        }

        $currentUrl = Request::path();
        $theme = Theme::load($this->theme);

        $alternateLocales = [];

        $translator = Translator::instance();
        $defaultLocale = Locale::getDefault()->code;
        $alternateLocales = array_keys(Locale::listEnabled());
        $translator->setLocale($defaultLocale, false);

        /*
         * Cycle each page and add its URL
         */
        foreach ($this->items as $item) {
            /*
             * Explicit URL
             */
            if ($item->type == 'url') {
                $this->addItemToSet($item, Url::to($item->url));
            }
            /*
             * Registered sitemap type
             */
            else {
                $obj = new MenuItemTypes();
                if (!($apiResult = $obj->resolveMenuItem($item->type, $item, $currentUrl, $theme))) {
                    $apiResult = Event::fire('pages.menuitem.resolveItem', [$item->type, $item, $currentUrl, $theme]);
                }

                if (!is_array($apiResult)) {
                    continue;
                }
                
                foreach ($apiResult as $itemInfo) {
                    if (!is_array($itemInfo)) {
                        continue;
                    }

                    /*
                     * Single item
                     */
                    if (isset($itemInfo['url'])) {
                        $url = $itemInfo['url'];
                        $alternateLocaleUrls = [];

                        if ($item->type == 'cms-page') {
                            $page = Page::loadCached($theme, $item->reference);
                            $router = new Router;

                            if ($page->hasTranslatablePageUrl($defaultLocale)) {
                                $page->rewriteTranslatablePageUrl($defaultLocale);
                            }

                            $url = $translator->getPathInLocale($page->url, $defaultLocale);
                            $url = $router->urlFromPattern($url);
                            $url = Url::to($url);

                            if (count($alternateLocales) > 1) {
                                foreach ($alternateLocales as $locale) {
                                    if ($page->hasTranslatablePageUrl($locale)) {
                                        $page->rewriteTranslatablePageUrl($locale);
                                    }
                                    $altUrl = $translator->getPathInLocale($page->url, $locale);
                                    $altUrl = $router->urlFromPattern($altUrl);
                                    $alternateLocaleUrls[$locale] = Url::to($altUrl);
                                }
                            }
                        }

                        if (isset($itemInfo['alternate_locale_urls'])) {
                            $alternateLocaleUrls = $itemInfo['alternate_locale_urls'];
                        }

                        $this->addItemToSet($item, $url, array_get($itemInfo, 'mtime'), $alternateLocaleUrls);
                    }

                    /*
                     * Multiple items
                     */
                    if (isset($itemInfo['items'])) {

                        $parentItem = $item;

                        $itemIterator = function($items) use (&$itemIterator, $parentItem)
                        {
                            foreach ($items as $item) {
                                if (isset($item['url'])) {
                                    $alternateLocaleUrls = [];
                                    if (isset($item['alternate_locale_urls'])) {
                                        $alternateLocaleUrls = $item['alternate_locale_urls'];
                                    }
                                    $this->addItemToSet($parentItem, $item['url'], array_get($item, 'mtime'), $alternateLocaleUrls);
                                }

                                if (isset($item['items'])) {
                                    $itemIterator($item['items']);
                                }
                            }
                        };

                        $itemIterator($itemInfo['items']);
                    }
                }
            }
        }

        $urlSet = $this->makeUrlSet();
        $xml = $this->makeXmlObject();
        $xml->appendChild($urlSet);
        $xml->formatOutput = true;

        return $xml->saveXML();
    }

    protected function addItemToSet($item, $url, $mtime = null, $alternateLocaleUrls = [])
    {
        if (!$alternateLocaleUrls) {
            return parent::addItemToSet($item, $url, $mtime);
        }

        if ($mtime instanceof \DateTime) {
            $mtime = $mtime->getTimestamp();
        }

        $xml = $this->makeXmlObject();
        $urlSet = $this->makeUrlSet();
        $mtime = $mtime ? date('c', $mtime) : date('c');

        foreach ($alternateLocaleUrls as $alternateLocaleUrl) {
            $urlElement = $this->makeUrlElement(
                $xml,
                $alternateLocaleUrl,
                $mtime,
                $item->changefreq,
                $item->priority,
                $alternateLocaleUrls
            );
            if ($urlElement) {
                $urlSet->appendChild($urlElement);
            }
        }

        return $urlSet;
    }

    protected function makeUrlElement($xml, $pageUrl, $lastModified, $frequency, $priority, $alternateLocaleUrls = [])
    {
        if (($url = parent::makeUrlElement($xml, $pageUrl, $lastModified, $frequency, $priority)) === false) {
            return false;
        }

        foreach ($alternateLocaleUrls as $locale => $locale_url) {
            $alternateUrl = $xml->createElement('xhtml:link');
            $alternateUrl->setAttribute('rel', 'alternate');
            $alternateUrl->setAttribute('hreflang', $locale);
            $alternateUrl->setAttribute('href', $locale_url);
            $url->appendChild($alternateUrl);
        }

        return $url;
    }

    protected function makeUrlSet()
    {
        if ($this->urlSet !== null) {
            return $this->urlSet;
        }

        $xml = $this->makeXmlObject();
        $urlSet = $xml->createElement('urlset');
        $urlSet->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'Googlebot/') === false) {
            // hack to force browser to properly render the XML sitemap
            $urlSet->setAttribute('xmlns:xhtml', 'xmlns:xhtml-namespace-definition-URL-here');
        } else {
            // Googlebot needs this URL
            $urlSet->setAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
        }
        $urlSet->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $urlSet->setAttribute('xsi:schemaLocation', 'http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd');

        return $this->urlSet = $urlSet;
    }

    public static function getMenuItem($page, $menuItem, $paramName)
    {
        $result = [];

        $defaultLocale = Locale::getDefault()->code;
        $pageUrl = self::getPageLocaleUrl($page, $menuItem, $defaultLocale, [$paramName => 'slug']);

        $alternateLocales = array_keys(Locale::listEnabled());

        if (count($alternateLocales) > 1) {
            foreach ($alternateLocales as $locale) {
                $result['alternate_locale_urls'][$locale] = self::getPageLocaleUrl($page, $menuItem, $locale, [$paramName => 'slug']);
            }
        }

        $result['title'] = $menuItem->name;
        $result['url'] = $pageUrl;
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
        }

        $item->lang($locale);

        $params = [];
        foreach ($paramMap as $paramName => $fieldName) {
            if ($page->baseFileName == 'category' && $fieldName == 'slug') {
                $params[$paramName] = getFullSlug($item);
            } else {
                $params[$paramName] = $item->$fieldName;
            }
        }

        $url = $translator->getPathInLocale($page->url, $locale);
        $url = (new Router)->urlFromPattern($url, $params);
        $url = Url::to($url);

        return $url;
    }
}
