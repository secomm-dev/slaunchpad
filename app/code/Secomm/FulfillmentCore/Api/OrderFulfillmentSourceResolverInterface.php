<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Api;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Deterministic MSI source codes for an order (item assignment → website stock → default).
 */
interface OrderFulfillmentSourceResolverInterface
{
    /**
     * @param OrderInterface $order Magento order
     * @return string[] Unique source codes; first entry is primary for warehouse resolve
     */
    public function resolveSourceCodes(OrderInterface $order): array;
}
