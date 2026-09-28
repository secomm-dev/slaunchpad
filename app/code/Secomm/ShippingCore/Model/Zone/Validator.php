<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Zone;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Secomm\ShippingCore\Api\Address\CanonicalZoneInterface;
use Secomm\ShippingCore\Model\Address\CanonicalZone;
use Secomm\ShippingCore\Model\ResourceModel\Zone as ZoneResource;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — canonical zone validation BEFORE persistence (SPEC §4).
 * Rejects (never silently drops): empty/invalid code, empty label, empty list entries,
 * unknown province/ward codes (VN_ADMIN_2025 reference layer), include-wards belonging to a
 * NON-included province (unreachable by the matcher's province gate), duplicate code.
 *
 * Composition: repository (uniqueness) + VnAddressUnitProvider (canonical existence).
 * The validator checks VALUES only — the enabled flag is orthogonal (a disabled zone is
 * valid data).
 */
final class Validator
{
    private const CODE_PATTERN = '/^[A-Z0-9_-]+$/';

    private ZoneResource $zoneResource;

    private VnAddressUnitProviderInterface $unitProvider;

    public function __construct(
        ZoneResource $zoneResource,
        VnAddressUnitProviderInterface $unitProvider
    ) {
        $this->zoneResource = $zoneResource;
        $this->unitProvider = $unitProvider;
    }

    /**
     * Full canonical validation. Lists are normalized (trim + drop-empty + dedupe) and the
     * NORMALIZED zone is returned for persistence — normalization has ONE owner (this class),
     * so cosmetic whitespace can never fail or leak through a save.
     *
     * @throws LocalizedException first failure wins, with the offending code named
     */
    public function validate(CanonicalZoneInterface $zone, ?int $excludeZoneId = null): CanonicalZoneInterface
    {
        $code = strtoupper(trim($zone->getCode()));
        $label = trim($zone->getLabel());

        if ($code === '') {
            throw new LocalizedException(new Phrase('Zone code is required.'));
        }
        if (!preg_match(self::CODE_PATTERN, $code)) {
            throw new LocalizedException(new Phrase(
                'Zone code "%1" may only contain A-Z, 0-9, underscore and dash.',
                [$code]
            ));
        }
        if ($label === '') {
            throw new LocalizedException(new Phrase('Zone label is required.'));
        }

        $provinceCodes = $this->normalizeList($zone->getIncludeProvinceCodes());
        $includeWardCodes = $this->normalizeList($zone->getIncludeWardCodes());
        $excludeWardCodes = $this->normalizeList($zone->getExcludeWardCodes());

        $this->assertCodesNonEmpty('include_province_codes', $provinceCodes);
        $this->assertCodesNonEmpty('include_ward_codes', $includeWardCodes);
        $this->assertCodesNonEmpty('exclude_ward_codes', $excludeWardCodes);

        $this->assertCanonicalProvinces($provinceCodes);
        $this->assertCanonicalWards('include_ward_codes', $includeWardCodes, $provinceCodes, true);
        $this->assertCanonicalWards('exclude_ward_codes', $excludeWardCodes, $provinceCodes, false);
        $this->assertUniqueCode($code, $excludeZoneId);

        return new CanonicalZone(
            $code,
            $label,
            (bool) $zone->isEnabled(),
            $provinceCodes,
            $includeWardCodes,
            $excludeWardCodes
        );
    }

    private function assertCodesNonEmpty(string $fieldName, array $codes): void
    {
        foreach ($codes as $code) {
            if (trim((string) $code) === '') {
                throw new LocalizedException(new Phrase(
                    'Zone field "%1" contains an empty code.',
                    [$fieldName]
                ));
            }
        }
    }

    /**
     * @return string[] trim + drop empties + dedupe (normalization, not silent code-dropping)
     */
    private function normalizeList(array $values): array
    {
        $trimmed = array_map(
            static function ($value): string {
                return trim((string) $value);
            },
            $values
        );
        $kept = [];
        foreach ($trimmed as $value) {
            if ($value !== '' && !in_array($value, $kept, true)) {
                $kept[] = $value;
            }
        }
        return $kept;
    }

    private function assertCanonicalProvinces(array $provinceCodes): void
    {
        foreach ($provinceCodes as $code) {
            $unit = $this->unitProvider->getUnit(VnSchemes::VN_ADMIN_2025, $code);
            if ($unit === null || $unit->getLevel() !== 1) {
                throw new LocalizedException(new Phrase(
                    'Zone field "include_province_codes": "%1" is not a canonical VN_ADMIN_2025 province code.',
                    [$code]
                ));
            }
        }
    }

    private function assertCanonicalWards(
        string $fieldName,
        array $wardCodes,
        array $provinceCodes,
        bool $requireProvinceMembership
    ): void {
        foreach ($wardCodes as $code) {
            $unit = $this->unitProvider->getUnit(VnSchemes::VN_ADMIN_2025, $code);
            if ($unit === null || $unit->getLevel() <= 1) {
                throw new LocalizedException(new Phrase(
                    'Zone field "%1": "%2" is not a canonical VN_ADMIN_2025 ward code.',
                    [$fieldName, $code]
                ));
            }
            if ($requireProvinceMembership
                && $provinceCodes !== []
                && !in_array($unit->getRegionCode(), $provinceCodes, true)
            ) {
                throw new LocalizedException(new Phrase(
                    'Zone field "%1": ward "%2" does not belong to any included province.',
                    [$fieldName, $code]
                ));
            }
        }
    }

    private function assertUniqueCode(string $code, ?int $excludeZoneId): void
    {
        $connection = $this->zoneResource->getConnection();
        $select = $connection->select()
            ->from($this->zoneResource->getMainTable(), ['c' => 'COUNT(*)'])
            ->where('code = ?', $code);
        if ($excludeZoneId !== null) {
            $select->where('zone_id <> ?', $excludeZoneId);
        }
        if ((int) $connection->fetchOne($select) > 0) {
            throw new LocalizedException(new Phrase(
                'Zone code "%1" is already in DB.',
                [$code]
            ));
        }
    }
}
