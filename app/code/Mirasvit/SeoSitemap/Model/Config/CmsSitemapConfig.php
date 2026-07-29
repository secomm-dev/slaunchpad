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



namespace Mirasvit\SeoSitemap\Model\Config;

use Magento\Store\Model\ScopeInterface;

class CmsSitemapConfig
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * CmsSitemapConfig constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param null|string $store
     * @return bool|int
     */
    public function getIsShowCmsPages($store = null)
    {
        return $this->scopeConfig->getValue(
            'seositemap/frontend/is_show_cms_pages',
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param null|string $store
     * @return array
     */
    public function getIgnoreCmsPages($store = null)
    {
        $value = (string)$this->scopeConfig->getValue(
            'seositemap/frontend/ignore_cms_pages',
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return explode(',', $value);
    }
}
