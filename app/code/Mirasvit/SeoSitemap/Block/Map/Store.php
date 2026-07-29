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



namespace Mirasvit\SeoSitemap\Block\Map;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mirasvit\SeoSitemap\Helper\Data as SeoSitemapHelper;
use Mirasvit\SeoSitemap\Model\Config;

class Store extends Template
{
    private $config;

    private $seoSitemapHelper;

    public function __construct(
        Config           $config,
        SeoSitemapHelper $seoSitemapHelper,
        Context          $context,
        array            $data = []
    ) {
        $this->config           = $config;
        $this->seoSitemapHelper = $seoSitemapHelper;

        parent::__construct($context, $data);
    }

    /**
     * @return \Magento\Framework\Phrase
     */
    public function getTitle()
    {
        return __('Stores');
    }

    public function getStores(): array
    {
        $currentStoreId = $this->_storeManager->getStore()->getId();

        $stores = $this->_storeManager->getStores();

        foreach ($stores as $key => $store) {
            if ($this->seoSitemapHelper->checkIsUrlExcluded($store->getBaseUrl(), $currentStoreId)) {
                unset($stores[$key]);
            }
        }

        return $stores;
    }

    /**
     * @return bool|int
     */
    public function canShowStores()
    {
        return $this->config->getIsShowStores();
    }
}
