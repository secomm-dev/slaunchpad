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

namespace Mirasvit\SeoSitemap\Block\Map;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Helper\Page;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;
use Mirasvit\SeoSitemap\Helper\Data as SeoSitemapHelper;
use Mirasvit\SeoSitemap\Model\Config;
use Mirasvit\SeoSitemap\Model\Config\CmsSitemapConfig;
use Mirasvit\SeoSitemap\Model\Config\LinkSitemapConfig;

class CmsPage extends Template
{
    private $cmsSitemapConfig;

    private $linkSitemapConfig;

    private $cmsPagesCollection;

    private $store;

    private $additionalLinksCollection;

    private $pageCollectionFactory;

    private $scopeConfig;

    private $seoSitemapHelper;

    private $config;

    public function __construct(
        CmsSitemapConfig      $cmsSitemapConfig,
        LinkSitemapConfig     $linkSitemapConfig,
        PageCollectionFactory $pageCollectionFactory,
        ScopeConfigInterface  $scopeConfig,
        SeoSitemapHelper      $seoSitemapHelper,
        Config                $config,
        Context               $context,
        array                 $data = []
    ) {
        $this->cmsSitemapConfig      = $cmsSitemapConfig;
        $this->linkSitemapConfig     = $linkSitemapConfig;
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->scopeConfig           = $scopeConfig;
        $this->seoSitemapHelper      = $seoSitemapHelper;
        $this->config                = $config;
        $this->store                 = $context->getStoreManager()->getStore();

        parent::__construct($context, $data);
    }

    /**
     * @return \Magento\Framework\Phrase
     */
    public function getTitle()
    {
        return __('Pages');
    }

    public function canShowCmsPages(): bool
    {
        return $this->cmsSitemapConfig->getIsShowCmsPages() && $this->getCollection();
    }

    /**
     * @return array
     */
    public function getCollection()
    {
        if (empty($this->cmsPagesCollection)) {
            $ignore     = $this->cmsSitemapConfig->getIgnoreCmsPages();
            $collection = $this->pageCollectionFactory->create()
                ->addStoreFilter($this->store)
                ->addFieldToFilter('is_active', true)
                ->addFieldToFilter('main_table.page_id', ['nin' => $ignore]);

            $this->cmsPagesCollection = $this->prepareCmsCollection($collection);
        }

        return $this->cmsPagesCollection;
    }

    /**
     * @return array
     */
    public function getAdditionalCollection()
    {
        if (empty($this->additionalLinksCollection)) {
            $links = $this->linkSitemapConfig->getAdditionalLinks($this->store->getId());
            foreach ($links as $key => $link) {
                if ($this->seoSitemapHelper->checkIsUrlExcluded($link->getUrl(), $this->store->getId())) {
                    unset($links[$key]);
                }
            }

            $this->additionalLinksCollection = $links;
        }

        return $this->additionalLinksCollection;
    }

    /**
     * @param mixed $collection
     * @return array
     */
    private function prepareCmsCollection($collection)
    {
        $result = [];

        $homePageId = $this->scopeConfig->getValue(
            Page::XML_PATH_HOME_PAGE,
            ScopeInterface::SCOPE_STORE
        );

        foreach ($collection as $key => $page) {
            $url = $page->getIdentifier() == $homePageId ? $this->store->getBaseUrl() : $this->getCmsPageUrl($page);

            if ($this->seoSitemapHelper->checkIsUrlExcluded($url, $this->store->getId())) {
                continue;
            }

            $pageData = new \Magento\Framework\DataObject();
            $pageData->setTitle($page->getTitle());
            $pageData->setUrl($url);

            $result[] = $pageData;
        }

        return $result;
    }

    /**
     * @param PageInterface $page
     * @return string
     */
    private function getCmsPageUrl(PageInterface $page)
    {
        $pageIdentifier = $page->getHierarchyRequestUrl() ?: $page->getIdentifier();

        return $this->_urlBuilder->getUrl($pageIdentifier);
    }

    public function canShowAdditionalLinks(): bool
    {
        return $this->config->getIsShowAdditionalLinks();
    }
}
