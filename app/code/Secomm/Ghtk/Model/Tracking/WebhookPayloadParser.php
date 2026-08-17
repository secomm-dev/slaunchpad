<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Tracking;

use Secomm\ShippingCore\Api\Tracking\TrackingUpdateInterface;
use Secomm\ShippingCore\Model\Tracking\TrackingUpdate;

/**
 * Parses a GHTK webhook payload into a TrackingUpdate (SL-017 / DEC-SL017-001
 * §4). Q-EXT: field names follow the GHTK docs; alternatives are accepted
 * defensively. Pure function — the controller stays thin and this class is
 * unit-testable.
 */
class WebhookPayloadParser
{
    public function __construct(
        private GhtkStatusMapper $statusMapper
    ) {
    }

    /**
     * @return TrackingUpdateInterface|null Null on structurally invalid payload.
     */
    public function parse(string $rawBody): ?TrackingUpdateInterface
    {
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return null;
        }

        $identifier = $this->firstNonEmpty($payload, ['label_id', 'tracking_code', 'tracking', 'order_code']);
        if ($identifier === null) {
            return null;
        }

        $carrierStatus = $this->firstNonEmpty($payload, ['status_id', 'status', 'status_code']);
        if ($carrierStatus === null) {
            return null;
        }

        return new TrackingUpdate(
            carrierCode: 'ghtk',
            trackingNumber: $identifier,
            normalizedStatus: $this->statusMapper->map($carrierStatus),
            carrierStatusCode: (string) $carrierStatus,
            carrierStatusMessage: $this->firstNonEmpty($payload, ['message', 'reason', 'description']),
            occurredAt: $this->occurredAt($payload),
            source: 'webhook',
            raw: $this->sanitize($payload)
        );
    }

    /**
     * Alternative tracking identifiers worth trying when the primary one does
     * not resolve to a Magento track (the track may store tracking_code while
     * the webhook sends label_id, or vice versa).
     *
     * @return string[]
     */
    public function identifierCandidates(array $payload): array
    {
        $candidates = [];
        foreach (['label_id', 'tracking_code', 'tracking', 'order_code'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $candidates[] = trim($value);
            } elseif (is_int($value)) {
                $candidates[] = (string) $value;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return array<string, mixed> Raw payload minus obviously sensitive keys.
     */
    public function sanitize(array $payload): array
    {
        unset($payload['token'], $payload['secret'], $payload['password']);

        return $payload;
    }

    private function firstNonEmpty(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function occurredAt(array $payload): ?int
    {
        foreach (['updated_at', 'timestamp', 'time'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_int($value) && $value > 0) {
                return $value;
            }
            if (is_string($value) && $value !== '') {
                $ts = strtotime($value);
                if ($ts !== false) {
                    return $ts;
                }
            }
        }

        return null;
    }
}
