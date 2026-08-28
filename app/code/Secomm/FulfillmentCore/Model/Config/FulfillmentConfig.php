<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads fulfillment_core/* store config paths.
 */
class FulfillmentConfig
{
    private const XML_PATH_ENABLED = 'fulfillment_core/general/enabled';
    private const XML_PATH_MAX_ATTEMPTS = 'fulfillment_core/export/max_attempts';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @param int|null $storeId Store scope; null uses default
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param int|null $storeId Store scope; null uses default
     */
    public function getMaxAttempts(?int $storeId = null): int
    {
        $value = (int) $this->scopeConfig->getValue(
            self::XML_PATH_MAX_ATTEMPTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value > 0 ? $value : 5;
    }
}
