<?php
declare(strict_types=1);

namespace Secomm\TiktokHyva\Observer;

use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Secomm\TiktokHyva\Model\PendingEventTracker;

/**
 * Base for pixel events triggered by core events; delegates to the tracker.
 */
abstract class AbstractPendingObserver implements ObserverInterface
{
    public function __construct(protected readonly PendingEventTracker $tracker)
    {
    }

    /**
     * Build, publish and stash one event
     *
     * @param string $eventName TikTok event name (e.g. PlaceAnOrder)
     * @param Product|null $product
     * @param Order|null $order
     * @return void
     */
    protected function trackAndStash(string $eventName, ?Product $product = null, ?Order $order = null): void
    {
        $this->tracker->track($eventName, $product, $order);
    }

    /**
     * @param Observer $observer
     * @return void
     */
    abstract public function execute(Observer $observer): void;
}
