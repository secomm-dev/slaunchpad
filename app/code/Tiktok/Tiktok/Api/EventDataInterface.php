<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Api;

interface EventDataInterface
{
    /**
     * Publish event to queue
     *
     * @return bool
     */
    public function publish(): bool;

    /**
     * Set name for the event
     *
     * @param string $eventName
     * @return $this
     */
    public function setEventName(string $eventName): self;

    /**
     * Retrieve event
     *
     * @return array
     */
    public function jsonSerialize(): array;
}
