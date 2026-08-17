<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Fee;

use Secomm\Ghtk\Model\Address\GhtkAddress;
use Secomm\Ghtk\Model\Address\PickupAddress;

/**
 * Assembles the GHTK fee API query params from the resolved destination,
 * pickup (origin-derived), weight and declared value (SL-015 / DEC-SL015-001:
 * mapping lives in a mapper, not in the API client).
 *
 * Pickup precedence (DEC-021): pick_address_id alone when present, otherwise
 * pick_province + pick_ward (pick_district optional).
 */
class FeeRequestMapper
{
    /**
     * @return array<string, string|int|float> GHTK fee query params.
     */
    public function map(GhtkAddress $dest, PickupAddress $pickup, int $weightGram, float $value, string $transport): array
    {
        $params = [
            'province' => $dest->province,
            'ward' => $dest->ward,
            'weight' => $weightGram,
            'transport' => $transport,
        ];
        if ($dest->district !== null) {
            $params['district'] = $dest->district;
        }
        if ($value > 0) {
            $params['value'] = $value;
        }

        if ($pickup->hasPickAddressId()) {
            $params['pick_address_id'] = $pickup->pickAddressId;
        } else {
            $params['pick_province'] = (string) $pickup->province;
            $params['pick_ward'] = (string) $pickup->ward;
            if ($pickup->district !== null) {
                $params['pick_district'] = $pickup->district;
            }
        }

        return $params;
    }

    /**
     * Stable identity of the pickup for the rate cache key (every parameter
     * that affects the fee must be keyed — AC-012).
     */
    public function pickupIdentity(PickupAddress $pickup): string
    {
        return $pickup->hasPickAddressId()
            ? 'paid:' . $pickup->pickAddressId
            : 'pp:' . $pickup->province . '|' . $pickup->ward;
    }
}
