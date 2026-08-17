<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\OrderSubmit;

/**
 * Defensively maps the GHTK Submit Order response (SL-016). The exact field
 * contract is Q-EXT (sandbox verify pending, DEC-024) — any drift is fixed
 * here only. Expects success=true plus an order block carrying at least a
 * label identifier; tracking falls back to the label id.
 */
class OrderResponseMapper
{
    public function success(array $response): bool
    {
        return ($response['success'] ?? null) === true;
    }

    public function message(array $response): string
    {
        $message = $response['message'] ?? ($response['error_message'] ?? '');
        if (is_string($message) && $message !== '') {
            return mb_substr($message, 0, 200);
        }

        return 'Unknown GHTK error.';
    }

    public function labelId(array $response): ?string
    {
        $order = $response['order'] ?? null;
        foreach ([($order['label'] ?? null), ($order['label_id'] ?? null)] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
            if (is_int($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    public function trackingNumber(array $response): ?string
    {
        $order = $response['order'] ?? null;
        foreach ([($order['tracking_code'] ?? null), ($order['tracking'] ?? null), ($order['label'] ?? null)] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
            if (is_int($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }
}
