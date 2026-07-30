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

namespace Mirasvit\SeoSitemap\Repository\Provider\Magezon;

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
    const MODULE_NAME = 'Magezon_Blog';
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
            return $this->canShow() && class_exists('Magezon\Blog\Model\Sitemap\ItemProvider\Post');
        }

        return class_exists('Magezon\Blog\Model\Sitemap\ItemProvider\Post');
    }

    public function initSitemapItem($storeId): array
    {
        $result = [];

        $result[] = new DataObject([
            'changefreq' => $this->dataHelper->getPageChangefreq($storeId),
            'priority'   => $this->dataHelper->getPagePriority($storeId),
            'collection' => $this->getItems($storeId),
        ]);

        return $result;

    }

    public function getItems($storeId): array
    {
        $sitemapAuthor = $this->objectManager->get('Magezon\Blog\Model\Sitemap\ItemProvider\Author');
        $sitemapCategory = $this->objectManager->get('Magezon\Blog\Model\Sitemap\ItemProvider\Category');
        $sitemapPost = $this->objectManager->get('Magezon\Blog\Model\Sitemap\ItemProvider\Post');
        $sitemapTag = $this->objectManager->get('Magezon\Blog\Model\Sitemap\ItemProvider\Tag');

        $items = [];
        $items = array_merge($items, $sitemapAuthor->getItems($storeId));
        $items = array_merge($items, $sitemapCategory->getItems($storeId));
        $items = array_merge($items, $sitemapPost->getItems($storeId));
        $items = array_merge($items, $sitemapTag->getItems($storeId));

        return $items;
    }

    private function canShow(): bool
    {
        return (bool)$this->scopeConfig->getValue('seositemap/frontend/is_show_magezon_blog', ScopeInterface::SCOPE_STORE);
    }
}
