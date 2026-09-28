<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetType;

/**
 * TASK-WY6WP5 — coverage target TYPE options for the Shipping Coverage grid. Only the
 * IMPLEMENTED types are offered (P1: CARRIER) — the reserved METHOD constant is not a
 * selectable/displayed value until a future task implements it.
 */
class CoverageTargetTypeOptions implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (CoverageTargetType::all() as $type) {
            if (CoverageTargetType::isImplemented($type)) {
                $options[] = ['value' => $type, 'label' => $this->label($type)];
            }
        }

        return $options;
    }

    private function label(string $type): string
    {
        return match ($type) {
            CoverageTargetType::CARRIER => 'Carrier',
            CoverageTargetType::METHOD => 'Method',
            default => $type,
        };
    }
}
