<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Tracking;

use Psr\Log\LoggerInterface;
use Secomm\ShippingCore\Api\Tracking\CarrierTrackingFetcherInterface;
use Secomm\ShippingCore\Api\Tracking\CarrierTrackingProcessorInterface;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;
use Secomm\ShippingCore\Model\ResourceModel\CarrierTrackingState\CollectionFactory as StateCollectionFactory;

/**
 * TASK-7AJ3K8 — carrier-agnostic tracking reconciliation orchestration (moved out of
 * Secomm_Ghtk\TrackingRefreshService): query the shared tracking state for ONE carrier,
 * exclude terminal states, keep stale entries (never synced or older than the threshold),
 * cap the batch, fetch per tracking number through the carrier fetcher and feed the SAME
 * CarrierTrackingProcessorInterface pipeline as the webhook. Per-item failures are logged
 * and skipped — one broken tracking number never stops the batch; the service never throws.
 *
 * Carrier config policy (enabled flag, staleness threshold) stays in the carrier — the
 * service receives already-resolved scalar inputs (carrierCode via DI, threshold per call).
 */
class TrackingReconciliationService
{
    public function __construct(
        private readonly StateCollectionFactory $stateCollectionFactory,
        private readonly CarrierTrackingFetcherInterface $fetcher,
        private readonly CarrierTrackingProcessorInterface $processor,
        private readonly string $carrierCode,
        private readonly LoggerInterface $logger,
        private readonly int $batchSize = 50
    ) {
    }

    /**
     * @param int $staleThresholdSeconds entries last synced before (now - threshold) are stale
     * @return int Number of states successfully re-synced.
     */
    public function refresh(int $staleThresholdSeconds): int
    {
        $threshold = time() - max(1, $staleThresholdSeconds);

        $collection = $this->stateCollectionFactory->create();
        $collection->addFieldToFilter('carrier_code', $this->carrierCode)
            ->addFieldToFilter('normalized_status', ['nin' => NormalizedTrackingStatus::terminal()])
            ->setPageSize($this->batchSize)
            ->setCurPage(1);
        // Stale filter in PHP: NULL last_synced_at (never synced) or older than threshold.
        $states = array_values(array_filter(
            $collection->getItems(),
            function ($state) use ($threshold) {
                $synced = $state->getLastSyncedAt();
                return $synced === null || $synced === '' || strtotime((string) $synced) <= $threshold;
            }
        ));

        $synced = 0;
        foreach ($states as $state) {
            $trackingNumber = (string) $state->getTrackingNumber();
            try {
                $update = $this->fetcher->fetch($trackingNumber);
                if ($update === null) {
                    continue;
                }
                if ($this->processor->process($update)) {
                    $synced++;
                }
            } catch (\Throwable $e) {
                $this->logger->warning(
                    'Tracking reconciliation failed for one shipment; continuing.',
                    ['carrier_code' => $this->carrierCode, 'tracking_number' => $trackingNumber, 'exception' => $e->getMessage()]
                );
            }
        }

        return $synced;
    }
}
