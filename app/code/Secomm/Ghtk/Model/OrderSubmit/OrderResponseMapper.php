<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\OrderSubmit;

/**
 * Parses the GHTK Submit Order response into a typed
 * {@see GhtkCreateResponse} (TASK-BE5YD2) — the service decides the lifecycle
 * outcome from the kind + identity; no more null-for-every-failure.
 *
 * Official contract (SPIKE-A1DGPY §2 — submit-order-express):
 * - success=true → `order{partner_id, label, tracking_id, status_id, …}`;
 * - success=false + error_code=ORDER_ID_EXIST → `partner_id`, `ghtk_label`,
 *   `created`, `status` (duplicate recovery identity).
 *
 * Duplicate field positions are parsed defensively from BOTH the top level and
 * the `order` block — the exact runtime shape is NEEDS_RUNTIME_VERIFICATION
 * (TASK-44F7V7); anything unusable parses as MALFORMED/BUSINESS and hard-fails
 * downstream instead of silently recovering.
 */
class OrderResponseMapper
{
    /**
     * @param array $response Decoded GHTK response.
     */
    public function parse(array $response): GhtkCreateResponse
    {
        $success = $response['success'] ?? null;

        if ($success !== true && $success !== false) {
            return GhtkCreateResponse::malformed('missing or invalid success contract');
        }

        if ($success === false) {
            $errorCode = $this->scalar($response['error_code'] ?? null);
            $message = $this->text($response['message'] ?? ($response['error_message'] ?? null));

            if ($errorCode === GhtkCreateResponse::ERROR_ORDER_ID_EXIST) {
                return GhtkCreateResponse::duplicateExisting(
                    $this->scalar($this->pick($response, ['partner_id'])),
                    $this->scalar($this->pick($response, ['ghtk_label', 'label', 'label_id'])),
                    $this->scalar($this->pick($response, ['ghtk_label', 'tracking_id', 'tracking_code', 'tracking', 'label'])),
                    $this->scalar($this->pick($response, ['status', 'status_id'])),
                    $message
                );
            }

            return GhtkCreateResponse::businessRejection($errorCode, $message);
        }

        // success=true — normal creation; a usable shipment identity is REQUIRED.
        $order = is_array($response['order'] ?? null) ? $response['order'] : [];
        $label = $this->scalar($this->pick($order, ['label', 'label_id']));
        $tracking = $this->scalar($this->pick($order, ['tracking_id', 'tracking_code', 'tracking', 'label']));

        if (!$this->hasValue($label) && !$this->hasValue($tracking)) {
            // §18 — success without usable provider identity is unusable technical data.
            return GhtkCreateResponse::malformed('success response carries no usable shipment identity');
        }

        return GhtkCreateResponse::created(
            $this->scalar($order['partner_id'] ?? null),
            $label,
            $tracking,
            $this->scalar($order['status_id'] ?? ($order['status'] ?? null))
        );
    }

    /**
     * Look a key up in the response top level first, then the `order` block —
     * duplicate-recovery fields are documented without a fixed position.
     *
     * @param array $response
     * @param string[] $keys
     */
    private function pick(array $response, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $response)) {
                return $response[$key];
            }
            if (isset($response['order']) && is_array($response['order']) && array_key_exists($key, $response['order'])) {
                return $response['order'][$key];
            }
        }

        return null;
    }

    private function scalar(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return null;
    }

    private function text(mixed $value): ?string
    {
        $text = $this->scalar($value);

        return $text !== null ? mb_substr($text, 0, 200) : null;
    }

    private function hasValue(?string $value): bool
    {
        return $value !== null && $value !== '';
    }
}
