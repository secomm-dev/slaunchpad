<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Secomm\AiCommerce\Model\InvalidateCache;

/**
 * Module configuration changed (enabled flag, caps, allowlists) — all cached
 * /ai responses are invalidated because every bound may have changed.
 */
class ConfigInvalidation implements ObserverInterface
{
    /**
     * @param InvalidateCache $invalidateCache targeted invalidation helper
     */
    public function __construct(private readonly InvalidateCache $invalidateCache)
    {
    }

    /**
     * Invalidate all cached /ai responses after a config change.
     *
     * @param Observer $observer event observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $this->invalidateCache->cleanAll();
    }
}
