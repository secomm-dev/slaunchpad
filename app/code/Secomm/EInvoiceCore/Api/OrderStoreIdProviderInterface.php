<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Api;

use Magento\Framework\Exception\LocalizedException;

/**
 * Resolves store scope for a sales order (implemented by Magento bridge).
 *
 * @api
 * @since 1.0.0
 */
interface OrderStoreIdProviderInterface
{
    /**
     * Resolve store ID for a sales order.
     *
     * @param int $orderId
     * @return int
     * @throws LocalizedException
     */
    public function getStoreIdByOrderId(int $orderId): int;
}
