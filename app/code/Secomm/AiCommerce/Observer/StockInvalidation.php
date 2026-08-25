<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Observer;

use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Secomm\AiCommerce\Model\InvalidateCache;

/**
 * MSI stock/salability invalidation (SPEC-TASK-AIC-PDC1 §3.2).
 *
 * Stock state changes through the MSI indexers (source-item saves,
 * reservation-driven salability updates) WITHOUT any catalog_product_save_*
 * event, so the product observers alone would leave cached availability
 * (in_stock|out_of_stock) stale. Core module-inventory-cache reacts to the
 * same mutations by dispatching `clean_cache_by_tags` with a CacheContext
 * whose identities carry the product cache tag — the same signal core uses
 * to drop full-page cache entries. Observing it here gives the identical
 * coverage for the /ai response cache, at the module's established
 * correctness-first granularity (whole module tag, like a product save).
 */
class StockInvalidation implements ObserverInterface
{
    /**
     * @param InvalidateCache $invalidateCache targeted invalidation helper
     */
    public function __construct(private readonly InvalidateCache $invalidateCache)
    {
    }

    /**
     * Invalidate cached /ai responses when a stock change touches products.
     *
     * @param Observer $observer event observer (clean_cache_by_tags)
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $object = $observer->getEvent()->getObject();

        if (!$object instanceof IdentityInterface) {
            return;
        }

        foreach ($object->getIdentities() as $identity) {
            if ($identity === Product::CACHE_TAG || str_starts_with($identity, Product::CACHE_TAG . '_')) {
                $this->invalidateCache->cleanAll();

                return;
            }
        }
    }
}
