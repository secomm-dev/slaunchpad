<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Address;

use Secomm\ShippingCore\Api\Address\CanonicalZoneInterface;
use Secomm\ShippingCore\Api\Address\CanonicalZoneMatcherInterface;

/**
 * TASK-MD2BD3 Phase B — deterministic canonical zone matcher;
 * @see CanonicalZoneMatcherInterface.
 */
final class CanonicalZoneMatcher implements CanonicalZoneMatcherInterface
{
    /**
     * @inheritDoc
     */
    public function matches(
        CanonicalZoneInterface $zone,
        string $destinationProvinceCode,
        ?string $destinationWardCode
    ): bool {
        if (!$zone->isEnabled()) {
            return false;
        }

        $provinceCodes = $zone->getIncludeProvinceCodes();
        if ($provinceCodes !== [] && !in_array($destinationProvinceCode, $provinceCodes, true)) {
            return false;
        }

        $includeWards = $zone->getIncludeWardCodes();
        if ($includeWards !== []
            && ($destinationWardCode === null || !in_array($destinationWardCode, $includeWards, true))) {
            return false;
        }

        $excludeWards = $zone->getExcludeWardCodes();
        if ($destinationWardCode !== null && in_array($destinationWardCode, $excludeWards, true)) {
            return false;
        }

        return true;
    }
}
