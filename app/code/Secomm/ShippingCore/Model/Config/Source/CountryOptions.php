<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 — P1 is Vietnam-only: a single fixed option for the zone form's
 * disabled Country display. This is a display constant, NOT the start of a multi-country
 * architecture (directive §2/§15) — the canonical VN_ADMIN_2025 codes already carry the VN
 * identity; nothing is persisted from this field.
 */
class CountryOptions implements OptionSourceInterface
{
    public const VIETNAM = 'VN';

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::VIETNAM, 'label' => 'Vietnam (VN)'],
        ];
    }
}
