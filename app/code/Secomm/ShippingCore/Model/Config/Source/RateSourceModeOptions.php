<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\ShippingCore\Api\Rate\RateSourceMode;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 — Rate Source Mode options for the Shipping Coverage screen
 * (relocated from the GHN system.xml group; labels preserved). Values come from the
 * ShippingCore contract enum — the single owner of the rate-orchestration vocabulary.
 */
class RateSourceModeOptions implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => RateSourceMode::CARRIER_ONLY, 'label' => 'Carrier Only (realtime rate, no fallback)'],
            ['value' => RateSourceMode::CARRIER_WITH_FALLBACK, 'label' => 'Carrier With Fallback (realtime rate, shared fallback for eligible outcomes)'],
            ['value' => RateSourceMode::FALLBACK_ONLY, 'label' => 'Fallback Only (ShippingCore short-circuits the realtime rate)'],
        ];
    }
}
