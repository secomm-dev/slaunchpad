<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Tracking;

use Secomm\Ghn\Api\Client\GhnApiClientInterface;
use Secomm\Ghn\Model\Client\GhnEndpoints;
use Secomm\Ghn\Model\Carrier\Ghn;
use Secomm\ShippingCore\Api\Tracking\CarrierTrackingFetcherInterface;
use Secomm\ShippingCore\Api\Tracking\TrackingUpdateInterface;
use Secomm\ShippingCore\Model\Tracking\TrackingUpdate;

/**
 * TASK-GKHXY1 (GHN-E1) — pulls the CURRENT provider status for one GHN order via the Order Info
 * API (`v2/shipping-order/detail?order_code=`) and maps it through the SAME
 * {@see GhnStatusMapper} the webhook uses — one mapping table, two sources, never diverging.
 *
 * Feeds the ShippingCore reconciliation cycle (stale `secomm_carrier_tracking_state` rows) and
 * any admin/support refresh tooling.
 *
 * Return semantics (contract): TrackingUpdate = fresh observation; null = provider responded but
 * nothing usable (unknown order / empty status); any Throwable = fetch failure (the
 * reconciliation service logs and continues with the next row). GET is retried by the shared
 * client (read-only).
 */
class GhnTrackingFetcher implements CarrierTrackingFetcherInterface
{
    private const OPERATION = 'order_info';

    public function __construct(
        private readonly GhnApiClientInterface $apiClient,
        private readonly GhnStatusMapper $statusMapper
    ) {
    }

    public function fetch(string $trackingNumber): ?TrackingUpdateInterface
    {
        $orderCode = trim($trackingNumber);
        if ($orderCode === '') {
            return null;
        }

        $data = $this->apiClient->get(self::OPERATION, GhnEndpoints::ORDER_INFO, ['order_code' => $orderCode]);

        $status = trim((string) ($data['status'] ?? ''));
        if ($status === '') {
            return null;
        }

        return new TrackingUpdate(
            carrierCode: Ghn::CARRIER_CODE,
            trackingNumber: $orderCode,
            normalizedStatus: $this->statusMapper->map($status),
            carrierStatusCode: $status,
            carrierStatusMessage: null,
            occurredAt: null,
            source: 'api',
            raw: $this->sanitizeDetail($data, $status)
        );
    }

    /**
     * TASK-PWHG0V (GHN-E3-A) — curated, PII-free detail fields for the Magento tracking display.
     * The processor never persists `raw`; this is request-scoped transport only. Every field is
     * an explicit whitelist key — the raw ~120-field provider body never travels further.
     *
     * @return array{status: string, expected_delivery_time?: string, log?: list<array{status: string, time: string}>}
     */
    private function sanitizeDetail(array $data, string $status): array
    {
        $raw = ['status' => $status];

        $expected = trim((string) ($data['expected_delivery_time'] ?? ''));
        if ($expected !== '') {
            $raw['expected_delivery_time'] = $expected;
        }

        $log = [];
        foreach ($data['log'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entryStatus = trim((string) ($entry['status'] ?? ''));
            if ($entryStatus === '') {
                continue;
            }
            $log[] = [
                'status' => $entryStatus,
                'time' => trim((string) ($entry['updated_date'] ?? $entry['time'] ?? $entry['created_at'] ?? '')),
            ];
        }
        if ($log !== []) {
            $raw['log'] = $log;
        }

        return $raw;
    }
}
