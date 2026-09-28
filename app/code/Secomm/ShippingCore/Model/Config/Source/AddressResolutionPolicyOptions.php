<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\ShippingCore\Api\Address\AddressResolutionPolicy;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 — Address Resolution Policy options for the Shipping Coverage
 * screen (relocated from the GHN system.xml group; labels preserved). Values come from the
 * ShippingCore contract enum — the shared orchestration policy owner.
 */
class AddressResolutionPolicyOptions implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => AddressResolutionPolicy::STRICT, 'label' => 'Strict (hide the carrier when the canonical destination is ambiguous)'],
            ['value' => AddressResolutionPolicy::FALLBACK, 'label' => 'Fallback (hide the carrier; leave resolution to the fallback layer)'],
            ['value' => AddressResolutionPolicy::PICK_PRIMARY, 'label' => 'Pick Primary (merchant opt-in: use the curated primary PRE-2025 candidate)'],
        ];
    }
}
