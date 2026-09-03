<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model\Delivery;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use Secomm\Tracking\Model\Config;
use Secomm\Tracking\Model\DeliveryLog;
use Secomm\Tracking\Model\DeliveryLogFactory;
use Secomm\Tracking\Model\Event\TrackingEvent;
use Secomm\Tracking\Model\ResourceModel\TrackingEvent\Collection;
use Secomm\Tracking\Model\ResourceModel\TrackingEvent\CollectionFactory;
use Secomm\Tracking\Model\TrackingEventQueue;
use Secomm\Tracking\Model\Vendor\VendorAdapterInterface;
use Secomm\Tracking\Model\Vendor\VendorSendException;

/**
 * FEAT-31X6N2 / SPEC §6.3 — cron-side outbox flush.
 *
 * Batch ≤50 pending rows due for delivery → vendor adapters → delivery log.
 * Backoff: 1m/5m/30m/2h/6h (5 attempts) then failed. Non-retryable errors
 * (auth/pixel 4xx) fail immediately. Stale rows (>7 days pending) are failed
 * with a log line so the outbox cannot grow unbounded.
 */
class FlushService
{
    private const BATCH_SIZE = 50;

    private const MAX_ATTEMPTS = 5;

    /** int seconds after each failed attempt (index = attempt number). */
    private const BACKOFF = [60, 300, 1800, 7200, 21600];

    private const STALE_SECONDS = 604800; // 7 days

    /**
     * @param CollectionFactory $collectionFactory
     * @param DeliveryLogFactory $deliveryLogFactory
     * @param VendorAdapterInterface[] $adapters keyed by vendor
     * @param Config $config
     * @param LoggerInterface $logger
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly DeliveryLogFactory $deliveryLogFactory,
        private readonly array $adapters,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * One flush pass. Called by the every-minute cron; exits fast when idle.
     */
    public function execute(): int
    {
        if (!$this->config->isEnabled()) {
            return 0;
        }

        $this->failStaleRows();

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('status', TrackingEventQueue::STATUS_PENDING)
            ->addFieldToFilter(
                ['next_attempt_at', 'next_attempt_at'],
                [['null' => true], ['lteq' => $this->dateTime->gmtDate()]]
            )
            ->setOrder('entity_id', Collection::SORT_ORDER_ASC)
            ->setPageSize(self::BATCH_SIZE)
            ->setCurPage(1);

        $processed = 0;
        foreach ($collection->getItems() as $row) {
            /** @var TrackingEventQueue $row */
            $this->processRow($row);
            $processed++;
        }

        return $processed;
    }

    private function processRow(TrackingEventQueue $row): void
    {
        $adapter = $this->adapters[$row->getData('vendor')] ?? null;
        if ($adapter === null) {
            $this->finish($row, TrackingEventQueue::STATUS_FAILED, 'no adapter for vendor');
            return;
        }

        try {
            $event = $this->hydrateEvent((string)$row->getData('payload'));
        } catch (\JsonException $e) {
            $this->finish($row, TrackingEventQueue::STATUS_FAILED, 'unparsable payload');
            return;
        }

        try {
            $result = $adapter->send($event);
            $this->logDelivery($row, $result->httpStatus, $result->responseSummary);
            $this->finish($row, TrackingEventQueue::STATUS_SENT, $result->responseSummary);
        } catch (VendorSendException $e) {
            $this->logDelivery($row, $e->httpStatus, $e->responseSummary);
            $this->retryOrFail($row, $e);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Secomm Tracking: flush error',
                ['event_id' => $row->getData('event_id'), 'error' => $e->getMessage()]
            );
            $this->retryOrFail($row, new VendorSendException($e->getMessage(), null, true, null));
        }
    }

    private function retryOrFail(TrackingEventQueue $row, VendorSendException $e): void
    {
        $attempts = (int)$row->getData('attempts') + 1;

        if (!$e->retryable || $attempts >= self::MAX_ATTEMPTS) {
            $this->finish($row, TrackingEventQueue::STATUS_FAILED, $e->getMessage());
            return;
        }

        $delay = self::BACKOFF[min($attempts, count(self::BACKOFF)) - 1] ?? self::BACKOFF[0];
        $row->setData('attempts', $attempts);
        $row->setData('next_attempt_at', $this->dateTime->gmtDate(null, time() + $delay));
        $row->save();
    }

    private function finish(TrackingEventQueue $row, string $status, ?string $summary): void
    {
        $row->setData('status', $status);
        $row->setData('next_attempt_at', null);
        $row->save();
    }

    private function failStaleRows(): void
    {
        $staleBefore = $this->dateTime->gmtDate(null, time() - self::STALE_SECONDS);
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('status', TrackingEventQueue::STATUS_PENDING)
            ->addFieldToFilter('created_at', ['lt' => $staleBefore]);

        foreach ($collection->getItems() as $row) {
            $this->finish($row, TrackingEventQueue::STATUS_FAILED, 'stale pending > 7 days');
        }
    }

    /**
     * @throws \JsonException
     */
    private function hydrateEvent(string $payloadJson): TrackingEvent
    {
        $data = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);

        return TrackingEvent::fromArray($data);
    }

    private function logDelivery(TrackingEventQueue $row, ?int $httpStatus, ?string $summary): void
    {
        if (!$this->config->isDebugLog()) {
            return;
        }

        $log = $this->deliveryLogFactory->create();
        $log->setData([
            'event_id' => $row->getData('event_id'),
            'event_name' => $row->getData('event_name'),
            'vendor' => $row->getData('vendor'),
            'direction' => 'server',
            'http_status' => $httpStatus,
            'response_summary' => $summary !== null ? $this->mask($summary) : null,
        ]);
        $log->save();
    }

    /**
     * Defense-in-depth: truncate dotted-quad IPs in summaries; hashes are already
     * one-way so they pass through (spec §10).
     */
    private function mask(string $summary): string
    {
        return (string)preg_replace('/(\d{1,3}\.\d{1,3})\.\d{1,3}\.\d{1,3}/', '$1.*.*', $summary);
    }
}
