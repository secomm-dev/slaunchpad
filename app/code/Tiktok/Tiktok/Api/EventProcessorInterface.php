<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Api;

use Tiktok\Tiktok\Model\Api\TiktokApiClient;

interface EventProcessorInterface
{
    /**
     * Process a batch of events for a specific website
     *
     * @param array $events
     * @param TiktokApiClient $apiClient
     * @return void
     */
    public function process(array $events, TiktokApiClient $apiClient): void;
}
