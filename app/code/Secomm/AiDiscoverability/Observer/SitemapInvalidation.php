<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sitemap\Model\Sitemap;
use Secomm\AiDiscoverability\Model\InvalidateCache;

/**
 * A sitemap row (sitemap_sitemap table) was saved or deleted — invalidate
 * its store (store 0 = all). This replaces the spec's assumed `clean_sitemap`
 * event, which does NOT exist in this installation.
 */
class SitemapInvalidation implements ObserverInterface
{
    /**
     * @param InvalidateCache $invalidateCache targeted invalidation helper
     */
    public function __construct(private readonly InvalidateCache $invalidateCache)
    {
    }

    /**
     * Invalidate llms.txt caches after a sitemap row save/delete.
     *
     * @param Observer $observer event observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $object = $observer->getEvent()->getData('object');

        if (!$object instanceof Sitemap) {
            return; // unrelated model — do nothing
        }

        $storeId = (int) $object->getStoreId();

        if ($storeId === 0) {
            $this->invalidateCache->cleanAll();

            return;
        }

        $this->invalidateCache->cleanStore($storeId);
    }
}
