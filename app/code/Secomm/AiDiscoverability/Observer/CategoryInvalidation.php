<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Observer;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\AiDiscoverability\Model\InvalidateCache;

/**
 * A category changed — invalidate the stores whose tree contains it
 * (fall back to all stores when store assignment cannot be resolved).
 */
class CategoryInvalidation implements ObserverInterface
{
    /**
     * @var InvalidateCache
     */
    private $invalidateCache;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param InvalidateCache $invalidateCache targeted invalidation helper
     * @param StoreManagerInterface $storeManager store registry
     */
    public function __construct(InvalidateCache $invalidateCache, StoreManagerInterface $storeManager)
    {
        $this->invalidateCache = $invalidateCache;
        $this->storeManager = $storeManager;
    }

    /**
     * Invalidate llms.txt caches for the stores containing the saved category.
     *
     * @param Observer $observer event observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $category = $observer->getEvent()->getData('object');

        if (!$category instanceof CategoryInterface) {
            return;
        }

        $storeIds = array_map('intval', (array) $category->getStoreIds());

        if ($storeIds === [] || in_array(0, $storeIds, true)) {
            $this->invalidateCache->cleanAll();

            return;
        }

        $this->invalidateCache->cleanStores($storeIds);
    }
}
