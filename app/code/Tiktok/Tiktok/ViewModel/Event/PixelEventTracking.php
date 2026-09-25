<?php

declare(strict_types=1);

namespace Tiktok\Tiktok\ViewModel\Event;

use Tiktok\Tiktok\Model\Event\Pool;
use Tiktok\Tiktok\Model\Event\TiktokEvent;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Catalog\Helper\Data as CatalogHelper;

class PixelEventTracking implements ArgumentInterface
{
    /**
     * PixelEventTracking construct
     *
     * @param \Tiktok\Tiktok\Model\Event\Pool $eventPool
     * @param \Tiktok\Tiktok\Model\Event\TiktokEvent $tiktokEvent
     * @param \Magento\Catalog\Helper\Data $catalogHelper
     */
    public function __construct(
        private readonly Pool $eventPool,
        private readonly TiktokEvent $tiktokEvent,
        private readonly CatalogHelper $catalogHelper
    ) {
    }

    /**
     * Retrieve TikTok view events
     *
     * @param array $eventTypes
     * @return array
     */
    public function getTiktokViewEvents(array $eventTypes = []): array
    {
        if (!$eventTypes) {
            $eventTypes = ['PageView'];
        }

        $eventData = [];
        foreach ($eventTypes as $eventType) {
            if ($data = $this->eventPool->execute($eventType)) {
                $eventData[] = $data;
            }
        }

        return $eventData;
    }

    /**
     * Perform check if to inject pixel tracking
     *
     * @return bool
     */
    public function shouldInject(): bool
    {
        return $this->tiktokEvent->shouldReport() && $this->tiktokEvent->isPixelTrackingEnabled();
    }

    /**
     * Retrieve product
     *
     * @return int|null
     */
    public function getProduct()
    {
        return $this->catalogHelper->getProduct()?->getId();
    }
}
