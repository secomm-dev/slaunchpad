<?php
declare(strict_types=1);

namespace Secomm\TiktokHyva\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Secomm\TiktokHyva\Model\PendingEventTracker;

/**
 * PlaceAnOrder — fired by sales_model_service_quote_submit_success, which runs on
 * every checkout order placement path (default checkout and Mageplaza OSC), so the
 * S2S event exists even when the customer never returns to the success page.
 */
class PlaceOrderObserver extends AbstractPendingObserver
{
    public function __construct(
        PendingEventTracker $tracker,
        private readonly CheckoutSession $checkoutSession
    ) {
        parent::__construct($tracker);
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if ($order === null) {
            return;
        }

        // The vendor TiktokEvent::addOrderToEvent() reads the order from the checkout
        // session — at dispatch time last_real_order is not set yet, so seed it here.
        $this->checkoutSession->setLastRealOrderId((string) $order->getIncrementId());

        $this->trackAndStash('PlaceAnOrder', null, $order);
    }
}
