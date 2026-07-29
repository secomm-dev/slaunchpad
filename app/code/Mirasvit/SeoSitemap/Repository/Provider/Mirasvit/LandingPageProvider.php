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



namespace Mirasvit\SeoSitemap\Repository\Provider\Mirasvit;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sitemap\Helper\Data as DataHelper;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\SeoSitemap\Repository\Provider\AbstractProvider;

class LandingPageProvider extends AbstractProvider
{
    const KEY         = 'landing_pages';
    const MODULE_NAME = 'Mirasvit_LandingPage';
    const TITLE       = 'Landing Pages';

    private $objectManager;

    private $dataHelper;

    private $storeManager;

    private $scopeConfig;

    private $request;

    public function __construct(
        ObjectManagerInterface $objectManager,
        DataHelper             $sitemapData,
        StoreManagerInterface  $storeManager,
        ScopeConfigInterface   $scopeConfig,
        RequestInterface       $request
    ) {
        $this->objectManager = $objectManager;
        $this->dataHelper    = $sitemapData;
        $this->storeManager  = $storeManager;
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

    public function getItems($storeId): array
    {
        $items = [];

        $baseUrl = $this->storeManager->getStore($storeId)->getBaseUrl();

        $urlSuffix = '';
        if (class_exists('Mirasvit\LandingPage\Model\Config\ConfigProvider')) {
            $config    = $this->objectManager->create('Mirasvit\LandingPage\Model\Config\ConfigProvider');
            $urlSuffix = $config->getUrlSuffix();
        }

        $collection = $this->objectManager->create('Mirasvit\LandingPage\Model\ResourceModel\Page\Collection');
        $collection->addFieldToFilter('store_ids', ['in' => [0, $storeId]]);
        $collection->addFieldToFilter('is_active', 1);
        $collection->addFieldToFilter('meta_tags', ['like' => 'INDEX%']);

        foreach ($collection as $page) {
            $items[] = new DataObject([
                'id'    => $page->getId(),
                'url'   => $baseUrl . $page->getUrlKey() . $urlSuffix,
                'title' => !empty($page->getPageTitle()) ? $page->getPageTitle() : $page->getName()
            ]);
        }

        return $items;
    }

    private function canShow(): bool
    {
        return (bool)$this->scopeConfig->getValue('seositemap/frontend/is_show_landing_pages', ScopeInterface::SCOPE_STORE);
    }
}
