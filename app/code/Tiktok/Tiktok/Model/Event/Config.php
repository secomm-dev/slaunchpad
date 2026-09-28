<?php

declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Event;

class Config
{
    /**
     * Queue Configuration
     */
    public const TOPIC_NAME = 'tiktok.event.track';
    public const CONSUMER_NAME = 'tiktok.event.track.consumer';

    /**
     * Processing Configuration
     */
    public const BATCH_SIZE = 500;
    public const MAX_RETRIES = 1;
    public const EVENT_TIMEOUT = 172800; // 48 hours in seconds
    public const MAX_EXECUTION_TIME = 300; // 5 minutes in seconds
    public const MAX_IDLE_TIME = 60;

    /**
     * Memory Management
     */
    public const MAX_MEMORY_LIMIT = 256 * 1024 * 1024; // 256MB
}
