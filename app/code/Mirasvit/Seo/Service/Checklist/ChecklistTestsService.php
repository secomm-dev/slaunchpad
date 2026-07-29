<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\Seo\Service\Checklist;

use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Model\Config;
use Mirasvit\Seo\Model\Config\ImageConfig;
use Mirasvit\SeoMarkup\Model\Config\CategoryConfig;
use Mirasvit\SeoMarkup\Model\Config\ProductConfig;

class ChecklistTestsService
{
    private $config;

    private $productConfig;

    private $categoryConfig;

    private $imageConfig;

    private $urlBuilder;

    private $storeManager;

    private $moduleManager;

    private $homePageUrl;

    private $homePageMarkup;

    private $robotsTxtContent;

    public function __construct(
        Config                $config,
        ProductConfig         $productConfig,
        CategoryConfig        $categoryConfig,
        ImageConfig           $imageConfig,
        UrlInterface          $urlBuilder,
        StoreManagerInterface $storeManager,
        ModuleManager         $moduleManager
    ) {
        $this->config         = $config;
        $this->productConfig  = $productConfig;
        $this->categoryConfig = $categoryConfig;
        $this->imageConfig    = $imageConfig;
        $this->urlBuilder     = $urlBuilder;
        $this->storeManager   = $storeManager;
        $this->moduleManager  = $moduleManager;

        $this->homePageUrl      = $this->storeManager->getStore()->getBaseUrl();
        $this->homePageMarkup   = file_get_contents($this->storeManager->getStore()->getBaseUrl());
        $this->robotsTxtContent = trim(
            file_get_contents(rtrim($this->storeManager->getStore()->getBaseUrl(), '/') . '/robots.txt') ?: ''
        );
    }

    /**
     * @return array
     */
    public function resolve(string $testKey)
    {
        return $this->{$testKey}();
    }

    private function robotsTxtIndexableTest(): array
    {
        $status  = true;
        $message = '';

        if (empty($this->robotsTxtContent)) {
            $status  = false;
            $message = '<p>The robots.txt file is empty.</p><p>Please follow
                <a target="_blank" href="https://developers.google.com/search/docs/advanced/robots/create-robots-txt">the instructions</a>
                to configure your robots.txt as well</p>';
        }

        return ['status' => $status, 'message' => $message];
    }

    private function robotsTxtIndexableHint(): string
    {
        return (string)__('The store can\'t be indexed when closed to robots and crawlers.');
    }

    private function homepageMetaTagsExistsTest(): array
    {
        $requiredMetaTags = [
            'title'       => (string)__('Meta title'),
            'keywords'    => 'Meta keywords',
            'description' => (string)__('Meta description'),
        ];

        $status      = true;
        $message     = '';
        $missingTags = array_diff(array_keys($requiredMetaTags), array_keys(get_meta_tags($this->homePageUrl) ?: []));

        if (!empty($missingTags)) {
            $metaTags = [];

            foreach ($missingTags as $key) {
                $metaTags[] = $requiredMetaTags[$key];
            }

            $status  = false;
            $message = '<p>Missing the following Meta Tags on Homepage: ' . implode(', ', $metaTags)
                . '.</p><p>Please configure your Homepage Meta Tags in  Content > Pages > Home Page > Search Engine Optimization</p>';
        }

        return ['status' => $status, 'message' => $message];
    }

    private function homepageMetaTagsExistsHint(): string
    {
        return (string)__('The page needs to display a correct title and store description in search results.');
    }

    private function robotsTxtSEOSitemapExistsTest(): array
    {
        $status  = true;
        $message = '';

        if (empty($this->robotsTxtContent)) {
            $status  = false;
            $message = (string)__('Unable to run test, the robots.txt file is empty');
        } elseif (!preg_match('/Sitemap\:/i', $this->robotsTxtContent)) {
            $status  = false;
            $message = '<p>Sitemap is not set in the robots.txt file.</p><p>Please follow
                <a target="_blank" href="https://developers.google.com/search/docs/advanced/robots/create-robots-txt">
                    this guide
                </a>
                to know more about robots.txt configuration</p>';
        }

        return ['status' => $status, 'message' => $message];
    }

    private function robotsTxtSEOSitemapExistsHint(): string
    {
        return '<p>A sitemap is a file where you provide information about the pages, videos, and other files on your site, and the relationships between them.</p><p>Search engines like Google read this file to more intelligently crawl your site.</p>';
    }

    private function GoogleAnalyticsTagAppliedTest(): array
    {
        preg_match('/i,s,o,g,r,a,m|GoogleAnalyticsObject|Magento_GoogleAnalytics/', $this->homePageMarkup, $match);
        $status  = true;
        $message = '';

        if (empty($match)) {
            $status  = false;
            $message = '<p>Unfortunately, the Google Analytics Tag is not applied for your store.</p><p>Please follow
                <a target="_blank" href="https://docs.magento.com/user-guide/marketing/google-universal-analytics.html"> steps described here </a>
                to make it work as well</p>';
        }

        return ['status' => $status, 'message' => $message];
    }

    private function GoogleAnalyticsTagAppliedHint(): string
    {
        return (string)__('Get a deeper understanding of your customers. Google Analytics gives you the free tools you need to analyze data for your business in one place.');
    }

    private function HTMLSitemapExistsTest(): array
    {
        $status  = (bool)$this->moduleManager->isOutputEnabled('Mirasvit_SeoSitemap');
        $message = '';

        if (!$status) {
            $message = (string)__('Please enable the Mirasvit_SeoSitemap module by running the following command: <pre>bin/magento module:enable Mirasvit_SeoSitemap</pre>');
        }

        return ['status' => $status, 'message' => $message];
    }

    private function HTMLSitemapExistsHint(): string
    {
        return '<p>An HTML sitemap is an HTML page on which all subpages of a website are listed. It is usually linked in the footer of a site and is therefore visible to all visitors.</p>
            <p>In contrast to XML sitemaps, HTML sitemaps are mainly created for users, as they help them to get an overview of the structure of your site and to navigate through all the subpages. Visually, an HTML sitemap resembles a navigation bar where all tabs are opened and where you can click on each link to get to the corresponding subpage.</p>';
    }

    private function ProductsRSExistsTest(): array
    {
        $status  = $this->productConfig->isRsEnabled((int)$this->storeManager->getStore()->getId());
        $message = '';

        if (!$status) {
            $url     = $this->urlBuilder->getUrl('admin/system_config/edit/section/seo/', []);
            $message = 'Please enable the "Enable rich snippet" function in <a target="_blank" href="' . $url
                . '"> Advanced SEO Suite -> Settings</a>, SEO rich snippets and Opengraph -> Product page section';
        }

        return ['status' => $status, 'message' => $message];
    }

    private function ProductsRSExistsHint(): string
    {
        return (string)__('Enhanced search results that provide additional information to searchers on the contents of a page. Google and other search engines use structured data, contained within the HTML, or Javascript of a webpage, to form these snippets.');
    }

    private function CategoriesRSExistsTest(): array
    {
        $status  = $this->categoryConfig->isRsEnabled((int)$this->storeManager->getStore()->getId());
        $message = '';

        if (!$status) {
            $url     = $this->urlBuilder->getUrl('admin/system_config/edit/section/seo/', []);
            $message = 'Please enable the "Enable rich snippet" function in <a target="_blank" href="' . $url
                . '"> Advanced SEO Suite -> Settings</a>, SEO rich snippets and Opengraph -> Category page section';
        }

        return ['status' => $status, 'message' => $message];
    }

    private function CategoriesRSExistsHint(): string
    {
        return (string)__('Enhanced search results that provide additional information to searchers on the contents of a page. Google and other search engines use structured data, contained within the HTML, or Javascript of a webpage, to form these snippets.');
    }

    private function SEOFriendlyImageURLEnabledTest(): array
    {
        $status  = (bool)$this->imageConfig->isFriendlyUrlEnabled();
        $message = '';

        if (!$status) {
            $url     = $this->urlBuilder->getUrl('admin/system_config/edit/section/seo/', []);
            $message = 'Please enable the "Enable SEO-friendly URLs for Product Images" function in <a target="_blank" href="'
                . $url . '"> Advanced SEO Suite -> Settings</a>, Images Settings section';
        }

        return ['status' => $status, 'message' => $message];
    }

    private function SEOFriendlyImageURLEnabledHint(): string
    {
        return (string)__('Using product names or other variables as image filenames is more descriptive to search engines than using random characters or numbers.');
    }

    private function SEOFriendlyAltEnabledTest(): array
    {
        $status  = (bool)$this->imageConfig->isFriendlyAltEnabled();
        $message = '';

        if (!$status) {
            $url     = $this->urlBuilder->getUrl('admin/system_config/edit/section/seo/', []);
            $message = 'Please enable the "Enable generation of Product Images Alt and Title" function in <a target="_blank" href="'
                . $url . '">Advanced SEO Suite -> Settings</a>, Images Settings section';
        }

        return ['status' => $status, 'message' => $message];
    }

    private function SEOFriendlyAltEnabledHint(): string
    {
        return (string)__('Alt tags provide a text alternative for an image for search engines and those using screen readers to access a web page.');
    }
}
