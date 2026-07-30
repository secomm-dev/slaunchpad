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

class PageConfig extends Config
{
    public function isRemoveNativeRs(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'seo_markup/page/is_remove_native_rs',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isOgEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'seo_markup/page/is_og_enabled',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isRsEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'seo_markup/page/is_rs_enabled',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getDefaultSchemaType(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            'seo_markup/page/default_schema_type',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
