<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Event\Pool;

use Tiktok\Tiktok\Model\Event\Pool\MetadataInterface;
use Tiktok\Tiktok\Model\Event\TiktokEvent;
use Tiktok\Tiktok\Model\Event\TiktokEventFactory;

/**
 * @inheritdoc
 */
class PageView implements MetadataInterface
{
    /**
     * PageView construct
     *
     * @param TiktokEventFactory $eventFactory
     */
    public function __construct(private readonly TiktokEventFactory $eventFactory)
    {
    }

    /**
     * @inheritdoc
     */
    public function getMetadata(?array $context = null): array
    {
        /** @var TiktokEvent $event */
        $event = $this->eventFactory->create();
        $event->setEventName('Pageview');
        $event->publish();
        return $event->getEvent();
    }
}
