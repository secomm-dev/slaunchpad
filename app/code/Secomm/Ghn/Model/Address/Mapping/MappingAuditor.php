<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Mapping;

use Magento\Framework\Exception\LocalizedException;
use Secomm\Ghn\Model\Address\Dataset\MappingCsv;
use Secomm\Ghn\Model\Address\GhnSchemes;
use Secomm\Ghn\Model\ResourceModel\AddressMapping;
use Secomm\Ghn\Model\ResourceModel\AddressUnit;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;

/**
 * SPEC-TASK-TBM30R §8 / SPEC-FEAT-FQWEQ3 AC-ADDR-006 — audit of the imported mapping lifecycle.
 * Reports mapped / unmapped / ambiguous / invalid / stale / disabled_provider_unit / duplicate /
 * dangling + coverage % (total and per level) and a `production_ready` flag
 * (unmapped = ambiguous = invalid = duplicate = dangling = 0). Computed live from stored rows ×
 * deterministic re-match — never silently auto-approves anything.
 */
class MappingAuditor
{
    public function __construct(
        private readonly MappingMatcher $matcher,
        private readonly AddressUnit $unitResource,
        private readonly AddressMapping $mappingResource,
        private readonly VnAddressUnitProviderInterface $unitProvider
    ) {
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException unknown scheme / GHN master data empty
     */
    public function audit(string $secommScheme): array
    {
        $ghnScheme = MappingMatcher::SCHEME_PAIRS[$secommScheme]
            ?? throw new LocalizedException(__('Unknown canonical scheme %1 for GHN mapping.', $secommScheme));

        $ghnUnits = $this->unitResource->fetchByScheme($ghnScheme);
        if ($ghnUnits === []) {
            throw new LocalizedException(
                __('GHN master data for %1 is empty — sync or import a master dataset first.', $ghnScheme)
            );
        }

        $decisions = $this->matcher->match($secommScheme, $ghnUnits);
        $stored = $this->mappingResource->fetchByScheme($secommScheme);

        // Unit index for invalid/disabled checks (ALL statuses — a stored mapping pointing at a
        // disabled unit is exactly what the audit must surface).
        $unitsById = [];
        foreach ($ghnUnits as $unit) {
            $unitsById[(int) $unit['entity_id']] = $unit;
        }

        $byLevel = [];
        $unmappedCodes = [];
        $ambiguousCodes = [];
        $invalidCodes = [];
        $staleCodes = [];
        $danglingCodes = [];
        $disabledProviderCodes = [];
        $mapped = 0;

        foreach ($decisions as $unitCode => $decision) {
            $level = (int) $decision['level'];
            $byLevel[$level]['total'] = ($byLevel[$level]['total'] ?? 0) + 1;

            $row = $stored[$unitCode] ?? null;
            if ($row !== null) {
                $mapped++;
                $byLevel[$level]['mapped'] = ($byLevel[$level]['mapped'] ?? 0) + 1;

                $this->inspectStoredRow($secommScheme, $row, $unitsById, $unitCode, $invalidCodes, $danglingCodes, $disabledProviderCodes);
                continue;
            }

            if ($decision['status'] === MappingMatcher::STATUS_AMBIGUOUS) {
                $byLevel[$level]['ambiguous'] = ($byLevel[$level]['ambiguous'] ?? 0) + 1;
                $ambiguousCodes[$unitCode] = array_map('strval', $decision['candidates']);
                continue;
            }

            $byLevel[$level]['unmapped'] = ($byLevel[$level]['unmapped'] ?? 0) + 1;
            $unmappedCodes[] = $unitCode;
        }

        // Stale rows: stored mappings for canonical codes the matcher no longer knows. They are
        // still audited — a stale row pointing at a disabled/missing unit must surface.
        foreach (array_keys($stored) as $unitCode) {
            if (isset($decisions[$unitCode])) {
                continue;
            }

            $staleCodes[] = $unitCode;
            $this->inspectStoredRow($secommScheme, $stored[$unitCode], $unitsById, (string) $unitCode, $invalidCodes, $danglingCodes, $disabledProviderCodes);
        }

        // Duplicate stored mappings are structurally impossible: fetchByScheme keys rows by
        // (scheme, unit_code) and UNIQUE(scheme, unit_code) enforces it at the DB level —
        // reported for completeness of the production-ready gate.
        $duplicateCount = 0;

        ksort($byLevel);
        foreach ($byLevel as $level => $stats) {
            $total = (int) ($stats['total'] ?? 0);
            $byLevel[$level]['coverage_percent'] = $total > 0
                ? round(((int) ($stats['mapped'] ?? 0)) * 100 / $total, 2)
                : 0.0;
        }

        $coverage = count($decisions) > 0 ? round($mapped * 100 / count($decisions), 2) : 0.0;
        // Address & Shipping Architecture v3 §25: stale > 0 must NEVER coexist with
        // production_ready = true — stored mappings for canonical units the authoritative
        // dataset no longer knows are exactly the state this gate exists to catch.
        $productionReady = count($unmappedCodes) === 0
            && $ambiguousCodes === []
            && $invalidCodes === []
            && $staleCodes === []
            && $danglingCodes === []
            && $duplicateCount === 0;

        return [
            'secomm_scheme_code' => $secommScheme,
            'ghn_scheme_code' => $ghnScheme,
            'total_canonical' => count($decisions),
            'mapped' => $mapped,
            'unmapped' => count($unmappedCodes),
            'ambiguous' => count($ambiguousCodes),
            'invalid' => count($invalidCodes),
            'stale' => count($staleCodes),
            'duplicate' => $duplicateCount,
            'dangling' => count($danglingCodes),
            'disabled_provider_unit' => count($disabledProviderCodes),
            'coverage_percent' => $coverage,
            'production_ready' => $productionReady,
            'by_level' => $byLevel,
            'unmapped_codes' => $unmappedCodes,
            'ambiguous_codes' => $ambiguousCodes,
            'invalid_codes' => $invalidCodes,
            'stale_codes' => $staleCodes,
            'dangling_codes' => $danglingCodes,
            'disabled_provider_codes' => $disabledProviderCodes,
        ];
    }

    /**
     * Stored mapping → unit/canonical health check: missing GHN unit = invalid (FK makes this
     * defensive only), canonical unit gone from VietNamAddress = dangling, disabled unit =
     * exactly what the audit must surface (AC-ADDR-006).
     *
     * @param array<string, mixed> $row
     * @param array<int, array<string, mixed>> $unitsById
     * @param array<int, string> $invalidCodes
     * @param array<int, string> $danglingCodes
     * @param array<int, string> $disabledProviderCodes
     */
    private function inspectStoredRow(
        string $secommScheme,
        array $row,
        array $unitsById,
        string $unitCode,
        array &$invalidCodes,
        array &$danglingCodes,
        array &$disabledProviderCodes
    ): void {
        $unitId = (int) $row['ghn_address_unit_id'];
        if (!isset($unitsById[$unitId])) {
            $invalidCodes[] = $unitCode;

            return;
        }

        if ($this->unitProvider->getUnit($secommScheme, $unitCode) === null) {
            $danglingCodes[] = $unitCode;
        }

        if ((string) $unitsById[$unitId]['status'] !== GhnSchemes::STATUS_ACTIVE) {
            $disabledProviderCodes[] = $unitCode;
        }
    }
}
