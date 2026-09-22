<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Tracking;

use Secomm\ShippingCore\Api\Tracking\CarrierStatusMapperInterface;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;

/**
 * TASK-GKHXY1 (GHN-E1) — THE single GHN-status → normalized-status mapper. Both the webhook
 * parser and the Order-Info fetcher resolve through THIS table (one source of truth — never two
 * diverging mappers).
 *
 * Vocabulary: 23 documented GHN order statuses (developer.ghn.vn master-data/order-status,
 * re-verified 2026-09-15 against the contract matrix terminal list + legacy reference):
 *
 *   created-side : ready_to_pick
 *   pickup       : picking, money_collect_picking, picked
 *   transport    : storing, transporting, sorting
 *   delivery     : delivering, money_collect_delivering, delivered, delivery_fail
 *   return       : waiting_to_return, return, return_transporting, return_sorting,
 *                  returning, return_fail, returned
 *   failure-side : cancel, exception, damage, lost, scrap
 *
 * Unknown / unrecognized provider statuses map to UNKNOWN (never silently IN_TRANSIT) — the raw
 * code is preserved by the caller for debugging.
 */
class GhnStatusMapper implements CarrierStatusMapperInterface
{
    /**
     * Explicit, exhaustive mapping table — no silent first-match, no fuzzy resolution.
     *
     * @var array<string, string>
     */
    private const MAP = [
        // creation / pickup
        'ready_to_pick' => NormalizedTrackingStatus::CREATED,
        'picking' => NormalizedTrackingStatus::PICKING,
        'money_collect_picking' => NormalizedTrackingStatus::PICKING,
        'picked' => NormalizedTrackingStatus::PICKED_UP,
        // transport
        'storing' => NormalizedTrackingStatus::IN_TRANSIT,
        'transporting' => NormalizedTrackingStatus::IN_TRANSIT,
        'sorting' => NormalizedTrackingStatus::IN_TRANSIT,
        // delivery
        'delivering' => NormalizedTrackingStatus::OUT_FOR_DELIVERY,
        'money_collect_delivering' => NormalizedTrackingStatus::OUT_FOR_DELIVERY,
        'delivered' => NormalizedTrackingStatus::DELIVERED,
        'delivery_fail' => NormalizedTrackingStatus::DELIVERY_FAILED,
        // return lifecycle
        'waiting_to_return' => NormalizedTrackingStatus::RETURNING,
        'return' => NormalizedTrackingStatus::RETURNING,
        'return_transporting' => NormalizedTrackingStatus::RETURNING,
        'return_sorting' => NormalizedTrackingStatus::RETURNING,
        'returning' => NormalizedTrackingStatus::RETURNING,
        'return_fail' => NormalizedTrackingStatus::RETURNING,
        'returned' => NormalizedTrackingStatus::RETURNED,
        // failure-side terminal
        'cancel' => NormalizedTrackingStatus::CANCELLED,
        // delivery-side failures (rare / exceptional) — materially delivery failures; the raw
        // code is preserved by the caller for support. `exception` ("does not fit into the
        // process") is genuinely ambiguous → UNKNOWN.
        // r2 taxonomy (TASK-GKHXY1): lost/damage are DISTINCT terminal outcomes — goods lost
        // vs goods damaged lead to materially different downstream handling (claims/compensation).
        // `scrap` = goods written off by the carrier (docs terminal list) → grouped with damage.
        'lost' => NormalizedTrackingStatus::LOST,
        'damage' => NormalizedTrackingStatus::DAMAGED,
        'scrap' => NormalizedTrackingStatus::DAMAGED,
        'exception' => NormalizedTrackingStatus::UNKNOWN,
    ];

    public function map(mixed $carrierStatusCode): string
    {
        $key = strtolower(trim((string) $carrierStatusCode));

        return self::MAP[$key] ?? NormalizedTrackingStatus::UNKNOWN;
    }
}
