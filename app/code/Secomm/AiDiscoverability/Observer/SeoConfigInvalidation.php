<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Secomm\AiDiscoverability\Model\InvalidateCache;

/**
 * Mirasvit `seo` section changed (noindex rules, trailing-slash policy) —
 * scope not derivable from the event, drop every store's llms.txt cache.
 */
class SeoConfigInvalidation implements ObserverInterface
{
    /**
     * @param InvalidateCache $invalidateCache targeted invalidation helper
     */
    public function __construct(private readonly InvalidateCache $invalidateCache)
    {
    }

    /**
     * Drop every store's llms.txt cache after a Mirasvit seo config change.
     *
     * @param Observer $observer event observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $this->invalidateCache->cleanAll();
    }
}
