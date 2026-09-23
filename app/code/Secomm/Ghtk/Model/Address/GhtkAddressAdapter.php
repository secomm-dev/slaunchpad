<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Address;

use Magento\Framework\App\CacheInterface;
use Secomm\Ghtk\Model\Address\GhtkOperationAddressCapability;
use Secomm\Ghtk\Model\GhtkAddressOverrideRepository;
use Secomm\Ghtk\Model\Log\MaskingLogger;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffServiceInterface;
use Secomm\ShippingCore\Api\Address\RuntimeAddressContextBuilderInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;

/**
 * TASK-7AJ3K8 r1 (DEC-TASK7AJ3K8-002) + TASK-6YG3HP (v5 structural alignment) — THE GHTK
 * ADDRESS ADAPTER for the destination AND pickup name-path, PER OPERATION:
 *
 *     ShippingCore canonical handoff (handoffContextForOperation) → GHTK text.
 *
 * RATE and CREATE resolve through their OWN operation-specific handoff — the operation is an
 * explicit argument, never inferred downstream (structurally ready for RATE/CREATE scheme
 * divergence; the concrete scheme is a CANDIDATE pending TASK-44F7V7 — see capability).
 *
 * Default representation is the canonical Vietnamese text (name_vi) of the carrier-required
 * scheme, straight from the Secomm_VietNamAddress reference layer. `secomm_ghtk_address_map`
 * is an OPTIONAL exception/override table keyed by canonical identity — a row replaces the
 * canonical text ONLY for units GHTK demonstrably needs different names for; a miss changes
 * nothing. NO fuzzy matching, NO first-match, NO guessed address: AMBIGUOUS/UNMAPPED canonical
 * outcomes → null (rate hidden / submit fails fast). Never throws (rate-path contract).
 *
 * Observability reasons (masked context only — unit codes, never street/telephone):
 * CANONICAL_ADDRESS_UNRESOLVED · GHTK_ADDRESS_INVALID · GHTK_OVERRIDE_APPLIED (debug).
 */
class GhtkAddressAdapter
{
    public const CACHE_TAG = 'secomm_ghtk_address_map';
    private const CACHE_PREFIX = 'ghtk_dest2_';
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly RuntimeAddressContextBuilderInterface $runtimeContextBuilder,
        private readonly CarrierAddressHandoffServiceInterface $handoffService,
        private readonly GhtkOperationAddressCapability $capability,
        private readonly GhtkLegacyCapabilityShim $legacyCapabilityShim,
        private readonly VnAddressUnitProviderInterface $unitProvider,
        private readonly GhtkAddressOverrideRepository $overrideRepository,
        private readonly CacheInterface $cache,
        private readonly MaskingLogger $logger
    ) {
    }

    /**
     * @param string|null $countryId ISO country id (non-VN → null)
     * @param int $regionId Magento region PK of the province
     * @param int|null $cityId ward node id when known (rarely persisted); name path otherwise
     * @param string|null $wardName locality text from the native `city` field
     * @param string $operation ShippingAddressOperation::RATE|CREATE — the caller's own
     *                          operation; it selects the operation-specific handoff
     */
    public function resolve(
        ?string $countryId,
        int $regionId,
        ?int $cityId,
        ?string $wardName,
        string $operation
    ): ?GhtkAddress {
        try {
            return $this->resolveUncached($countryId, $regionId, $cityId, $wardName, $operation);
        } catch (\Throwable $e) {
            // Rate path must never crash shipping estimation (SL-009 AC-5).
            $this->logger->error(
                'GHTK address adaptation failed; returning null (graceful).',
                ['country_id' => $countryId, 'region_id' => $regionId, 'exception' => $e->getMessage()]
            );

            return null;
        }
    }

    private function resolveUncached(
        ?string $countryId,
        int $regionId,
        ?int $cityId,
        ?string $wardName,
        string $operation
    ): ?GhtkAddress {
        // Builder shim: ShippingCore's scalar builder still type-hints the deprecated
        // per-carrier capability (ShippingCore hard stop) — GhtkLegacyCapabilityShim
        // adapts the per-operation capability for that one legacy call (TASK-6YG3HP).
        $context = $this->runtimeContextBuilder->build(
            $countryId,
            $regionId,
            $cityId,
            $wardName,
            $this->legacyCapabilityShim
        );
        // v5 per-operation handoff — RATE and CREATE resolve independently.
        $handoff = $this->handoffService->handoffContextForOperation($context, $this->capability, $operation);

        if (!$handoff->isApplicable()) {
            return null; // non-VN — never GHTK business
        }

        $resolved = $handoff->getResolvedAddress();
        if ($resolved === null) {
            // No guessed address: AMBIGUOUS (candidates exist) and UNMAPPED both hide the rate.
            $this->logger->warning(
                'GHTK address adapter: CANONICAL_ADDRESS_UNRESOLVED; no rate.',
                [
                    'region_id' => $regionId,
                    'candidate_count' => count($handoff->getCandidateCodes()),
                ]
            );

            return null;
        }

        $cacheId = self::CACHE_PREFIX . 'u_' . $resolved->getUnitCode();
        $cached = $this->cache->load($cacheId);
        if ($cached !== false) {
            return $this->unserialize($cached);
        }

        $address = $this->fromCanonicalIdentity(
            $resolved->getSchemeCode(),
            (string) $resolved->getUnitCode()
        );
        if ($address !== null) {
            $this->cache->save($this->serialize($address), $cacheId, [self::CACHE_TAG], self::CACHE_TTL);
        }

        return $address;
    }

    /**
     * Canonical ward identity → GHTK text: canonical name_vi by default, optional
     * exception override per field, then required-field validation.
     */
    private function fromCanonicalIdentity(string $schemeCode, string $unitCode): ?GhtkAddress
    {
        $wardUnit = $this->unitProvider->getUnit($schemeCode, $unitCode);
        if ($wardUnit === null) {
            $this->logger->warning(
                'GHTK address adapter: GHTK_ADDRESS_INVALID — canonical ward unit missing from the reference layer.',
                ['scheme' => $schemeCode, 'unit_code' => $unitCode]
            );

            return null;
        }

        $provinceCode = $wardUnit->getRegionCode();
        $regionUnit = $this->unitProvider->getUnit($schemeCode, $provinceCode);

        $province = $regionUnit?->getNameVi() ?? '';
        $ward = $wardUnit->getNameVi();
        $district = null;
        if ($province === '' || $ward === '') {
            $this->logger->warning(
                'GHTK address adapter: GHTK_ADDRESS_INVALID — canonical name_vi incomplete.',
                ['scheme' => $schemeCode, 'unit_code' => $unitCode]
            );

            return null;
        }

        // Optional exception override, keyed by canonical identity (null-safe miss = normal path).
        $override = $this->overrideRepository->findActive($schemeCode, $provinceCode, $unitCode);
        if ($override !== null) {
            $province = $override->getGhtkProvince() ?? $province;
            $district = $override->getGhtkDistrict();
            $ward = $override->getGhtkWard() ?? $ward;
            $this->logger->debug(
                'GHTK address adapter: GHTK_OVERRIDE_APPLIED.',
                ['scheme' => $schemeCode, 'province_code' => $provinceCode, 'unit_code' => $unitCode]
            );

            return new GhtkAddress($province, $district, $ward, true);
        }

        return new GhtkAddress($province, $district, $ward, false);
    }

    private function serialize(GhtkAddress $address): string
    {
        return (string) json_encode([
            'province' => $address->province,
            'district' => $address->district,
            'ward' => $address->ward,
            'isExact' => $address->isExact,
        ], JSON_THROW_ON_ERROR);
    }

    private function unserialize(string $payload): ?GhtkAddress
    {
        try {
            $data = json_decode($payload, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($data)) {
            return null;
        }

        return new GhtkAddress(
            (string) ($data['province'] ?? ''),
            isset($data['district']) ? (string) $data['district'] : null,
            (string) ($data['ward'] ?? ''),
            (bool) ($data['isExact'] ?? false)
        );
    }
}
