<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Event\Pool;

use Tiktok\Tiktok\Model\Event\Pool\MetadataInterface;
use Tiktok\Tiktok\Model\Event\TiktokEvent;
use Tiktok\Tiktok\Model\Event\TiktokEventFactory;

/**
 * @inheritdoc
 */
class CompletePayment implements MetadataInterface
{
    /**
     * CompletePayment construct
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
        $event->addOrderToEvent();
        $event->setEventName('CompletePayment');
        $event->publish();
        return $event->getEvent();
    }
}
