<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Tracking;

use Secomm\Ghn\Model\Carrier\Ghn;
use Secomm\ShippingCore\Api\Tracking\TrackingUpdateInterface;
use Secomm\ShippingCore\Model\Tracking\TrackingUpdate;

/**
 * TASK-GKHXY1 (GHN-E1) — parses the GHN webhook payload (PascalCase, all fields always present
 * — zero value when unset) into a carrier-neutral {@see TrackingUpdate}.
 *
 * Only `Type=switch_status` payloads carry a lifecycle transition and are converted. Other
 * documented event types (`create`, `update_weight`, `update_cod`, `update_fee`,
 * `update_payment_type`, `cod`, `update_partial_return`) return null — the webhook controller
 * acknowledges them HTTP 200 without touching the tracking pipeline (GHN-E2 may consume the
 * fee/COD events later).
 *
 * Dedupe: GHN's documented key is `OrderCode + Type + Time`. Re-delivery of the SAME payload is
 * absorbed downstream by the tracking processor (same normalized status + same raw code → skip),
 * so no event table is added; the trade-off (same-status newer-Time rewrite) is identical state.
 *
 * The returned `raw` array is sanitized: only safe diagnostic fields are kept (no shipper
 * phone/Pod URL/shop identifiers — PII/credential hygiene, §28).
 */
class WebhookPayloadParser
{
    /** The only webhook event type that carries a status transition. */
    public const TYPE_SWITCH_STATUS = 'switch_status';

    /** Every event type documented in the current GHN webhook contract (contract matrix §11). */
    public const KNOWN_TYPES = [
        'create',
        'switch_status',
        'update_weight',
        'update_cod',
        'update_fee',
        'update_payment_type',
        'cod',
        'update_partial_return',
    ];

    /** Raw keys preserved for audit — everything else (PII, credentials) is dropped. */
    private const SAFE_RAW_KEYS = ['OrderCode', 'ClientOrderCode', 'Status', 'Type', 'Time', 'Reason', 'ReasonCode'];

    public function __construct(private readonly GhnStatusMapper $statusMapper)
    {
    }

    public function parse(array $payload): ?TrackingUpdateInterface
    {
        if (($payload['Type'] ?? '') !== self::TYPE_SWITCH_STATUS) {
            return null;
        }

        $orderCode = trim((string) ($payload['OrderCode'] ?? ''));
        $status = trim((string) ($payload['Status'] ?? ''));
        if ($orderCode === '' || $status === '') {
            return null;
        }

        $occurredAt = null;
        $time = trim((string) ($payload['Time'] ?? ''));
        if ($time !== '') {
            $parsed = strtotime($time);
            $occurredAt = $parsed !== false ? $parsed : null;
        }

        return new TrackingUpdate(
            carrierCode: Ghn::CARRIER_CODE,
            trackingNumber: $orderCode,
            normalizedStatus: $this->statusMapper->map($status),
            carrierStatusCode: $status,
            carrierStatusMessage: $this->resolveMessage($payload),
            occurredAt: $occurredAt,
            source: 'webhook',
            raw: $this->sanitize($payload)
        );
    }

    private function resolveMessage(array $payload): ?string
    {
        $parts = array_filter(
            [(string) ($payload['Description'] ?? ''), (string) ($payload['Reason'] ?? '')],
            static fn (string $part): bool => $part !== ''
        );

        $message = implode(' — ', $parts);

        return $message !== '' ? $message : null;
    }

    /**
     * @return array<string, mixed> safe diagnostic subset of the raw payload
     */
    private function sanitize(array $payload): array
    {
        $raw = [];
        foreach (self::SAFE_RAW_KEYS as $key) {
            $value = $payload[$key] ?? null;
            if ($value !== null && $value !== '') {
                $raw[$key] = $value;
            }
        }

        return $raw;
    }
}
