<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Secomm\AiDiscoverability\Model\InvalidateCache;

/**
 * LC-30 config changed. The section event payload does not reliably carry
 * the saved scope, so all store caches are dropped — a rare, cheap operation.
 */
class ConfigInvalidation implements ObserverInterface
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
     * Drop every store's llms.txt cache after an LC-30 config change.
     *
     * @param Observer $observer event observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $this->invalidateCache->cleanAll();
    }
}
