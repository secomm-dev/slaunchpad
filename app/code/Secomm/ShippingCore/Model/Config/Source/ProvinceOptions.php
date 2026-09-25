<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 — VN provinces for the zone form, straight from the
 * Secomm_VietNamAddress canonical reference layer (VN_ADMIN_2025 level 1 — no hardcoded
 * geography, no directory-region sync dependency). Options carry the exact identity zones
 * are validated against (`VN-XX`); save-time validation stays authoritative (SPEC §4).
 * Labels are display-only: "name (VN-XX)".
 */
class ProvinceOptions implements OptionSourceInterface
{
    private VnAddressUnitProviderInterface $unitProvider;

    public function __construct(VnAddressUnitProviderInterface $unitProvider)
    {
        $this->unitProvider = $unitProvider;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->unitProvider->getByLevel(VnSchemes::VN_ADMIN_2025, 1) as $province) {
            $options[] = [
                'value' => $province->getCode(),
                'label' => sprintf('%s (%s)', $province->getNameVi(), $province->getCode()),
            ];
        }
        usort($options, static function (array $a, array $b): int {
            return strcmp((string) $a['label'], (string) $b['label']);
        });

        return $options;
    }
}
