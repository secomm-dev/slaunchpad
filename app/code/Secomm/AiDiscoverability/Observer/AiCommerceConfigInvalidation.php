<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Secomm\AiDiscoverability\Model\InvalidateCache;

/**
 * AiCommerce config changed. The advertised Machine-readable Commerce section
 * of llms.txt depends on the store-scoped `seocomm_ai_commerce/general/enabled`
 * flag, so the cached bodies must drop. Same policy as the LC-30 section
 * observer: the payload carries no reliable scope, all stores are dropped —
 * a rare, cheap operation (SPEC-TASK-7FBHHC §2.4). Soft by construction: this
 * observer only fires on AiCommerce's own section-save event, so the module
 * being absent can never trigger it.
 */
class AiCommerceConfigInvalidation implements ObserverInterface
{
    /**
     * @param InvalidateCache $invalidateCache targeted invalidation helper
     */
    public function __construct(private readonly InvalidateCache $invalidateCache)
    {
    }

    /**
     * Drop every store's llms.txt cache after an AiCommerce config change.
     *
     * @param Observer $observer event observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $this->invalidateCache->cleanAll();
    }
}
