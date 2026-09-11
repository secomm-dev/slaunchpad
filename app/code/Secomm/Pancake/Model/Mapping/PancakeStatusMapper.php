<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Model\Mapping;

use Secomm\FulfillmentCore\Api\FulfillmentStatusMapperInterface;
use Secomm\FulfillmentCore\Api\NormalizedFulfillmentStatus;
use Secomm\Pancake\Model\ServiceCode;

/**
 * Maps Pancake integer status → NormalizedFulfillmentStatus for timeline/comments.
 * Magento sales_order.status changes come only from admin Status Mapping table.
 */
class PancakeStatusMapper implements FulfillmentStatusMapperInterface
{
    public function getServiceCode(): string
    {
        return ServiceCode::CODE;
    }

    public function map(string $rawStatus): string
    {
        $code = (int) $rawStatus;

        return match ($code) {
            0, 17, 11, 20, 1 => NormalizedFulfillmentStatus::CONFIRMED,
            12, 13, 8 => NormalizedFulfillmentStatus::PACKING,
            9 => NormalizedFulfillmentStatus::WAITING_PICKUP,
            2 => NormalizedFulfillmentStatus::SHIPPED,
            3, 16 => NormalizedFulfillmentStatus::DELIVERED,
            4, 15 => NormalizedFulfillmentStatus::RETURNING,
            5 => NormalizedFulfillmentStatus::RETURNED,
            6, 7 => NormalizedFulfillmentStatus::CANCELLED,
            default => NormalizedFulfillmentStatus::UNKNOWN,
        };
    }
}
