<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Cron;

use Tiktok\Tiktok\Logger\TiktokLogger;
use Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder;
use Tiktok\Tiktok\Model\Event\Config;
use Tiktok\Tiktok\Model\Queue\EventConsumer;
use Exception;

class ProcessEventQueue
{
    /**
     * Init dependencies
     *
     * @param \Tiktok\Tiktok\Logger\TiktokLogger $logger
     * @param \Tiktok\Tiktok\Model\Queue\EventConsumer $eventConsumer
     * @param \Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder $tiktokApiClientBuilder
     */
    public function __construct(
        private readonly TiktokLogger $logger,
        private readonly EventConsumer $eventConsumer,
        private readonly TiktokApiClientBuilder $tiktokApiClientBuilder
    ) {
    }

    /**
     * Execute the cron job
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $this->logger->info('Starting TikTok event processing');

            $groupedMessages = $this->eventConsumer->collectMessages(Config::BATCH_SIZE);

            if (empty($groupedMessages)) {
                $this->logger->info('No messages to process');
                return;
            }

            $processedCount = 0;
            foreach ($groupedMessages as $websiteId => $data) {
                $msgCount = count($data['messages']);
                $this->logger->info('Processing website batch', [
                    'website_id' => $websiteId,
                    'message_count' => $msgCount]);

                try {
                    $this->tiktokApiClientBuilder->create((int) $websiteId)->sendEvents($data['messages']);
                    $processedCount += $msgCount;
                } catch (Exception $e) {
                    $this->logger->error('Error processing website batch: ' . $e->getMessage(), [
                        'website_id' => $websiteId]);
                }
            }

            $this->logger->info('Completed TikTok event processing', [
                'total_processed' => $processedCount,
                'website_count' => count($groupedMessages)]);

        } catch (Exception $e) {
            $this->logger->critical('Error in event queue processing: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()]);
        }
    }
}
