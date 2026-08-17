<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Tracking;

use Secomm\ShippingCore\Api\Tracking\CarrierStatusMapperInterface;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;

/**
 * GHTK shipment status → NormalizedTrackingStatus (SL-017 / DEC-SL017-001 §5).
 * THE single mapping table for the carrier — webhook and Tracking API both
 * use it; nothing else in the module maps status codes.
 *
 * Q-EXT: the numeric codes below follow the GHTK documentation; exact payload
 * fields/codes must be verified against sandbox before go-live (mapper is the
 * only class to touch). Unknown codes map to UNKNOWN — never a failure.
 */
class GhtkStatusMapper implements CarrierStatusMapperInterface
{
    /**
     * Numeric status id → normalized status.
     */
    private const MAP = [
        '-1' => NormalizedTrackingStatus::CANCELLED,      // Hủy đơn hàng
        '1' => NormalizedTrackingStatus::CREATED,          // Chưa tiếp nhận
        '2' => NormalizedTrackingStatus::PICKING,          // Đã tiếp nhận
        '3' => NormalizedTrackingStatus::PICKED_UP,        // Đã lấy hàng / nhập kho
        '4' => NormalizedTrackingStatus::IN_TRANSIT,       // Đã điều phối giao hàng
        '5' => NormalizedTrackingStatus::DELIVERED,        // Đã giao hàng
        '6' => NormalizedTrackingStatus::DELIVERY_FAILED,  // Gặp lỗi khi giao hàng
        '7' => NormalizedTrackingStatus::PICKING,          // Đã điều phối lấy hàng
        '8' => NormalizedTrackingStatus::PICKING,          // Đang lấy hàng
        '9' => NormalizedTrackingStatus::PICKING,          // Chưa lấy được hàng
        '10' => NormalizedTrackingStatus::OUT_FOR_DELIVERY,// Đang giao hàng
        '11' => NormalizedTrackingStatus::DELIVERY_FAILED, // Chưa giao được hàng
        '12' => NormalizedTrackingStatus::RETURNING,       // Chuyển hoàn
        '13' => NormalizedTrackingStatus::RETURNED,        // Đã giao hoàn
        '20' => NormalizedTrackingStatus::RETURNING,       // Đang trả hàng (shop)
    ];

    public function map(mixed $carrierStatusCode): string
    {
        $key = is_int($carrierStatusCode) ? (string) $carrierStatusCode : trim((string) $carrierStatusCode);
        if ($key !== '' && isset(self::MAP[$key])) {
            return self::MAP[$key];
        }

        return NormalizedTrackingStatus::UNKNOWN;
    }
}
