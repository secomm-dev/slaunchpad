<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

namespace Secomm\VietNamAddress\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Secomm\VietNamAddress\Api\Data\VnOperationalNameResolutionInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Api\VnOperationalAddressResolverInterface;
use Secomm\VietNamAddress\Api\VnOperationalNameResolverInterface;
use Secomm\VietNamAddress\Model\Data\VnOperationalNameResolutionData;
use Secomm\VietNamAddress\Model\Scheme\VnSchemeRegistry;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * DEC-FEATYA2C0W-004 (D5 name-entry) / TASK-7AJ3K8 — @see VnOperationalNameResolverInterface.
 *
 * Name matching runs on the REFERENCE layer (secomm_vietnam_address_unit — the canonical
 * multi-scheme dataset), never on the runtime directory name tables: the unit rows are the
 * authoritative name carriers (name_vi + name_en) for every imported scheme, region-scoped
 * through the region unit. The single-match canonical unit code is then handed to the existing
 * id bridge ({@see VnOperationalAddressResolverInterface::resolveFromCanonical}) for the
 * runtime identity — one lookup path, no duplicated SQL, no first-match anywhere.
 */
class VnOperationalNameResolver implements VnOperationalNameResolverInterface
{
    private const COUNTRY_VN = 'VN';
    private const TABLE_REGION = 'directory_country_region';

    /** Reference-layer level of ward (sub-region) units. */
    private const LEVEL_REGION = 1;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly VnSchemeRegistry $schemeRegistry,
        private readonly VnAddressUnitProviderInterface $unitProvider,
        private readonly VnOperationalAddressResolverInterface $operationalAddressResolver
    ) {
    }

    public function resolveWardByName(int $regionId, string $wardName): VnOperationalNameResolutionInterface
    {
        $name = trim($wardName);
        if ($regionId <= 0 || $name === '') {
            return VnOperationalNameResolutionData::unmapped(
                VnOperationalNameResolutionInterface::REASON_NAME_NOT_MATCHED
            );
        }

        $scheme = $this->activeScheme();
        if ($scheme === null) {
            return VnOperationalNameResolutionData::unmapped(
                VnOperationalNameResolutionInterface::REASON_SCHEME_NOT_ACTIVE
            );
        }

        $regionRow = $this->resource->getConnection()->fetchRow(
            $this->resource->getConnection()
                ->select()
                ->from($this->table(self::TABLE_REGION), ['country_id'])
                ->where('region_id = ?', $regionId)
        );
        if (!is_array($regionRow) || (string)($regionRow['country_id'] ?? '') !== self::COUNTRY_VN) {
            return VnOperationalNameResolutionData::unmapped(
                VnOperationalNameResolutionInterface::REASON_NOT_VN_REGION
            );
        }

        $regionCode = $this->regionCode($regionId, $scheme);
        if ($regionCode === null) {
            return VnOperationalNameResolutionData::unmapped(
                VnOperationalNameResolutionInterface::REASON_NOT_VN_REGION
            );
        }

        $matches = [];
        foreach ($this->unitProvider->getChildren($scheme, $regionCode) as $unit) {
            if ($unit->getLevel() === self::LEVEL_REGION) {
                continue;
            }
            if ($unit->getNameVi() === $name || $unit->getNameEn() === $name) {
                $matches[] = $unit->getCode();
            }
        }

        if (count($matches) > 1) {
            return VnOperationalNameResolutionData::ambiguous($matches);
        }

        if (count($matches) === 1) {
            $runtime = $this->operationalAddressResolver->resolveFromCanonical($scheme, $matches[0]);

            return $runtime->isResolved()
                ? VnOperationalNameResolutionData::exact($runtime->getIdentity())
                : VnOperationalNameResolutionData::unmapped(
                    VnOperationalNameResolutionInterface::REASON_RUNTIME_ROW_MISSING
                );
        }

        return VnOperationalNameResolutionData::unmapped(
            VnOperationalNameResolutionInterface::REASON_NAME_NOT_MATCHED
        );
    }

    /**
     * Region code of the runtime row within the active scheme (identity via the code columns
     * the import wrote — never via names).
     */
    private function regionCode(int $regionId, string $scheme): ?string
    {
        $runtime = $this->operationalAddressResolver->resolveFromRuntime($regionId, 0);
        if (!$runtime->isResolved() || $runtime->getIdentity()?->getSchemeCode() !== $scheme) {
            return null;
        }

        return $runtime->getIdentity()->getRegionCode();
    }

    /**
     * Configured active scheme, only trusted when the registry CURRENT row agrees — same
     * rule as VnOperationalAddressResolver.
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

    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }
}
