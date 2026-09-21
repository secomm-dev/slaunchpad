<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Address;

/**
 * TASK-MD2BD3 Phase B (architecture v10 §35.2) — deterministic zone membership matcher.
 *
 * Tách bạch matching logic khỏi zone VO: CanonicalZone chỉ là data; matcher quyết
 * zone có khớp canonical destination (province + ward) không.
 *
 * Matching precedence (fail-closed, xác định):
 *  1. zone disabled → false
 *  2. provinceCodes non-empty + province KHÔNG match → false
 *  3. includeWardCodes non-empty + ward KHÔNG trong → false
 *  4. includeWardCodes rỗng → không có positive ward restriction (pass qua)
 *  5. ward trong excludeWardCodes → false (exclude wins)
 *  6. Còn lại → true
 *
 * Canonical codes only — KHÔNG localized text, KHÔNG provider IDs, KHÔNG fuzzy.
 */
interface CanonicalZoneMatcherInterface
{
    /**
     * @param CanonicalZoneInterface $zone zone cần kiểm tra
     * @param string $destinationProvinceCode canonical VN province code (VN-XX)
     * @param string|null $destinationWardCode canonical VN ward code (VNA25-*); null khi không có
     * @return bool true khi zone khớp destination
     */
    public function matches(
        CanonicalZoneInterface $zone,
        string $destinationProvinceCode,
        ?string $destinationWardCode
    ): bool;
}
