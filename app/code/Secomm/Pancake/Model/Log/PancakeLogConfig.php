<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Model\Log;

use Secomm\FulfillmentCore\Api\PosLogConfigInterface;
use Secomm\Pancake\Model\Config\PancakeConfig;
use Secomm\Pancake\Model\Order\PancakeOrderExporter;

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
        return PancakeOrderExporter::SERVICE_CODE;
    }

    /**
     * @param int|null $storeId Store scope; null uses default config scope
     */
    public function isLogEnabled(?int $storeId = null): bool
    {
        return $this->config->isLogEnabled($storeId);
    }
}
