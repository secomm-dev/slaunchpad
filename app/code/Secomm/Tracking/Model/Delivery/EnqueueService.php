<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model\Delivery;

use Psr\Log\LoggerInterface;
use Secomm\Tracking\Model\Config;
use Secomm\Tracking\Model\Event\TrackingEvent;
use Secomm\Tracking\Model\ResourceModel\TrackingEvent as TrackingEventResource;
use Secomm\Tracking\Model\TrackingEventQueue;
use Secomm\Tracking\Model\TrackingEventQueueFactory;
use Secomm\Tracking\Model\Vendor\VendorAdapterInterface;

/**
 * FEAT-31X6N2 / SPEC §6.3 — the ONLY write path observers use.
 *
 * Insert-only (request scope): no HTTP, no vendor calls. UNIQUE (event_id, vendor)
 * collapses duplicate enqueues (state machine re-fire, IPN + return double confirm).
 * Tracking failures must never disturb the order flow — everything is caught.
 */
class EnqueueService
{
    /**
     * @param TrackingEventResource $resource
     * @param TrackingEventQueueFactory $queueFactory
     * @param VendorAdapterInterface[] $adapters keyed by vendor
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly TrackingEventResource $resource,
        private readonly TrackingEventQueueFactory $queueFactory,
        private readonly array $adapters,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Enqueue the event to every configured vendor. Safe to call from observers.
     */
    public function enqueue(TrackingEvent $event, ?string $scopeCode = null): void
    {
        if (!$this->config->isEnabled($scopeCode)) {
            return;
        }

        $marketingConsent = (bool)($event->consent['marketing'] ?? false);
        if (!$marketingConsent) {
            return; // consent gate — server-side marketing events are dropped (spec §8)
        }

        foreach ($this->adapters as $vendorKey => $adapter) {
            try {
                $this->enqueueToVendor($event, $vendorKey, $adapter, $scopeCode);
            } catch (\Throwable $e) {
                // Never propagate into the order/creditmemo save flow.
                $this->logger->error(
                    'Secomm Tracking: enqueue failed',
                    ['event_id' => $event->eventId, 'vendor' => $vendorKey, 'error' => $e->getMessage()]
                );
            }
        }
    }

    private function enqueueToVendor(
        TrackingEvent $event,
        string $vendorKey,
        VendorAdapterInterface $adapter,
        ?string $scopeCode
    ): void {
        $status = TrackingEventQueue::STATUS_PENDING;
        if (!$adapter->isConfigured($scopeCode) || $adapter->mapEventName($event->event) === null) {
            $status = TrackingEventQueue::STATUS_SKIPPED;
        }

        $row = $this->queueFactory->create();
        $row->setData([
            'event_name' => $event->event,
            'event_id' => $event->eventId,
            'vendor' => $vendorKey,
            'payload' => $event->toJson(),
            'status' => $status,
            'attempts' => 0,
        ]);

        $this->resource->save($row);
    }
}
