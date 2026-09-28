<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Observer;

use Tiktok\Tiktok\Model\Event\Management\EventManager;
use Tiktok\Tiktok\Model\Event\TiktokEvent;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

/**
 * Observes the `checkout_cart_save_after` event.
 */
class AddToCartObserver implements ObserverInterface
{
    /**
     * @var \Tiktok\Tiktok\Model\Event\TiktokEvent
     */
    private TiktokEvent $tiktokEvent;

    /**
     * @var \Tiktok\Tiktok\Model\Event\Management\EventManager
     */
    private EventManager $eventManager;

    /**
     * Init dependencies
     *
     * @param \Tiktok\Tiktok\Model\Event\TiktokEvent $tiktokEvent
     * @param \Tiktok\Tiktok\Model\Event\Management\EventManager $eventManager
     */
    public function __construct(
        TiktokEvent $tiktokEvent,
        EventManager $eventManager
    ) {
        $this->tiktokEvent = $tiktokEvent;
        $this->eventManager = $eventManager;
    }

    /**
     * Publish event for add to cart
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /* @var $product \Magento\Catalog\Model\Product */
        $product = $observer->getEvent()->getProduct();
        /* @var $quoteItem \Magento\Quote\Model\Quote\Item */
        $quoteItem = $observer->getEvent()->getQuoteItem();

        $this->tiktokEvent->setEventName('AddToCart');
        if ($quoteItem->getHasChildren()) {
            foreach ($quoteItem->getChildren() as $child) {
                $childProduct = $child->getProduct();
                $this->tiktokEvent->addProduct($childProduct, $quoteItem->getQtyToAdd());
            }
        } else {
            $this->tiktokEvent->addProduct($product, $quoteItem->getQtyToAdd());
        }
        $this->tiktokEvent->publish();

        $this->eventManager->addEvent($this->tiktokEvent);
    }
}
