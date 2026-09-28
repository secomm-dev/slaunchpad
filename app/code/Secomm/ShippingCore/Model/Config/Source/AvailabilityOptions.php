<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\ShippingCore\Model\CarrierCoverage\Availability;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 — Shipping Coverage availability options (directive §9). Values
 * are string-aligned with the runtime DestinationScope enum (3 values since TASK-R8WR1R);
 * every option here is honored by CarrierEligibilityEvaluator.
 */
class AvailabilityOptions implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Availability::ALL, 'label' => 'All Vietnam (no zone restriction)'],
            ['value' => Availability::SELECTED_ZONES, 'label' => 'Only Selected Zones'],
            ['value' => Availability::ALL_EXCEPT_SELECTED_ZONES, 'label' => 'All Except Selected Zones'],
        ];
    }
}
