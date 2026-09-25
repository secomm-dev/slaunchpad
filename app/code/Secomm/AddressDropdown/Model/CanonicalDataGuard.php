<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\AddressDropdown\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Api\Data\RegionInterface;
use Secomm\AddressDropdown\Model\Region;
use Secomm\AddressDropdown\Model\RegionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel as RegionResource;

/**
 * TASK-SEC-A4 — fail-safe guard for CANONICAL Vietnam address data.
 *
 * The canonical VN hierarchy (regions + cities of country VN) is managed EXCLUSIVELY through
 * the Secomm_VietNamAddress import workflow: stable codes, parent_city_id, profile membership
 * and provider mapping (and the string-matched TableRate dest_city constraint) all hang off
 * those rows. A manual admin CRUD or import mutation that silently renamed/deleted/cascaded
 * them would corrupt live checkout data — so every mutation path through the command layer
 * consults this guard and is REJECTED with an explicit message. Non-VN countries keep the
 * full generic engine (create/edit/delete unaffected).
 */
class CanonicalDataGuard
{
    private const CANONICAL_COUNTRY_ID = 'VN';

    private RegionFactory $regionFactory;

    private RegionResource $regionResource;

    public function __construct(
        RegionFactory $regionFactory,
        RegionResource $regionResource
    ) {
        $this->regionFactory = $regionFactory;
        $this->regionResource = $regionResource;
    }

    /**
     * A region row is mutable only when it is NOT canonical VN data: either an explicitly
     * non-VN country, or (transitional data) a VN row that provably does not exist in the
     * canonical hierarchy is still protected — VN is canonical by country identity.
     *
     * @throws LocalizedException when the mutation must be rejected
     */
    public function assertRegionMutatable(?int $regionId, ?string $countryId): void
    {
        if (!$this->isCanonicalVnRegion($regionId, $countryId)) {
            return;
        }
        $label = $regionId !== null && $regionId > 0
            ? sprintf('region #%d', $regionId)
            : 'a new VN region';

        throw new LocalizedException($this->message($label));
    }

    /**
     * A city is mutable only when its parent region is not canonical VN data.
     *
     * @throws LocalizedException when the mutation must be rejected
     */
    public function assertCityMutatable(?int $cityId, ?int $regionId): void
    {
        if ($regionId === null || $regionId <= 0 || !$this->isCanonicalVnRegion($regionId, null)) {
            return;
        }
        $label = $cityId !== null && $cityId > 0
            ? sprintf('city #%d (parent region #%d)', $cityId, $regionId)
            : sprintf('a new city (parent region #%d)', $regionId);

        throw new LocalizedException($this->message($label));
    }

    private function isCanonicalVnRegion(?int $regionId, ?string $countryId): bool
    {
        // An explicitly non-VN country is never canonical — no lookup, mutable immediately.
        if ($countryId !== null) {
            return strtoupper($countryId) === self::CANONICAL_COUNTRY_ID;
        }

        if ($regionId === null || $regionId <= 0) {
            return false;
        }

        // Country unknown — resolve it from the persisted row.
        $region = $this->regionFactory->create();
        $this->regionResource->load($region, $regionId, RegionInterface::REGION_ID);
        if (!(int) $region->getData(RegionInterface::REGION_ID)) {
            return false;
        }

        return strtoupper((string) $region->getData('country_id')) === self::CANONICAL_COUNTRY_ID;
    }

    private function message(string $entity): Phrase
    {
        return __(
            'VN canonical address data (%1) is managed exclusively by the Secomm_VietNamAddress '
            . 'import workflow — manual edit/delete/import is rejected to protect stable codes, '
            . 'parent_city_id, profile membership and carrier (TableRate) city references.',
            $entity
        );
    }
}
