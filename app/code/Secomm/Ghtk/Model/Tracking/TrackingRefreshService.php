<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Tracking;

use Psr\Log\LoggerInterface;
use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\GhtkApiClient;
use Secomm\Ghtk\Model\GhtkApiException;
use Secomm\ShippingCore\Api\Tracking\CarrierTrackingProcessorInterface;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;
use Secomm\ShippingCore\Model\ResourceModel\CarrierTrackingState\CollectionFactory as StateCollectionFactory;
use Secomm\ShippingCore\Model\Tracking\TrackingUpdate;

/**
 * Tracking Status API reconciliation (SL-017 / DEC-SL017-001 §5) — the
 * FALLBACK path: stale, non-terminal GHTK tracking states are re-fetched and
 * fed through the SAME CarrierTrackingProcessorInterface pipeline as the
 * webhook. Never polls everything, never throws (per-item failures are logged
 * and skipped). Disabled via config (webhook is primary).
 */
class TrackingRefreshService
{
    private const BATCH_SIZE = 50;

    public function __construct(
        private StateCollectionFactory $stateCollectionFactory,
        private GhtkApiClient $apiClient,
        private GhtkStatusMapper $statusMapper,
        private CarrierTrackingProcessorInterface $processor,
        private GhtkConfig $config,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @return int Number of states successfully re-synced.
     */
    public function refresh(): int
    {
        if (!$this->config->isTrackingRefreshEnabled()) {
            return 0;
        }

        $threshold = time() - ($this->config->getTrackingRefreshThresholdHours() * 3600);
        $collection = $this->stateCollectionFactory->create();
        $collection->addFieldToFilter('carrier_code', 'ghtk')
            ->addFieldToFilter('normalized_status', ['nin' => NormalizedTrackingStatus::terminal()])
            ->setPageSize(self::BATCH_SIZE)
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
            try {
                if ($this->refreshOne((string) $state->getTrackingNumber())) {
                    $synced++;
                }
            } catch (GhtkApiException $e) {
                $this->logger->warning(
                    'GHTK tracking refresh failed for one shipment; continuing.',
                    ['tracking_number' => (string) $state->getTrackingNumber(), 'exception' => $e->getMessage()]
                );
            }
        }

        return $synced;
    }

    /**
     * @throws GhtkApiException On API failure (caller decides to continue).
     */
    private function refreshOne(string $trackingNumber): bool
    {
        $response = $this->apiClient->getOrderStatus($trackingNumber);
        $order = is_array($response['order'] ?? null) ? $response['order'] : [];

        $carrierStatus = $order['status'] ?? ($order['status_id'] ?? null);
        if ($carrierStatus === null || (is_string($carrierStatus) && trim($carrierStatus) === '')) {
            return false;
        }

        $update = new TrackingUpdate(
            carrierCode: 'ghtk',
            trackingNumber: $trackingNumber,
            normalizedStatus: $this->statusMapper->map($carrierStatus),
            carrierStatusCode: is_string($carrierStatus) ? trim($carrierStatus) : (string) $carrierStatus,
            carrierStatusMessage: isset($order['message']) && is_string($order['message']) ? $order['message'] : null,
            occurredAt: null, // status API carries no reliable observation time — pipeline guards ordering
            source: 'api',
            raw: []
        );

        return $this->processor->process($update);
    }
}
