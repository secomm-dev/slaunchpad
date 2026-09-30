<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRepositoryInterface;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierZoneIndex;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 (final TL verification §6) — zone options for the Carrier
 * Coverage picker:
 *   - ENABLED zones → selectable ("Label (CODE)");
 *   - DISABLED zones that are still PERSISTED-REFERENCED by a registered carrier → remain
 *     visible, clearly marked "Label (CODE) — Disabled" (an admin editing coverage must
 *     never silently lose sight of a persisted selection; the value can only leave the
 *     config by the admin explicitly removing it);
 *   - DISABLED and unreferenced zones → NOT offered (cannot be newly selected).
 *
 * Runtime semantics untouched: a disabled zone never matches (CarrierZoneMatcher gate) —
 * the marker is visibility only.
 */
class ReferencableZoneCodes implements OptionSourceInterface
{
    private CanonicalZoneRepositoryInterface $zoneRepository;

    private CarrierZoneIndex $zoneIndex;

    public function __construct(CanonicalZoneRepositoryInterface $zoneRepository, CarrierZoneIndex $zoneIndex)
    {
        $this->zoneRepository = $zoneRepository;
        $this->zoneIndex = $zoneIndex;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->zoneRepository->getAll() as $zone) {
            $code = $zone->getCode();
            if ($zone->isEnabled()) {
                $options[] = ['value' => $code, 'label' => sprintf('%s (%s)', $zone->getLabel(), $code)];
                continue;
            }
            if ($this->zoneIndex->carriersForZone($code) !== []) {
                $options[] = ['value' => $code, 'label' => sprintf('%s (%s) — Disabled', $zone->getLabel(), $code)];
            }
        }

        return $options;
    }
}
