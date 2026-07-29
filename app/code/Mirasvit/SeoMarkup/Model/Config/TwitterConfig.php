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

use Magento\Store\Model\ScopeInterface as ScopeInterface;
use Mirasvit\SeoMarkup\Model\Config;

class TwitterConfig extends Config
{
    const CARD_TYPE_SMALL_IMAGE = 1;
    const CARD_TYPE_LARGE_IMAGE = 2;

    public function getCardType(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            'seo_markup/twitter/card_type',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getUsername(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            'seo_markup/twitter/username',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
