<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Queue;

use Tiktok\Tiktok\Logger\TiktokLogger;
use Exception;
use InvalidArgumentException;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\MessageQueue\ConsumerInterface;
use Magento\Framework\MessageQueue\EnvelopeInterface;
use Magento\Framework\MessageQueue\QueueInterface;
use Magento\Framework\MessageQueue\QueueRepository;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * TikTok Queue Event Consumer
 */
class EventConsumer implements ConsumerInterface
{
    /**
     * TikTok Event Track Queue Name
     */
    private const QUEUE_NAME = 'tiktok.event.track';

    /**
     * @var \Tiktok\Tiktok\Logger\TiktokLogger
     */
    private TiktokLogger $logger;

    /**
     * @var \Magento\Framework\MessageQueue\QueueRepository
     */
    private QueueRepository $queueRepository;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var \Magento\Framework\App\DeploymentConfig
     */
    private DeploymentConfig $deploymentConfig;

    /**
     * @var \Magento\Framework\MessageQueue\QueueInterface
     */
    private $queue;

    /**
     * @param \Tiktok\Tiktok\Logger\TiktokLogger $logger
     * @param \Magento\Framework\MessageQueue\QueueRepository $queueRepository
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Magento\Framework\App\DeploymentConfig $deploymentConfig
     */
    public function __construct(
        TiktokLogger $logger,
        QueueRepository $queueRepository,
        SerializerInterface $serializer,
        DeploymentConfig $deploymentConfig
    ) {
        $this->logger = $logger;
        $this->queueRepository = $queueRepository;
        $this->serializer = $serializer;
        $this->deploymentConfig = $deploymentConfig;
    }

    /**
     * Collect messages from queue and group by website ID
     *
     * @param int $maxMessages
     *
     * @return array
     */
    public function collectMessages(int $maxMessages): array
    {
        $messagesByWebsite = [];
        $processedCount = 0;

        try {
            $connectionName = $this->getConnectionName();
            $queue = $this->getQueue();
            $this->logger->info('Starting message collection', [
                'max_messages' => $maxMessages,
                'connection' => $connectionName,
                'queue' => self::QUEUE_NAME]);
            while ($processedCount < $maxMessages) {
                $envelope = $queue->dequeue();

                if ($envelope === null) {
                    break;
                }

                $messageData = $this->decodeMessage($envelope);
                if ($messageData === null) {
                    continue;
                }

                $websiteId = $messageData['website_id'];

                if (!isset($messagesByWebsite[$websiteId])) {
                    $messagesByWebsite[$websiteId] = [
                        'messages' => [],
                        'envelopes' => []];
                }

                // Store the actual message body
                $messagesByWebsite[$websiteId]['messages'][] = $messageData['data'];
                $messagesByWebsite[$websiteId]['envelopes'][] = $envelope;
                $queue->acknowledge($envelope);
                $processedCount++;
            }

            $this->logger->info('Message collection complete', [
                'total_websites' => count($messagesByWebsite),
                'total_messages' => $processedCount]);

            return $messagesByWebsite;

        } catch (Exception $e) {
            $this->logger->critical('Error collecting messages: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Get queue connection name based on configuration
     *
     * @return string
     */
    private function getConnectionName(): string
    {
        $amqpConfig = $this->deploymentConfig->get('queue/amqp');
        return !empty($amqpConfig) ? 'amqp' : 'db';
    }

    /**
     * Retrieve queue
     *
     * @return QueueInterface
     */
    private function getQueue(): QueueInterface
    {
        if (!$this->queue) {
            $this->queue = $this->queueRepository->get($this->getConnectionName(), self::QUEUE_NAME);
        }
        return $this->queue;
    }

    /**
     * Decode message from envelope
     *
     * @param EnvelopeInterface $envelope
     *
     * @return array|null
     */
    private function decodeMessage(EnvelopeInterface $envelope): ?array
    {
        try {
            $message = json_decode($this->serializer->unserialize($envelope->getBody()), true);
            if (!isset($message['data']) || !isset($message['website_id'])) {
                $this->logger->error('Invalid message structure');
                return null;
            }
            return $message;
        } catch (Exception $e) {
            $this->logger->error('Failed to decode message: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Handle failed messages - blacklist specific messages and requeue others
     *
     * @param string[] $messageIds Messages to blacklist
     * @param array $envelopes Group of message envelopes
     *
     * @return void
     */
    public function handleFailedMessages(array $messageIds, array $envelopes): void
    {
        foreach ($envelopes as $envelope) {
            try {
                $messageData = $this->decodeMessage($envelope);
                if ($messageData === null) {
                    $this->logger->error('Could not decode message for failure handling');
                    continue;
                }

                $messageId = $messageData['metadata']['message_id'] ?? null;

                if ($messageId === null) {
                    $this->logger->error('Message ID missing from message metadata');
                    continue;
                }

                if (in_array($messageId, $messageIds)) {
                    // Reject without requeue for blacklisted messages
                    $this->getQueue()->reject($envelope, false);
                    $this->logger->error('Blacklisted message', [
                        'message_id' => $messageId,
                        'event_id' => $messageData['metadata']['event_id'] ?? 'unknown']);
                } else {
                    // Requeue other messages in the group
                    $this->getQueue()->reject($envelope, true);
                    $this->logger->info('Requeued message for retry', [
                        'message_id' => $messageId,
                        'event_id' => $messageData['metadata']['event_id'] ?? 'unknown']);
                }
            } catch (Exception $e) {
                $this->logger->error('Error handling message failure: ' . $e->getMessage());
                // If we can't process the failure handling, reject with requeue to be safe
                $this->getQueue()->reject($envelope, true);
            }
        }
    }

    /**
     * Connects to a queue, consumes a message on the queue, and invoke a method to process the message contents.
     *
     * @param int|null $maxNumberOfMessages if not specified - process all queued incoming messages and terminate,
     *      otherwise terminate execution after processing the specified number of messages
     *
     * @return void
     * @since 103.0.0
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function process($maxNumberOfMessages = 0): void
    {
        $this->logger->info('Consumer attempted to process a messages
        from tiktok-event-track queue. This is not supported.');
    }
}
