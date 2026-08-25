<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Secomm\AiCommerce\Model\InvalidateCache;

/**
 * A product was saved or deleted — a previously absent product may now enter
 * search results (visibility/price/stock change), so the whole module cache
 * is invalidated (correctness > maximum cache granularity; plan rev 2 §4).
 */
class ProductInvalidation implements ObserverInterface
{
    /**
     * @param InvalidateCache $invalidateCache targeted invalidation helper
     */
    public function __construct(private readonly InvalidateCache $invalidateCache)
    {
    }

    /**
     * Invalidate all cached /ai responses after a product change.
     *
     * @param Observer $observer event observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $this->invalidateCache->cleanAll();
    }
}
