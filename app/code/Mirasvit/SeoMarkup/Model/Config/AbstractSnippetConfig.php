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

namespace Mirasvit\SeoMarkup\Model\Config;

use Magento\Store\Model\ScopeInterface;
use Mirasvit\SeoMarkup\Model\Config;

abstract class AbstractSnippetConfig extends Config
{
    public const DESCRIPTION_TYPE_DESCRIPTION = 1;
    public const DESCRIPTION_TYPE_META        = 2;

    public const PRODUCT_OFFERS_TYPE_DISABLED     = 0;
    public const PRODUCT_OFFERS_TYPE_CURRENT_PAGE = 1;
    public const PRODUCT_OFFERS_TYPE_ENTIRE       = 2;

    public const PRODUCT_OFFERS_FORMAT_FULL      = 1;
    public const PRODUCT_OFFERS_FORMAT_SIMPLE    = 2;
    public const PRODUCT_OFFERS_FORMAT_AGGREGATE = 3;

    public const IMAGE_NO               = 0;
    public const IMAGE_YES              = 1;
    public const IMAGE_YES_NON_FILTERED = 2;

    abstract protected function getConfigSection(): string;

    public function isRemoveNativeRs(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            $this->getConfigPath('is_remove_native_rs'),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isRsEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            $this->getConfigPath('is_rs_enabled'),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isOgEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            $this->getConfigPath('is_og_enabled'),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getProductOffersType(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            $this->getConfigPath('product_offers_type'),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getProductOffersFormat(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            $this->getConfigPath('product_offers_format'),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getDescriptionType(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            $this->getConfigPath('description_type'),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getImage(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            $this->getConfigPath('is_image_enabled'),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getAggregateBrandName(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            $this->getConfigPath('aggregate_brand_name'),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isAggregateRatingEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            $this->getConfigPath('aggregate_include_rating'),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isExcludeZeroPriceProducts(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            $this->getConfigPath('aggregate_exclude_zero_price'),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    protected function getConfigPath(string $field): string
    {
        return 'seo_markup/' . $this->getConfigSection() . '/' . $field;
    }
}
