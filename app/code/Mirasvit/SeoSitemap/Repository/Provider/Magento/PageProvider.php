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

namespace Mirasvit\SeoSitemap\Repository\Provider\Magento;

use Magento\Cms\Helper\Page;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Sitemap\Helper\Data as DataHelper;
use Magento\Sitemap\Model\ResourceModel\Cms\PageFactory;
use Magento\Store\Model\ScopeInterface;
use Mirasvit\Seo\Service\Alternate\CmsStrategy;
use Mirasvit\Seo\Service\Config\AlternateConfig;
use Mirasvit\SeoSitemap\Model\Config\CmsSitemapConfig;
use Mirasvit\SeoSitemap\Model\Config\LinkSitemapConfig;
use Mirasvit\SeoSitemap\Repository\Provider\AbstractProvider;

class PageProvider extends AbstractProvider
{
    const KEY         = 'pages';
    const MODULE_NAME = 'Magento_Cms';
    const TITLE       = 'Pages';

    private $cmsFactory;

    private $dataHelper;

    private $cmsSitemapConfig;

    private $linkSitemapConfig;

    private $cmsStrategy;

    private $alternateConfig;

    private $scopeConfig;

    public function __construct(
        CmsSitemapConfig     $cmsSitemapConfig,
        LinkSitemapConfig    $linkSitemapConfig,
        DataHelper           $dataHelper,
        PageFactory          $cmsFactory,
        CmsStrategy          $cmsStrategy,
        AlternateConfig      $alternateConfig,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->dataHelper        = $dataHelper;
        $this->cmsFactory        = $cmsFactory;
        $this->cmsSitemapConfig  = $cmsSitemapConfig;
        $this->linkSitemapConfig = $linkSitemapConfig;
        $this->cmsStrategy       = $cmsStrategy;
        $this->alternateConfig   = $alternateConfig;
        $this->scopeConfig       = $scopeConfig;
    }

    /**
     * @param int $storeId
     * @return array|DataObject
     */
    public function initSitemapItem($storeId)
    {
        return new DataObject([
            'changefreq' => $this->dataHelper->getPageChangefreq($storeId),
            'priority'   => $this->dataHelper->getPagePriority($storeId),
            'collection' => $this->getCmsPages($storeId),
        ]);
    }

    /**
     * @param int $storeId
     * @return array
     */
    private function getCmsPages($storeId)
    {
        $ignore   = $this->cmsSitemapConfig->getIgnoreCmsPages($storeId);
        $links    = $this->linkSitemapConfig->getAdditionalLinks($storeId, true);
        $cmsPages = $this->cmsFactory->create()->getCollection($storeId);

        $homePageId = $this->scopeConfig->getValue(
            Page::XML_PATH_HOME_PAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        foreach ($cmsPages as $cmsKey => $cms) {
            if (in_array($cms->getId(), $ignore)) {
                unset($cmsPages[$cmsKey]);
            }

            if ($cms->getUrl() == $homePageId || $cms->getUrl() . '|' . $cms->getId() == $homePageId) {
                $cms->setUrl('');
            }

            if ($this->alternateConfig->addHreflangToSitemap((int)$storeId)) {
                $alternates = $this->cmsStrategy->getAlternateUrl([], (int)$cms->getId(), (int)$storeId);
                $cms->setAlternates($alternates);
            }
        }

        if ($links) {
            $cmsPages = array_merge($cmsPages, $links);
        }

        return $cmsPages;
    }

    /**
     * @param int $storeId
     * @return array
     */
    public function getItems($storeId)
    {
        return [];
    }
}
