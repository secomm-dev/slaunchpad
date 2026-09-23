<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Tracking;

use Secomm\ShippingCore\Api\Tracking\TrackingUpdateInterface;
use Secomm\ShippingCore\Model\Tracking\TrackingUpdate;

/**
 * Parses a GHTK webhook payload into a TrackingUpdate (SL-017 / DEC-SL017-001 §4).
 *
 * TASK-KCXKVR — the OFFICIAL webhook sample posts
 * `application/x-www-form-urlencoded` (label_id=…&partner_id=…&status_id=5&…)
 * while a JSON sample also appears in the docs: BOTH formats are accepted here.
 * `action_time` (ISO 8601, e.g. 2016-11-02T12:18:39+07:00) is parsed into
 * `occurredAt` for out-of-order protection; invalid/missing → null (the update
 * is still processed — ShippingCore ordering guards handle nulls).
 *
 * Pure function — the controller stays thin and this class is unit-testable.
 */
class WebhookPayloadParser
{
    public function __construct(
        private GhtkStatusMapper $statusMapper
    ) {
    }

    /**
     * Raw request body → payload array. JSON first (structured), then the
     * official form-urlencoded format. Null when neither parses.
     *
     * @return array<string, mixed>|null
     */
    public function decode(string $rawBody): ?array
    {
        $trimmed = trim($rawBody);
        if ($trimmed === '') {
            return null;
        }

        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            $json = json_decode($trimmed, true);
            if (is_array($json)) {
                return $json;
            }
        }

        $form = [];
        parse_str($trimmed, $form);

        return $form !== [] ? $form : null;
    }

    /**
     * Raw request body → TrackingUpdate (JSON or form-urlencoded). Null on a
     * structurally invalid payload.
     */
    public function parse(string $rawBody): ?TrackingUpdateInterface
    {
        $payload = $this->decode($rawBody);

        return $payload !== null ? $this->parsePayload($payload) : null;
    }

    /**
     * Payload array → TrackingUpdate. Null when the identifier or status is missing.
     *
     * @param array<string, mixed> $payload
     */
    public function parsePayload(array $payload): ?TrackingUpdateInterface
    {
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
            carrierStatusMessage: $this->statusMessage($payload),
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
     * @param array<string, mixed> $payload
     * @return string[]
     */
    public function identifierCandidates(array $payload): array
    {
        $candidates = [];
        foreach (['label_id', 'tracking_code', 'tracking', 'order_code', 'partner_id'] as $key) {
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
     * @param array<string, mixed> $payload
     * @return array<string, mixed> Raw payload minus obviously sensitive keys.
     */
    public function sanitize(array $payload): array
    {
        unset($payload['token'], $payload['secret'], $payload['password']);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
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

    /**
     * Human-readable reason for the update: `reason` (official field) first,
     * then generic message alternatives. TRACK-API style `status_text` accepted
     * too so both ingestion paths share this parser's message semantics.
     *
     * @param array<string, mixed> $payload
     */
    private function statusMessage(array $payload): ?string
    {
        return $this->firstNonEmpty($payload, ['reason', 'message', 'status_text', 'description']);
    }

    /**
     * Official update time is `action_time` (ISO 8601 with timezone). Fallbacks
     * kept for JSON-sample variants. Invalid/missing → null — never a rejection.
     *
     * @param array<string, mixed> $payload
     */
    private function occurredAt(array $payload): ?int
    {
        foreach (['action_time', 'updated_at', 'timestamp', 'time'] as $key) {
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
