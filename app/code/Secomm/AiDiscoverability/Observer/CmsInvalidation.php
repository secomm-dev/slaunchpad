<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Cms\Api\Data\PageInterface;
use Secomm\AiDiscoverability\Model\InvalidateCache;

/**
 * A CMS page changed — invalidate only the stores the page belongs to
 * (store 0 = all stores).
 */
class CmsInvalidation implements ObserverInterface
{
    /**
     * @var InvalidateCache
     */
    private $invalidateCache;

    /**
     * @param InvalidateCache $invalidateCache targeted invalidation helper
     */
    public function __construct(InvalidateCache $invalidateCache)
    {
        $this->invalidateCache = $invalidateCache;
    }

    /**
     * Invalidate llms.txt caches for the stores the saved CMS page belongs to.
     *
     * @param Observer $observer event observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $page = $observer->getEvent()->getData('object');

        if (!$page instanceof PageInterface) {
            return;
        }

        $storeIds = array_map('intval', (array) $page->getStores());

        if (in_array(0, $storeIds, true) || $storeIds === []) {
            $this->invalidateCache->cleanAll();

            return;
        }

        $this->invalidateCache->cleanStores($storeIds);
    }
}
