<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Address;

use Magento\Framework\Exception\LocalizedException;
use Secomm\ShippingCore\Api\Address\CarrierAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\ResolvedShippingAddressInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionManagerInterface;
use Secomm\ShippingCore\Model\Address\Exception\UnsupportedDestinationException;
use Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface;
use Secomm\VietNamAddress\Api\VnAdminAddressResolverInterface;

/**
 * TASK-5XDG1P (Phase E-B) — LOCAL canonical shipping-address orchestration.
 *
 * Orchestration ONLY: validate → request-scoped cache lookup → delegate canonical graph
 * resolution to Secomm_VietNamAddress (VnAdminAddressResolverInterface — same-scheme EXACT,
 * cross-scheme cardinality-authoritative MAPPED/AMBIGUOUS/UNMAPPED) → convert 1-1 into the
 * carrier-neutral ResolvedShippingAddress → cache → return. No mapping logic lives here (D2);
 * AMBIGUOUS is never auto-selected; external resolvers and textual fallback are later phases
 * and are never invoked.
 *
 * Cache: plain in-memory array on this manager. Magento DI shares one instance per request
 * area, so entries live exactly one request (SPIKE-W273TB §7) — no Redis, no Magento cache
 * frontend, no persistence. The canonical unit/mapping datasets do not change mid-request
 * (imports run via CLI outside the shipping flow), so no invalidation is needed.
 */
final class ShippingAddressResolutionManager implements ShippingAddressResolutionManagerInterface
{
    private const COUNTRY_VN = 'VN';

    /** @var array<string, ResolvedShippingAddressInterface> keyed by canonical identity */
    private array $resolvedByCanonicalKey = [];

    public function __construct(
        private readonly VnAdminAddressResolverInterface $adminAddressResolver
    ) {
    }

    /**
     * @inheritDoc
     * @throws UnsupportedDestinationException non-Vietnam destination (explicit bypass — never UNMAPPED)
     * @throws LocalizedException unknown source/target scheme code (configuration fault, propagated)
     */
    public function resolve(
        ShippingAddressResolutionContextInterface $context,
        CarrierAddressCapabilityInterface $capability
    ): ResolvedShippingAddressInterface {
        $targetScheme = $capability->getRequiredScheme();

        $countryId = $context->getCountryId();
        if ($countryId !== null && strtoupper(trim($countryId)) !== self::COUNTRY_VN) {
            throw new UnsupportedDestinationException(
                __('Shipping address resolution only applies to Vietnam destinations, got country "%1".', $countryId)
            );
        }

        $cacheKey = implode('|', [
            $context->getSourceScheme() ?? '',
            $context->getSourceUnitCode() ?? '',
            $targetScheme,
        ]);
        if (isset($this->resolvedByCanonicalKey[$cacheKey])) {
            return $this->resolvedByCanonicalKey[$cacheKey];
        }

        $resolved = $this->resolveLocally($context, $targetScheme);
        $this->resolvedByCanonicalKey[$cacheKey] = $resolved;

        return $resolved;
    }

    /**
     * One canonical resolution for a cache miss; the outcome is cached whatever its status —
     * AMBIGUOUS/UNMAPPED are valid domain outcomes, not errors to recompute within a request.
     *
     * @throws LocalizedException unknown scheme code
     */
    private function resolveLocally(
        ShippingAddressResolutionContextInterface $context,
        string $targetScheme
    ): ResolvedShippingAddressInterface {
        $sourceScheme = $context->getSourceScheme();
        $sourceUnitCode = $context->getSourceUnitCode();

        if ($sourceScheme === null || trim($sourceScheme) === '' || $sourceUnitCode === null || trim($sourceUnitCode) === '') {
            // Missing canonical identity — explicit unresolved per contract; no fallback to
            // region_id/city_id/ward name/street parsing (outside the canonical runtime contract).
            // TASK-7AJ3K8: an AMBIGUOUS name-bridge match carries its candidates in the context —
            // they surface as AMBIGUOUS (never auto-picked). Candidates are canonical active-scheme
            // codes; cross-scheme candidate translation belongs to the later disambiguation phase.
            $candidates = $context->getCandidateCodes();
            if ($candidates !== []) {
                return new ResolvedShippingAddress(
                    VnAddressResolutionInterface::STATUS_AMBIGUOUS,
                    $targetScheme,
                    null,
                    $candidates
                );
            }

            return new ResolvedShippingAddress(
                VnAddressResolutionInterface::STATUS_UNMAPPED,
                $targetScheme,
                null
            );
        }

        return $this->convert(
            $this->adminAddressResolver->resolve($sourceScheme, $sourceUnitCode, $targetScheme),
            $targetScheme
        );
    }

    /**
     * 1-1 status conversion — the VietnamAddress outcome IS the shipping outcome (D9 semantics
     * stay authoritative in the resolver); the VO constructor re-asserts every invariant.
     */
    private function convert(
        VnAddressResolutionInterface $resolution,
        string $targetScheme
    ): ResolvedShippingAddressInterface {
        return match ($resolution->getStatus()) {
            VnAddressResolutionInterface::STATUS_EXACT,
            VnAddressResolutionInterface::STATUS_MAPPED => new ResolvedShippingAddress(
                $resolution->getStatus(),
                $targetScheme,
                $resolution->getResolvedCode()
            ),
            VnAddressResolutionInterface::STATUS_AMBIGUOUS => new ResolvedShippingAddress(
                VnAddressResolutionInterface::STATUS_AMBIGUOUS,
                $targetScheme,
                null,
                $resolution->getCandidateCodes()
            ),
            VnAddressResolutionInterface::STATUS_UNMAPPED => new ResolvedShippingAddress(
                VnAddressResolutionInterface::STATUS_UNMAPPED,
                $targetScheme,
                null
            ),
            default => throw new \LogicException(
                sprintf('Vietnam address resolver returned an impossible resolution status "%s".', $resolution->getStatus())
            ),
        };
    }
}
