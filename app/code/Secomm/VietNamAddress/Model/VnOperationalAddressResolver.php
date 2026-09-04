<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Secomm\VietNamAddress\Api\Data\VnOperationalIdentityInterface;
use Secomm\VietNamAddress\Api\Data\VnOperationalResolutionInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Api\VnOperationalAddressResolverInterface;
use Secomm\VietNamAddress\Model\Data\VnOperationalIdentityData;
use Secomm\VietNamAddress\Model\Data\VnOperationalResolutionData;
use Secomm\VietNamAddress\Model\Scheme\VnSchemeRegistry;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * DEC-FEATYA2C0W-004 (D5) / TASK-Q4B98P — the operational ↔ canonical bridge.
 *
 * Runtime rows carry the dataset codes since the import (directory_country_region.code =
 * "VN-XX", directory_region_city.code = "VNA25-* / VNAP25-*" — SPEC §2), so BOTH directions
 * are pure code lookups: no name join anywhere, no cache (one indexed query per lookup).
 *
 * The active scheme comes from `secomm_vietnam_address/general/active_scheme` and is only
 * trusted when the scheme registry agrees (CURRENT row) — a drift yields an explicit
 * `scheme_not_active` outcome instead of resolving against an unknown dataset (AC-9).
 */
class VnOperationalAddressResolver implements VnOperationalAddressResolverInterface
{
    private const COUNTRY_VN = 'VN';
    private const TABLE_REGION = 'directory_country_region';
    private const TABLE_CITY = 'directory_region_city';

    /** Reference-layer level of region rows (see VnAddressUnitInterface::getLevel). */
    private const LEVEL_REGION = 1;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly VnSchemeRegistry $schemeRegistry,
        private readonly VnAddressUnitProviderInterface $unitProvider
    ) {
    }

    public function resolveFromRuntime(int $regionId = 0, int $cityId = 0): VnOperationalResolutionInterface
    {
        if ($regionId <= 0 && $cityId <= 0) {
            return VnOperationalResolutionData::unresolved(VnOperationalResolutionInterface::REASON_INVALID_INPUT);
        }

        $scheme = $this->activeScheme();
        if ($scheme === null) {
            return VnOperationalResolutionData::unresolved(VnOperationalResolutionInterface::REASON_SCHEME_NOT_ACTIVE);
        }

        $connection = $this->connection();

        $cityRow = null;
        if ($cityId > 0) {
            $cityRow = $connection->fetchRow(
                $connection->select()
                    ->from($this->table(self::TABLE_CITY), ['city_id', 'region_id', 'code', 'parent_city_id'])
                    ->where('city_id = ?', $cityId)
            );
            if (!is_array($cityRow) || (int)($cityRow['city_id'] ?? 0) === 0) {
                return VnOperationalResolutionData::unresolved(
                    VnOperationalResolutionInterface::REASON_RUNTIME_ROW_MISSING
                );
            }
            if ($regionId > 0 && (int)$cityRow['region_id'] !== $regionId) {
                return VnOperationalResolutionData::unresolved(VnOperationalResolutionInterface::REASON_INVALID_INPUT);
            }
            $regionId = (int)$cityRow['region_id'];
            if ($regionId <= 0) {
                return VnOperationalResolutionData::unresolved(
                    VnOperationalResolutionInterface::REASON_RUNTIME_ROW_MISSING
                );
            }
        }

        $regionRow = $connection->fetchRow(
            $connection->select()
                ->from($this->table(self::TABLE_REGION), ['region_id', 'country_id', 'code'])
                ->where('region_id = ?', $regionId)
        );
        if (!is_array($regionRow) || (int)($regionRow['region_id'] ?? 0) === 0) {
            return VnOperationalResolutionData::unresolved(VnOperationalResolutionInterface::REASON_RUNTIME_ROW_MISSING);
        }
        if ((string)$regionRow['country_id'] !== self::COUNTRY_VN) {
            return VnOperationalResolutionData::unresolved(VnOperationalResolutionInterface::REASON_NOT_VN_REGION);
        }

        $regionCode = (string)($regionRow['code'] ?? '');
        $unitCode = $cityRow !== null ? (string)($cityRow['code'] ?? '') : $regionCode;
        if ($unitCode === '') {
            return VnOperationalResolutionData::unresolved(
                VnOperationalResolutionInterface::REASON_RUNTIME_CODE_MISSING
            );
        }

        $unit = $this->unitProvider->getUnit($scheme, $unitCode);

        return VnOperationalResolutionData::resolved(
            new VnOperationalIdentityData(
                schemeCode: $scheme,
                unitCode: $unitCode,
                level: $unit?->getLevel(),
                regionCode: $unit?->getRegionCode() ?? $regionCode,
                parentUnitCode: $unit?->getParentCode(),
                regionId: $regionId,
                cityId: $cityId > 0 ? $cityId : null
            )
        );
    }

    public function resolveFromCanonical(string $schemeCode, string $unitCode): VnOperationalResolutionInterface
    {
        $schemeCode = trim($schemeCode);
        $unitCode = trim($unitCode);
        if ($schemeCode === '' || $unitCode === '' || !isset(VnSchemes::catalog()[$schemeCode])) {
            return VnOperationalResolutionData::unresolved(VnOperationalResolutionInterface::REASON_INVALID_INPUT);
        }

        // Swap model: only the ACTIVE scheme has runtime rows — never fabricate ids for
        // a historical scheme (AC-4).
        if ($this->activeScheme() !== $schemeCode) {
            return VnOperationalResolutionData::unresolved(VnOperationalResolutionInterface::REASON_SCHEME_NOT_ACTIVE);
        }

        $unit = $this->unitProvider->getUnit($schemeCode, $unitCode);
        if ($unit === null) {
            return VnOperationalResolutionData::unresolved(VnOperationalResolutionInterface::REASON_UNIT_UNKNOWN);
        }

        $connection = $this->connection();
        if ($unit->getLevel() === self::LEVEL_REGION) {
            $regionRow = $connection->fetchRow(
                $connection->select()
                    ->from($this->table(self::TABLE_REGION), ['region_id'])
                    ->where('code = ?', $unitCode)
                    ->where('country_id = ?', self::COUNTRY_VN)
            );
            if (!is_array($regionRow) || (int)($regionRow['region_id'] ?? 0) === 0) {
                return VnOperationalResolutionData::unresolved(
                    VnOperationalResolutionInterface::REASON_RUNTIME_ROW_MISSING
                );
            }

            return VnOperationalResolutionData::resolved(
                $this->identity($unit, $unitCode, (int)$regionRow['region_id'], null)
            );
        }

        $cityIds = $connection->fetchCol(
            $connection->select()
                ->from(['c' => $this->table(self::TABLE_CITY)], ['city_id'])
                ->join(['r' => $this->table(self::TABLE_REGION)], 'r.region_id = c.region_id', [])
                ->where('c.code = ?', $unitCode)
                ->where('r.country_id = ?', self::COUNTRY_VN)
        );
        // 0 = not installed / purged; >1 = runtime drift against the UNIQUE(scheme, code)
        // reference layer — both are explicit misses, never a guess (AC-3/AC-4).
        if (count($cityIds) !== 1) {
            return VnOperationalResolutionData::unresolved(
                VnOperationalResolutionInterface::REASON_RUNTIME_ROW_MISSING
            );
        }

        $cityId = (int)reset($cityIds);
        $regionId = (int)$connection->fetchOne(
            $connection->select()
                ->from($this->table(self::TABLE_CITY), ['region_id'])
                ->where('city_id = ?', $cityId)
        );

        return VnOperationalResolutionData::resolved($this->identity($unit, $unitCode, $regionId, $cityId));
    }

    /**
     * Configured active scheme, only trusted when the registry CURRENT row agrees.
     */
    private function activeScheme(): ?string
    {
        $configured = trim(
            (string)($this->scopeConfig->getValue(
                VnSchemes::XML_PATH_ACTIVE_SCHEME,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT
            ) ?? '')
        );
        if ($configured === '') {
            return null;
        }

        return $this->schemeRegistry->getCurrent() === $configured ? $configured : null;
    }

    private function identity(
        \Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface $unit,
        string $unitCode,
        ?int $regionId,
        ?int $cityId
    ): VnOperationalIdentityInterface {
        return new VnOperationalIdentityData(
            schemeCode: $unit->getSchemeCode(),
            unitCode: $unitCode,
            level: $unit->getLevel(),
            regionCode: $unit->getRegionCode(),
            parentUnitCode: $unit->getParentCode(),
            regionId: $regionId > 0 ? $regionId : null,
            cityId: $cityId
        );
    }

    private function connection(): \Magento\Framework\DB\Adapter\AdapterInterface
    {
        return $this->resource->getConnection();
    }

    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }
}
