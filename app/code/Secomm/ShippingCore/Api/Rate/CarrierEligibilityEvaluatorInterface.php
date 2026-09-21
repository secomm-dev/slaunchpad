<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Rate;

use Secomm\ShippingCore\Api\Rate\CarrierEligibilityResultInterface;

/**
 * TASK-MD2BD3 Phase B (architecture v10 §35.1) — evaluates carrier eligibility for a canonical
 * destination based on the carrier's configured DestinationScope + Allowed Zone Codes.
 *
 * Chỉ trả lời "carrier eligible cho destination này không" — KHÔNG quyết:
 * RateSourceMode, AddressResolutionPolicy, fallback dispatch, provider mapping, carrier API call.
 *
 * Ownership: ShippingCore owns generic evaluation; zone definitions belong to the shared
 * CanonicalZoneRegistry; carrier configuration supplies DestinationScope + AllowedZoneCodes.
 */
interface CarrierEligibilityEvaluatorInterface
{
    /**
     * @param string $destinationScope DestinationScope::ALL | DestinationScope::SELECTED_ZONES
     * @param string[] $allowedZoneCodes zone codes từ carrier configuration
     * @param string $destinationProvinceCode canonical VN province code
     * @param string|null $destinationWardCode canonical VN ward code; null khi không có
     */
    public function evaluate(
        string $destinationScope,
        array $allowedZoneCodes,
        string $destinationProvinceCode,
        ?string $destinationWardCode
    ): CarrierEligibilityResultInterface;
}
