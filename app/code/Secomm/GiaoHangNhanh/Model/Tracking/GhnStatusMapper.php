<?php declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\GiaoHangNhanh\Model\Tracking;

use Secomm\ShippingCore\Api\Tracking\CarrierStatusMapperInterface;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;

/**
 * Maps raw GHN status codes onto NormalizedTrackingStatus constants (SL-017 / DEC-SL017-001).
 */
class GhnStatusMapper implements CarrierStatusMapperInterface
{
    private const MAP = [
        'ready_to_pick'            => NormalizedTrackingStatus::CREATED,
        'picking'                  => NormalizedTrackingStatus::PICKING,
        'money_collect_picking'    => NormalizedTrackingStatus::PICKING,
        'picked'                   => NormalizedTrackingStatus::PICKED_UP,
        'storing'                  => NormalizedTrackingStatus::IN_TRANSIT,
        'transporting'             => NormalizedTrackingStatus::IN_TRANSIT,
        'sorting'                  => NormalizedTrackingStatus::IN_TRANSIT,
        'delivering'               => NormalizedTrackingStatus::OUT_FOR_DELIVERY,
        'money_collect_delivering' => NormalizedTrackingStatus::OUT_FOR_DELIVERY,
        'delivered'                => NormalizedTrackingStatus::DELIVERED,
        'delivery_fail'            => NormalizedTrackingStatus::DELIVERY_FAILED,
        'waiting_to_return'        => NormalizedTrackingStatus::RETURNING,
        'return'                   => NormalizedTrackingStatus::RETURNING,
        'return_transporting'      => NormalizedTrackingStatus::RETURNING,
        'return_sorting'           => NormalizedTrackingStatus::RETURNING,
        'returning'                => NormalizedTrackingStatus::RETURNING,
        'return_fail'              => NormalizedTrackingStatus::RETURNING,
        'returned'                 => NormalizedTrackingStatus::RETURNED,
        'cancel'                   => NormalizedTrackingStatus::CANCELLED,
    ];

    public function map(mixed $carrierStatusCode): string
    {
        return self::MAP[(string)$carrierStatusCode] ?? NormalizedTrackingStatus::UNKNOWN;
    }
}
