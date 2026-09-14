<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Service;

use Magento\Checkout\Model\Session;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;

/**
 * Prepares the CUSTOMER checkout success session after a finalized order.
 *
 * Extracted from OrderFinalizer (review-corrective TASK-EDS9T5, Blocker 4):
 * the finalizer is pure order-placement business logic with NO session
 * dependency, so the IPN path (server-to-server, no customer browser) never
 * touches Magento session state. The BROWSER Return processor owns customer
 * UX: after the finalizer returns the bound order — whether it just placed
 * it or recovered an order finalized moments earlier by the IPN — it calls
 * this preparer so the standard success page validates
 * (mirror of Magento Checkouts Onepage::saveOrder session updates).
 *
 * The ONLY writer of the ZaloPay success-session state.
 */
class SuccessSessionPreparer
{
    /**
     * SuccessSessionPreparer constructor.
     *
     * @param Session $checkoutSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Session         $checkoutSession,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Mirror the core Onepage::saveOrder session updates so the standard
     * success page validates after the payment-first redirect flow.
     *
     * @param PaymentAttemptInterface $attempt
     * @param OrderInterface $order
     * @return void
     */
    public function prepare(PaymentAttemptInterface $attempt, OrderInterface $order): void
    {
        try {
            $this->checkoutSession
                ->setLastQuoteId($attempt->getQuoteId())
                ->setLastSuccessQuoteId($attempt->getQuoteId())
                ->setLastOrderId((int)$order->getEntityId())
                ->setLastRealOrderId((string)$order->getIncrementId())
                ->setLastOrderStatus((string)$order->getState());
        } catch (\Exception $e) {
            $this->logger->error('ZaloPay success session preparation failed: ' . $e->getMessage());
        }
    }
}
