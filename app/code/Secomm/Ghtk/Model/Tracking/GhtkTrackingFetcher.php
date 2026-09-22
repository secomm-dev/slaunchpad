<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Tracking;

use Secomm\Ghtk\Model\GhtkApiClient;
use Secomm\Ghtk\Model\GhtkApiException;
use Secomm\ShippingCore\Api\Tracking\CarrierTrackingFetcherInterface;
use Secomm\ShippingCore\Api\Tracking\CarrierStatusMapperInterface;
use Secomm\ShippingCore\Api\Tracking\TrackingUpdateInterface;
use Secomm\ShippingCore\Model\Tracking\TrackingUpdate;

/**
 * TASK-7AJ3K8 — the GHTK {@see CarrierTrackingFetcherInterface}: tracking number → GHTK
 * status API → normalized TrackingUpdate (through the carrier-owned GhtkStatusMapper).
 * Carrier status knowledge stays HERE; the shared reconciliation owns only the loop.
 *
 * @throws GhtkApiException On transport/API failure — the reconciliation logs and continues.
 */
class GhtkTrackingFetcher implements CarrierTrackingFetcherInterface
{
    public function __construct(
        private readonly GhtkApiClient $apiClient,
        private readonly CarrierStatusMapperInterface $statusMapper
    ) {
    }

    public function fetch(string $trackingNumber): ?TrackingUpdateInterface
    {
        $response = $this->apiClient->getOrderStatus($trackingNumber);
        $order = is_array($response['order'] ?? null) ? $response['order'] : [];

        $carrierStatus = $order['status'] ?? ($order['status_id'] ?? null);
        if ($carrierStatus === null || (is_string($carrierStatus) && trim($carrierStatus) === '')) {
            return null;
        }

        return new TrackingUpdate(
            carrierCode: 'ghtk',
            trackingNumber: $trackingNumber,
            normalizedStatus: $this->statusMapper->map($carrierStatus),
            carrierStatusCode: is_string($carrierStatus) ? trim($carrierStatus) : (string) $carrierStatus,
            carrierStatusMessage: isset($order['message']) && is_string($order['message']) ? $order['message'] : null,
            occurredAt: null, // status API carries no reliable observation time — pipeline guards ordering
            source: 'api',
            raw: []
        );
    }
}
