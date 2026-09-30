<?php
declare(strict_types=1);

namespace Secomm\TiktokHyva\Model\Event\Pool;

use Tiktok\Tiktok\Model\Event\Pool\MetadataInterface;
use Tiktok\Tiktok\Model\Event\TiktokEvent;
use Tiktok\Tiktok\Model\Event\TiktokEventFactory;

/**
 * AddPaymentInfo — payment-method selection on the checkout page. Quote items ride
 * along (same shape as InitiateCheckout); published S2S with the same event_id
 * that the client-side dynamic fetch tracks.
 */
class AddPaymentInfo implements MetadataInterface
{
    public function __construct(private readonly TiktokEventFactory $eventFactory)
    {
    }

    /**
     * @inheritdoc
     */
    public function getMetadata(?array $context = null): array
    {
        $event = $this->eventFactory->create();
        $event->addQuoteItemsToEvent();
        $event->setEventName('AddPaymentInfo');
        $event->publish();

        return $event->getEvent();
    }
}
