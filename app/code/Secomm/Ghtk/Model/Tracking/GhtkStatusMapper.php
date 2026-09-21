<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
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
 * TASK-KCXKVR — mapping corrected against the OFFICIAL GHTK status table
 * (api.ghtk.vn webhook docs, VI + EN consistent; evidence SPIKE-A1DGPY §6).
 * The previous table was built from an unofficial legacy list and mislabeled
 * 6/7/9/11/12/13 and missed 21 entirely.
 *
 * Official code → meaning (verbatim) → normalized:
 *  -1  Hủy đơn hàng                                → CANCELLED (terminal)
 *   1  Chưa tiếp nhận                              → CREATED
 *   2  Đã tiếp nhận                                → PICKING
 *   3  Đã lấy hàng/Đã nhập kho                     → PICKED_UP
 *   4  Đã điều phối giao hàng/Đang giao hàng       → OUT_FOR_DELIVERY
 *   5  Đã giao hàng/Chưa đối soát                  → DELIVERED (terminal)
 *   6  Đã đối soát                                 → DELIVERED — financial post-delivery step; terminal
 *      delivered state is PRESERVED, never downgraded (processor is sticky).
 *   7  Không lấy được hàng                         → DELIVERY_FAILED — pickup failure; non-terminal:
 *      GHTK may still resolve/reject the order (reasons 110–115), the follow-up
 *      webhook tells. No PICKUP_FAILED state exists; this is the closest
 *      representable failed-state. Raw code + reason remain on the update.
 *   8  Hoãn lấy hàng                               → PICKING (delay inside the pickup loop — reattempt)
 *   9  Không giao được hàng                        → DELIVERY_FAILED (reattempt possible — non-terminal by design)
 *  10  Delay giao hàng                             → OUT_FOR_DELIVERY (still in the delivery loop, late)
 *  11  Đã đối soát công nợ trả hàng                → RETURNED (terminal — return reconciliation completed)
 *  12  Đã điều phối lấy hàng/Đang lấy hàng         → PICKING (start of the PICKUP flow — also a cancellable state)
 *  13  Đơn hàng bồi hoàn                           → RETURNED (compensation/lost — best existing terminal;
 *      raw code + message carry the real semantics)
 *  20  Đang trả hàng (COD cầm hàng đi trả)         → RETURNING
 *  21  Đã trả hàng (COD đã trả xong hàng)          → RETURNED (terminal — previously MISSING → UNKNOWN)
 *
 * Shipper-reported statuses 123/127/128/45/49/410 are INFORMATIONAL ONLY per the
 * official docs ("không phải trạng thái của đơn hàng" — a shipper 123 can be
 * corrected to 127) — deliberately NOT mapped → UNKNOWN. Never invent lifecycle
 * transitions from informational events.
 *
 * Unknown codes map to UNKNOWN — never a failure.
 */
class GhtkStatusMapper implements CarrierStatusMapperInterface
{
    /**
     * Numeric status id → normalized status.
     */
    private const MAP = [
        '-1' => NormalizedTrackingStatus::CANCELLED,       // Hủy đơn hàng
        '1' => NormalizedTrackingStatus::CREATED,          // Chưa tiếp nhận
        '2' => NormalizedTrackingStatus::PICKING,          // Đã tiếp nhận
        '3' => NormalizedTrackingStatus::PICKED_UP,        // Đã lấy hàng/Đã nhập kho
        '4' => NormalizedTrackingStatus::OUT_FOR_DELIVERY, // Đã điều phối giao hàng/Đang giao hàng
        '5' => NormalizedTrackingStatus::DELIVERED,        // Đã giao hàng/Chưa đối soát
        '6' => NormalizedTrackingStatus::DELIVERED,        // Đã đối soát — post-delivery financial; terminal preserved
        '7' => NormalizedTrackingStatus::DELIVERY_FAILED,  // Không lấy được hàng (pickup failure — non-terminal)
        '8' => NormalizedTrackingStatus::PICKING,          // Hoãn lấy hàng (pickup delay — reattempt)
        '9' => NormalizedTrackingStatus::DELIVERY_FAILED,  // Không giao được hàng (reattempt possible)
        '10' => NormalizedTrackingStatus::OUT_FOR_DELIVERY,// Delay giao hàng (still delivering)
        '11' => NormalizedTrackingStatus::RETURNED,        // Đã đối soát công nợ trả hàng (terminal return)
        '12' => NormalizedTrackingStatus::PICKING,         // Đã điều phối lấy hàng/Đang lấy hàng
        '13' => NormalizedTrackingStatus::RETURNED,        // Đơn hàng bồi hoàn (compensation — terminal)
        '20' => NormalizedTrackingStatus::RETURNING,       // Đang trả hàng (COD cầm hàng đi trả)
        '21' => NormalizedTrackingStatus::RETURNED,        // Đã trả hàng (COD đã trả xong hàng)
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
