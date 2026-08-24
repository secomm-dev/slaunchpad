<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Secomm\AiCommerce\Model\InvalidateCache;

/**
 * A category was saved/moved/deleted — trees and category-filtered search
 * results can change broadly, so the whole module cache is invalidated.
 */
class CategoryInvalidation implements ObserverInterface
{
    /**
     * @param InvalidateCache $invalidateCache targeted invalidation helper
     */
    public function __construct(private readonly InvalidateCache $invalidateCache)
    {
    }

    /**
     * Invalidate all cached /ai responses after a category change.
     *
     * @param Observer $observer event observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $this->invalidateCache->cleanAll();
    }
}
