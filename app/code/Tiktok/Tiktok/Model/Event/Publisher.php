<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Event;

use Exception;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Tiktok\Tiktok\Api\EventDataInterface;
use Tiktok\Tiktok\Api\EventPublisherInterface;
use Tiktok\Tiktok\Logger\TiktokLogger;

class Publisher implements EventPublisherInterface
{
    /**
     * @var \Magento\Framework\MessageQueue\PublisherInterface
     */
    private PublisherInterface $publisher;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var \Tiktok\Tiktok\Logger\TiktokLogger
     */
    private TiktokLogger $logger;

    /**
     * @param \Magento\Framework\MessageQueue\PublisherInterface $publisher
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Tiktok\Tiktok\Logger\TiktokLogger $logger
     */
    public function __construct(
        PublisherInterface  $publisher,
        SerializerInterface $serializer,
        TiktokLogger $logger
    ) {
        $this->publisher = $publisher;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * Publish event to queue
     *
     * @param EventDataInterface $eventData
     *
     * @return void
     */
    public function publish(EventDataInterface $eventData): void
    {
        try {
            $eventData->setIsS2SEvent(true);
            $serializedData = $this->serializer->serialize($eventData);
            $this->publisher->publish(Config::TOPIC_NAME, $serializedData);
            $this->logEvent($eventData, $serializedData);
        } catch (Exception $e) {
            $this->logger->error('Failed to publish event: ' . $e->getMessage(), [
                'event_id' => $eventData->getEventId(),
                'event_name' => $eventData->getEventName(),
                'website_id' => $eventData->getWebsiteId()]);
        }
    }

    /**
     * Log event data
     *
     * @param EventDataInterface $eventData
     * @param string $serializedData
     *
     * @return void
     */
    private function logEvent(EventDataInterface $eventData, string $serializedData): void
    {
        $this->logger->info('Event published', [
            'event_id' => $eventData->getEventId(),
            'event_name' => $eventData->getEventName(),
            'website_id' => $eventData->getWebsiteId()]);
        if ($eventData->getScopeManager()->getLogLevel() === 'debug') {
            $this->logger->debug('Event published', [
                'event_id' => $eventData->getEventId(),
                'event_name' => $eventData->getEventName(),
                'website_id' => $eventData->getWebsiteId(),
                'event_data' => $serializedData]);
        }
    }
}
