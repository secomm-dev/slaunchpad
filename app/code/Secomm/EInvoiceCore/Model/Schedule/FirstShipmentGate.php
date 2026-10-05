<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Model\Schedule;

use Magento\Sales\Model\Order;

/**
 * Allows automatic issue only on the order's first shipment.
 */
class FirstShipmentGate
{
    /**
     * Whether the order has exactly one shipment record.
     *
     * @param Order $order
     * @return bool
     */
    public function isFirstShipment(Order $order): bool
    {
        return (int) $order->getShipmentsCollection()->getSize() === 1;
    }
}
