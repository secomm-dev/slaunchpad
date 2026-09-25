<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Event\Management;

use Tiktok\Tiktok\Model\Event\TiktokEvent;

class EventManager
{
    /**
     * @var array
     */
    private array $events = [];

    /**
     * Add event
     *
     * @param \Tiktok\Tiktok\Model\Event\TiktokEvent $event
     * @return void
     */
    public function addEvent(TiktokEvent $event): void
    {
        $this->events[] = $event->getEvent();
    }

    /**
     * Clear events
     *
     * @return void
     */
    public function clearEvents(): void
    {
        $this->events = [];
    }

    /**
     * Prepare response
     *
     * @return array|array[]
     */
    public function prepareResponse(): array
    {
        if (!$this->hasEvents()) {
            return [];
        }

        return ['tiktok_events' => $this->getEvents()];
    }

    /**
     * Check if has events
     *
     * @return bool
     */
    public function hasEvents(): bool
    {
        return !empty($this->events);
    }

    /**
     * Return events
     *
     * @return array
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
