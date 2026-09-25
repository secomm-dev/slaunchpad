<?php

declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Event;

use Magento\Framework\Exception\LocalizedException;
use Tiktok\Tiktok\Api\EventProcessorInterface;
use Tiktok\Tiktok\Logger\TiktokLogger;
use Tiktok\Tiktok\Model\Api\TiktokApiClient;

/**
 * Event Processor
 */
class Processor implements EventProcessorInterface
{
    /**
     * @var \Tiktok\Tiktok\Logger\TiktokLogger
     */
    private TiktokLogger $logger;

    /**
     * @param \Tiktok\Tiktok\Logger\TiktokLogger $logger
     */
    public function __construct(
        TiktokLogger $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Process batch of events
     *
     * @param array $events
     * @param TiktokApiClient $apiClient
     * @return void
     * @throws LocalizedException
     */
    public function process(array $events, TiktokApiClient $apiClient): void
    {
        if (empty($events)) {
            return;
        }

        // Check memory usage before processing
        if ($this->isMemoryExceeded()) {
            throw new LocalizedException(__('Memory limit exceeded. Processing halted.'));
        }

        $websiteId = $events[0]->getWebsiteId();
        $batchStartTime = time();

        try {
            // Validate all events are for same website
            $this->validateEventBatch($events);

            // Convert event objects to arrays for API
            $eventData = array_map(function ($event) {
                return $event->jsonSerialize();
            }, $events);

            $this->logger->info('Sending batch to TikTok API', [
                'batch_size' => count($events),
                'website_id' => $websiteId
            ]);

            $response = $apiClient->trackEvents($eventData);

            if (!isset($response['code']) || $response['code'] !== 0) {
                throw new LocalizedException(
                    __('TikTok API Error: %1', $response['message'] ?? 'Unknown error')
                );
            }

            $this->logSuccessfulBatch($events, $batchStartTime);
        } catch (LocalizedException $e) {
            $this->handleFailedBatch($events, $e, $apiClient);
            throw $e;
        } catch (\Exception $e) {
            $this->handleFailedBatch($events, $e, $apiClient);
            throw new LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * Validate all events in batch are for same website
     *
     * @param array $events
     * @throws LocalizedException
     */
    private function validateEventBatch(array $events): void
    {
        $websiteId = $events[0]->getWebsiteId();
        foreach ($events as $event) {
            if ($event->getWebsiteId() !== $websiteId) {
                throw new LocalizedException(
                    __('Mixed website IDs in batch. Expected %1', $websiteId)
                );
            }
        }
    }

    /**
     * Check if memory usage exceeds limit
     *
     * @return bool
     */
    private function isMemoryExceeded(): bool
    {
        $memoryUsed = memory_get_usage(true);
        return ($memoryUsed >= Config::MAX_MEMORY_LIMIT);
    }

    /**
     * Log successful batch processing
     *
     * @param array $events
     * @param int $startTime
     */
    private function logSuccessfulBatch(array $events, int $startTime): void
    {
        $processingTime = time() - $startTime;
        $websiteId = $events[0]->getWebsiteId();

        $this->logger->info('Successfully processed event batch', [
            'batch_size' => count($events),
            'website_id' => $websiteId,
            'processing_time' => $processingTime
        ]);

        if ($this->logger->isDebug()) {
            foreach ($events as $event) {
                $this->logger->event('Event processed successfully', [
                    'event_id' => $event->getEventId(),
                    'event_name' => $event->getEventName(),
                    'website_id' => $websiteId
                ]);
            }
        }
    }

    /**
     * Handle failed batch processing
     *
     * @param array $events
     * @param \Exception $exception
     * @param TiktokApiClient $apiClient
     */
    private function handleFailedBatch(array $events, \Exception $exception, TiktokApiClient $apiClient): void
    {
        $websiteId = $events[0]->getWebsiteId();

        $this->logger->error('Failed to process event batch: ' . $exception->getMessage(), [
            'batch_size' => count($events),
            'website_id' => $websiteId
        ]);

        // If batch size is 1, we've already tried individual processing
        if (count($events) > 1) {
            $this->retryIndividualEvents($events, $apiClient);
        }
    }

    /**
     * Retry failed events individually
     *
     * @param array $events
     * @param TiktokApiClient $apiClient
     */
    private function retryIndividualEvents(array $events, TiktokApiClient $apiClient): void
    {
        foreach ($events as $event) {
            try {
                $this->process([$event], $apiClient);
            } catch (\Exception $e) {
                $this->logger->error('Failed to process individual event after retry', [
                    'event_id' => $event->getEventId(),
                    'event_name' => $event->getEventName(),
                    'website_id' => $event->getWebsiteId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
