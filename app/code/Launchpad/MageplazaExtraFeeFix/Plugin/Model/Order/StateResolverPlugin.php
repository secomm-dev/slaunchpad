<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Launchpad\MageplazaExtraFeeFix\Plugin\Model\Order;

use Launchpad\MageplazaExtraFeeFix\Service\ExtraFeeOrderHelper;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\OrderStateResolverInterface;

class StateResolverPlugin
{
    public function __construct(
        private readonly ExtraFeeOrderHelper $extraFeeOrderHelper
    ) {
    }

    /**
     * Correct order state to CLOSED when all order items have been refunded/canceled
     * and a non-refundable Mageplaza Extra Fee (rf = 0) prevents total_paid == total_refunded,
     * which would otherwise cause Magento core to resolve the state as COMPLETE incorrectly.
     *
     * @param OrderStateResolverInterface $subject
     * @param string $resultState
     * @param OrderInterface $order
     * @param array $arguments
     * @return string
     */
    public function afterGetStateForOrder(
        OrderStateResolverInterface $subject,
        string $resultState,
        OrderInterface $order,
        array $arguments = []
    ): string {
        // getData() and getAllItems() are on Order, not OrderInterface.
        // In practice Magento always passes an Order instance here.
        if (!$order instanceof Order) {
            return $resultState;
        }

        if ($resultState === Order::STATE_COMPLETE && (float)$order->getTotalRefunded() > 0) {
            if ($this->extraFeeOrderHelper->hasNonRefundableExtraFee($order)
                && $this->extraFeeOrderHelper->areAllItemsRefundedOrCanceled($order)
            ) {
                return Order::STATE_CLOSED;
            }
        }

        return $resultState;
    }
}
