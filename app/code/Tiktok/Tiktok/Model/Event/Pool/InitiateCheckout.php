<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Event\Pool;

use Tiktok\Tiktok\Model\Event\Pool\MetadataInterface;
use Tiktok\Tiktok\Model\Event\TiktokEvent;
use Tiktok\Tiktok\Model\Event\TiktokEventFactory;

/**
 * @inheritdoc
 */
class InitiateCheckout implements MetadataInterface
{
    /**
     * InitiateCheckout construct
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
        $event->addQuoteItemsToEvent();
        $event->setEventName('InitiateCheckout');
        $event->publish();
        return $event->getEvent();
    }
}
