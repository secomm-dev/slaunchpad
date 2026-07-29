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



namespace Mirasvit\SeoSitemap\Repository\Provider\Magefan;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sitemap\Helper\Data as DataHelper;
use Magento\Store\Model\ScopeInterface;
use Mirasvit\SeoSitemap\Repository\Provider\AbstractProvider;

class BlogProvider extends AbstractProvider
{
    const KEY         = 'blogs';
    const MODULE_NAME = 'Magefan_Blog';
    const TITLE       = 'Blog';

    private $objectManager;

    private $dataHelper;

    private $scopeConfig;

    private $request;

    public function __construct(
        ObjectManagerInterface $objectManager,
        DataHelper             $sitemapData,
        ScopeConfigInterface   $scopeConfig,
        RequestInterface       $request
    ) {
        $this->objectManager = $objectManager;
        $this->dataHelper    = $sitemapData;
        $this->scopeConfig   = $scopeConfig;
        $this->request       = $request;
    }

    public function isApplicable(): bool
    {
        if ($this->request->getFullActionName() == 'seositemap_index_index') {
            return $this->canShow();
        }

        return true;
    }

    /**
     * @param int $storeId
     * @return array
     */
    public function initSitemapItem($storeId)
    {
        $result = [];

        $result[] = new DataObject([
            'changefreq' => $this->dataHelper->getPageChangefreq($storeId),
            'priority'   => $this->dataHelper->getPagePriority($storeId),
            'collection' => $this->getItems($storeId),
        ]);

        return $result;
    }

    /**
     * @param int $storeId
     * @return array
     */
    public function getItems($storeId)
    {
        /** @var \Magefan\Blog\Model\Url $urlHelper */
        $urlHelper     = $this->objectManager->get('Magefan\Blog\Model\Url');
        $postCollection = \Magento\Framework\App\ObjectManager::getInstance()
                            ->create(\Magefan\Blog\Model\Post::class)
                            ->getCollection($storeId)
                            ->addStoreFilter($storeId)
                            ->addActiveFilter();

        $items = [];

        foreach ($postCollection as $key => $post) {
            $items[] = new DataObject([
                'id'         => $post->getId(),
                'url'        => $urlHelper->getUrlPath($post->getIdentifier(), 'post'),
                'title'      => $post->getTitle(),
                'updated_at' => $post->getUpdatedAt(),
            ]);
        }

        $catCollection = \Magento\Framework\App\ObjectManager::getInstance()
            ->create(\Magefan\Blog\Model\Category::class)
            ->getCollection($storeId)
            ->addStoreFilter($storeId)
            ->addActiveFilter();

        foreach ($catCollection as $key => $cat) {
            $items[] = new DataObject([
                'id'         => $cat->getId(),
                'url'        => $cat->getUrl(),
                'title'      => $cat->getTitle(),
                'updated_at' => $cat->getUpdatedAt(),
            ]);
        }

        return $items;
    }

    private function canShow(): bool
    {
        return (bool)$this->scopeConfig->getValue('seositemap/frontend/is_show_magefan_blog', ScopeInterface::SCOPE_STORE);
    }
}
