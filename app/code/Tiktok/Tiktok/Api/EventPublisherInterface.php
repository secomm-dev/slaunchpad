<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Api;

interface EventPublisherInterface
{
    /**
     * Publish event to queue
     *
     * @param EventDataInterface $eventData
     * @return void
     */
    public function publish(EventDataInterface $eventData): void;
}
