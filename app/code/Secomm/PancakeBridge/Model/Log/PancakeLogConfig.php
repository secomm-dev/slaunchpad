<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PancakeBridge\Model\Log;

use Secomm\FulfillmentCore\Api\PosLogConfigInterface;
use Secomm\PancakeBridge\Model\Config\PancakeConfig;
use Secomm\PancakeFunction\Model\ServiceCode;

/**
 * Pancake admin flag for FulfillmentCore file logging.
 */
class PancakeLogConfig implements PosLogConfigInterface
{
    public function __construct(
        private readonly PancakeConfig $config
    ) {
    }

    public function getServiceCode(): string
    {
        return ServiceCode::CODE;
    }

    /**
     * @param int|null $storeId Store scope; null uses default config scope
     */
    public function isLogEnabled(?int $storeId = null): bool
    {
        return $this->config->isLogEnabled($storeId);
    }
}
