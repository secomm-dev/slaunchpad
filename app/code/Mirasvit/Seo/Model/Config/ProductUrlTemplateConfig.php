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

namespace Mirasvit\Seo\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ProductUrlTemplateConfig
{
    private $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    public function getProductUrlKey(?int $store = null): string
    {
        $productUrlKey = (string)$this->scopeConfig->getValue(
            'seo/url/product_url_key',
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return trim($productUrlKey);
    }

    public function getRegenerateUrlKeyOnVisibilityChange(?int $store = null): bool
    {
        return $this->getProductUrlKey($store)
            && (bool)$productUrlKey = (string)$this->scopeConfig->getValue(
                'seo/url/regenerate_url_key_after_visibility_changed',
                ScopeInterface::SCOPE_STORE,
                $store
            );
    }
}
