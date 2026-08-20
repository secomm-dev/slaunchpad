<?php declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model\Tracking;

use Secomm\ShippingCore\Api\Tracking\CarrierStatusMapperInterface;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;

/**
 * Maps raw Ahamove status and sub-status combinations onto NormalizedTrackingStatus constants (SL-017 / DEC-SL017-001).
 * Supports composite inputs in the format "STATUS:SUB_STATUS" (e.g. "COMPLETED:FAILED", "IN PROCESS:IN_RETURN").
 */
class AhamoveStatusMapper implements CarrierStatusMapperInterface
{
    private const DIRECT_MAP = [
        'IDLE'       => NormalizedTrackingStatus::CREATED,
        'ASSIGNING'  => NormalizedTrackingStatus::PICKING,
        'IN PROCESS' => NormalizedTrackingStatus::OUT_FOR_DELIVERY,
        'COMPLETED'  => NormalizedTrackingStatus::DELIVERED,
        'CANCELLED'  => NormalizedTrackingStatus::CANCELLED,
    ];

    public function map(mixed $carrierStatusCode): string
    {
        $raw = strtoupper(trim((string)$carrierStatusCode));

        if (str_contains($raw, ':')) {
            [$status, $subStatus] = explode(':', $raw, 2);
            $status = trim($status);
            $subStatus = trim($subStatus);

            if ($status === 'COMPLETED' || $status === 'IN PROCESS') {
                return match ($subStatus) {
                    'FAILED'    => NormalizedTrackingStatus::DELIVERY_FAILED,
                    'IN_RETURN' => NormalizedTrackingStatus::RETURNING,
                    'RETURNED'  => NormalizedTrackingStatus::RETURNED,
                    default     => self::DIRECT_MAP[$status] ?? NormalizedTrackingStatus::UNKNOWN,
                };
            }

            return self::DIRECT_MAP[$status] ?? NormalizedTrackingStatus::UNKNOWN;
        }

        return self::DIRECT_MAP[$raw] ?? NormalizedTrackingStatus::UNKNOWN;
    }
}
