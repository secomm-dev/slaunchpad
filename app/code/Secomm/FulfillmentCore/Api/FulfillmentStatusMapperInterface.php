<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Api;

/**
 * Many-to-one vendor raw status → NormalizedFulfillmentStatus (timeline/comments).
 * Magento sales_order.status changes use admin Status Mapping table, not this mapper.
 */
interface FulfillmentStatusMapperInterface
{
    /**
     * Stable service key matching MapperPool DI item name.
     */
    public function getServiceCode(): string;

    /**
     * Map vendor raw status string to a NormalizedFulfillmentStatus constant.
     * Unmapped codes must return NormalizedFulfillmentStatus::UNKNOWN (never throw).
     *
     * @param string $rawStatus Vendor status code or label
     */
    public function map(string $rawStatus): string;
}
