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



namespace Mirasvit\SeoSitemap\Repository\Provider\Aheadworks;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Mirasvit\Seo\Service\Alternate\AheadworksBlogStrategy;
use Mirasvit\Seo\Service\Config\AlternateConfig;
use Mirasvit\SeoSitemap\Repository\Provider\AbstractProvider;

class BlogProvider extends AbstractProvider
{
    const KEY         = 'blogs';
    const MODULE_NAME = 'Aheadworks_Blog';
    const TITLE       = 'Blog';

    private $objectManager;

    private $blogStrategy;

    private $alternateConfig;

    private $itemsProvider = '';

    private $scopeConfig;

    private $request;

    public function __construct(
        ObjectManagerInterface $objectManager,
        AheadworksBlogStrategy $blogStrategy,
        AlternateConfig        $alternateConfig,
        ScopeConfigInterface   $scopeConfig,
        RequestInterface       $request
    ) {
        $this->objectManager   = $objectManager;
        $this->blogStrategy    = $blogStrategy;
        $this->alternateConfig = $alternateConfig;
        $this->scopeConfig     = $scopeConfig;
        $this->request         = $request;
    }

    public function isApplicable(): bool
    {
        if (class_exists('Aheadworks\Blog\Model\Sitemap\ItemsProvider')) {
            $this->itemsProvider = 'Aheadworks\Blog\Model\Sitemap\ItemsProvider';
        } elseif (class_exists('Aheadworks\Blog\Model\Sitemap\ItemsProviderComposite')) {
            $this->itemsProvider = 'Aheadworks\Blog\Model\Sitemap\ItemsProviderComposite';
        }

        if ($this->request->getFullActionName() == 'seositemap_index_index') {
            return $this->canShow() && !!$this->itemsProvider;
        }

        return !!$this->itemsProvider;
    }

    /**
     * @param int $storeId
     *
     * @return array
     */
    public function initSitemapItem($storeId)
    {
        $result = [];

        // Aheadworks sitemap items provider does not add alternate links to the sitemap
        // so we use our provider is alternates for sitemaps enabled
        if ($this->alternateConfig->addHreflangToSitemap((int)$storeId)) {
            return $this->getItems($storeId);
        }

        if (strpos($this->itemsProvider, 'ItemsProviderComposite') !== false) {
            $sitemapHelper = $this->objectManager->get($this->itemsProvider);

            return $sitemapHelper->getItems($storeId);
        }

        $sitemapHelper = $this->objectManager->get($this->itemsProvider);

        $result[] = $sitemapHelper->getBlogItem($storeId);
        $result[] = $sitemapHelper->getCategoryItems($storeId);
        $result[] = $sitemapHelper->getPostItems($storeId);

        return $result;
    }

    /**
     * @param int $storeId
     *
     * @return array
     */
    public function getItems($storeId)
    {
        $configProvider            = $this->objectManager->create('Aheadworks\Blog\Model\Config');
        $urlBuilder                = $this->objectManager->create('Magento\Framework\UrlInterface');
        $urlHelper                 = $this->objectManager->create('Aheadworks\Blog\Model\Url');
        $categoryCollectionFactory = $this->objectManager->create('Aheadworks\Blog\Model\ResourceModel\Category\CollectionFactory');
        $postCollectionFactory     = $this->objectManager->create('Aheadworks\Blog\Model\ResourceModel\Post\CollectionFactory');

        $items = [];
        $home  = $configProvider->getRouteToBlog($storeId);

        if (!empty($home)) {
            $homeItem = new DataObject([
                'title'      => (string)__('Blog Home'),
                'url'        => $urlBuilder->getUrl($home)
            ]);

            if ($this->alternateConfig->addHreflangToSitemap((int)$storeId)) {
                $homeItem->setAlternates($this->blogStrategy->getBlogAlternates('home'));
            }

            $items['home'] = $homeItem;
        }

        $categoryCollection = $categoryCollectionFactory->create()
            ->addFieldToFilter('status', ['eq' => '1'])
            ->addStoreFilter($storeId);

        foreach ($categoryCollection->getItems() as $category) {
            $catItem = new DataObject([
                'title'      => $category->getName(),
                'url'        => $urlBuilder->getUrl($urlHelper->getCategoryRoute($category))
            ]);

            if ($this->alternateConfig->addHreflangToSitemap((int)$storeId)) {
                $catItem->setAlternates($this->blogStrategy->getBlogAlternates('category', $category));
            }

            $items['cat' . $category->getId()] = $catItem;
        }

        $postCollection = $postCollectionFactory->create()
            ->addFieldToFilter('status', ['eq' => 'publication'])
            ->addStoreFilter($storeId);

        foreach ($postCollection->getItems() as $post) {
            $postItem = new DataObject([
                'title'      => $post->getTitle(),
                'url'        => $urlBuilder->getUrl($urlHelper->getPostRoute($post))
            ]);

            if ($this->alternateConfig->addHreflangToSitemap((int)$storeId)) {
                $postItem->setAlternates($this->blogStrategy->getBlogAlternates('post', $post));
            }

            $items['post' . $post->getId()] = $postItem;
        }

        return $items;
    }

    private function canShow(): bool
    {
        return (bool)$this->scopeConfig->getValue('seositemap/frontend/is_show_aheadworks_blog', ScopeInterface::SCOPE_STORE);
    }
}
