<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Mapping;

use Secomm\Ghn\Model\Address\GhnSchemes;
use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;

/**
 * SPEC-FEAT-FQWEQ3 §7 — deterministic match of canonical units against GHN master data
 * (offline pipeline step; RUNTIME NEVER MATCHES NAMES). Decision per canonical unit:
 *
 *  - `approved`   — exact normalized-name match within the mapped parent scope, or a curated
 *                   alias from Files/ghn_mapping_aliases_{scheme}.csv (alias must still resolve
 *                   inside the mapped parent scope — no cross-tree overrides);
 *  - `ambiguous`  — more than one candidate in scope (NEVER auto-picked — AC-ADDR-006);
 *  - `unmapped`   — zero candidates (includes cascades: parent unmapped ⇒ children unmapped);
 *  - `alias_invalid` — alias present but its provider_key is outside the mapped parent scope.
 *
 * Matching compares the canonical `name_vi` against the GHN `name` only — GHN extension_names
 * are deliberately NOT used for auto-approval (too broad); they remain available to the human
 * curation loop via audit exports.
 */
class MappingMatcher
{
    public const STATUS_APPROVED = 'approved';
    public const STATUS_AMBIGUOUS = 'ambiguous';
    public const STATUS_UNMAPPED = 'unmapped';
    public const STATUS_ALIAS_INVALID = 'alias_invalid';

    /** Canonical secomm scheme → GHN master data scheme. */
    public const SCHEME_PAIRS = [
        VnSchemePairs::SECOMM_2025 => VnSchemePairs::GHN_2025,
        VnSchemePairs::SECOMM_PRE_2025 => VnSchemePairs::GHN_PRE_2025,
    ];

    public function __construct(
        private readonly VnAddressUnitProviderInterface $unitProvider,
        private readonly NameNormalizer $normalizer,
        private readonly AliasRepository $aliasRepository
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $ghnUnits parent-first rows of the GHN scheme
     *        (AddressUnit::fetchByScheme), any status — non-ACTIVE units are skipped as candidates
     * @return array<string, array<string, mixed>> secomm_unit_code => decision
     */
    public function match(string $secommScheme, array $ghnUnits): array
    {
        $aliases = $this->aliasRepository->getAliases($secommScheme);
        $byEntityId = [];
        $provinces = [];
        $byParent = [];
        foreach ($ghnUnits as $unit) {
            $byEntityId[(string) $unit['entity_id']] = $unit;
            if ((string) $unit['status'] !== GhnSchemes::STATUS_ACTIVE) {
                continue;
            }
            if ((int) $unit['depth'] === 1) {
                $provinces[] = $unit;
                continue;
            }
            $byParent[(string) $unit['parent_id']][] = $unit;
        }

        $decisions = [];
        $regions = $this->unitProvider->getChildren($secommScheme, '');
        foreach ($regions as $region) {
            $regionDecision = $this->decide(
                $region,
                $provinces,
                $aliases
            );
            $decisions[$region->getCode()] = $regionDecision;

            $provinceEntityId = $regionDecision['ghn_entity_id'];
            foreach ($this->unitProvider->getChildren($secommScheme, $region->getCode()) as $child) {
                if ((int) $child->getLevel() === 2 && $this->childIsDistrict($secommScheme)) {
                    $districtDecision = $this->decide($child, $byParent[$provinceEntityId] ?? [], $aliases);
                    $decisions[$child->getCode()] = $districtDecision;

                    $districtEntityId = $districtDecision['ghn_entity_id'];
                    foreach ($this->unitProvider->getChildren($secommScheme, $child->getCode()) as $ward) {
                        $decisions[$ward->getCode()] = $this->decide($ward, $byParent[$districtEntityId] ?? [], $aliases);
                    }
                    continue;
                }

                // 2-level scheme (2025): level-2 canonical units are wards under the province.
                $decisions[$child->getCode()] = $this->decide($child, $byParent[$provinceEntityId] ?? [], $aliases);
            }
        }

        return $decisions;
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     * @return array<string, mixed>
     */
    private function decide(
        VnAddressUnitInterface $unit,
        array $candidates,
        array $aliases
    ): array {
        $base = [
            'secomm_unit_code' => $unit->getCode(),
            'name' => $unit->getNameVi(),
            'level' => (int) $unit->getLevel(),
            'status' => self::STATUS_UNMAPPED,
            'candidates' => [],
            'chosen_provider_key' => null,
            'ghn_entity_id' => null,
            'method' => null,
        ];

        $normalized = $this->normalizer->normalize($unit->getNameVi());
        if ($normalized === '') {
            return $base;
        }

        $matches = [];
        foreach ($candidates as $candidate) {
            if ($this->normalizer->normalize((string) $candidate['name']) === $normalized) {
                $matches[] = $candidate;
            }
        }

        $tier = 'name';
        if ($matches === []) {
            // Second evidence tier (TASK-6TNKDH §7/§8): administrative-prefix normalization.
            // Still only a SUGGESTION tier — nothing here ever approves a mapping.
            $prefixless = $this->normalizer->normalizePrefixless($unit->getNameVi());
            if ($prefixless !== '') {
                foreach ($candidates as $candidate) {
                    if ($this->normalizer->normalizePrefixless((string) $candidate['name']) === $prefixless) {
                        $matches[] = $candidate;
                    }
                }
                $tier = 'name_prefixless';
            }
        }
        if ($matches === []) {
            // Third evidence tier: ASCII-folded (diacritic/đ variants of the same official place,
            // e.g. "Hoà Bình"/"Hòa Bình"). Same-place identity collapse stays impossible — a
            // folded match with several candidates lands in AMBIGUOUS, never auto-picked.
            $folded = $this->normalizer->normalizeAsciiFolded($unit->getNameVi());
            if ($folded !== '') {
                foreach ($candidates as $candidate) {
                    if ($this->normalizer->normalizeAsciiFolded((string) $candidate['name']) === $folded) {
                        $matches[] = $candidate;
                    }
                }
                $tier = 'name_ascii_folded';
            }
        }

        $candidateKeys = array_map(static fn (array $c): string => (string) $c['provider_key'], $candidates);
        $base['candidates'] = $candidateKeys;
        $base['match_tier'] = $matches === [] ? null : $tier;

        $aliasKey = $aliases[$unit->getCode()] ?? null;
        if ($aliasKey !== null) {
            foreach ($candidates as $candidate) {
                if ((string) $candidate['provider_key'] === $aliasKey) {
                    return array_merge($base, [
                        'status' => self::STATUS_APPROVED,
                        'chosen_provider_key' => $aliasKey,
                        'ghn_entity_id' => (string) $candidate['entity_id'],
                        'method' => 'CURATED_ALIAS',
                    ]);
                }
            }

            return array_merge($base, [
                'status' => self::STATUS_ALIAS_INVALID,
                'chosen_provider_key' => $aliasKey,
            ]);
        }

        if (count($matches) > 1) {
            return array_merge($base, [
                'status' => self::STATUS_AMBIGUOUS,
                'candidates' => array_map(static fn (array $c): string => (string) $c['provider_key'], $matches),
            ]);
        }

        if (count($matches) === 1) {
            return array_merge($base, [
                'status' => self::STATUS_APPROVED,
                'chosen_provider_key' => (string) $matches[0]['provider_key'],
                'ghn_entity_id' => (string) $matches[0]['entity_id'],
                'method' => 'EXACT_NAME',
            ]);
        }

        return $base;
    }

    private function childIsDistrict(string $secommScheme): bool
    {
        GhnSchemes::assertKnown(self::SCHEME_PAIRS[$secommScheme]);

        return self::SCHEME_PAIRS[$secommScheme] === VnSchemePairs::GHN_PRE_2025;
    }
}
