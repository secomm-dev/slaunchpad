<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Tracking;

/**
 * Carrier status → NormalizedTrackingStatus mapping contract (SL-017).
 * One implementation per carrier module (e.g. GhtkStatusMapper) — the shared
 * core never hard-codes carrier status codes.
 */
interface CarrierStatusMapperInterface
{
    /**
     * @param mixed $carrierStatusCode Raw carrier status (code, id or label).
     * @return string A NormalizedTrackingStatus constant; UNKNOWN when unmapped.
     */
    public function map(mixed $carrierStatusCode): string;
}
