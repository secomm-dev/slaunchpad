<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Address;

use Secomm\ShippingCore\Api\Address\CarrierAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\RuntimeAddressContextBuilderInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface;
use Secomm\VietNamAddress\Api\VnOperationalAddressResolverInterface;
use Secomm\VietNamAddress\Api\VnOperationalNameResolverInterface;

/**
 * TASK-7AJ3K8 (Phase E-C1) — @see RuntimeAddressContextBuilderInterface.
 *
 * Identity precedence: id-based bridge (region + city node) → name-based bridge (region +
 * locality name). Name AMBIGUOUS produces a context WITH candidates and WITHOUT identity —
 * the manager reports AMBIGUOUS; nothing is auto-picked here or anywhere else.
 */
final class RuntimeAddressContextBuilder implements RuntimeAddressContextBuilderInterface
{
    public function __construct(
        private readonly VnOperationalAddressResolverInterface $operationalAddressResolver,
        private readonly VnOperationalNameResolverInterface $operationalNameResolver
    ) {
    }

    /**
     * @inheritDoc
     */
    public function build(
        ?string $countryId,
        int $regionId,
        ?int $cityId,
        ?string $cityName,
        CarrierAddressCapabilityInterface $capability,
        ?string $streetText = null
    ): ShippingAddressResolutionContextInterface {
        $runtime = $this->resolveRuntime($regionId, $cityId, $cityName);

        return new ShippingAddressResolutionContext(
            countryId: $countryId,
            sourceScheme: $runtime['scheme'],
            sourceUnitCode: $runtime['unitCode'],
            targetScheme: $capability->getRequiredScheme(),
            streetText: $this->normalizeStreet($streetText),
            candidateCodes: $runtime['candidates']
        );
    }

    /**
     * @return array{scheme: ?string, unitCode: ?string, candidates: string[]}
     */
    private function resolveRuntime(int $regionId, ?int $cityId, ?string $cityName): array
    {
        if ($cityId !== null && $cityId > 0) {
            return $this->fromIdentity(
                $this->operationalAddressResolver->resolveFromRuntime($regionId, $cityId)->getIdentity()
            );
        }

        $name = trim((string) $cityName);
        if ($regionId > 0 && $name !== '') {
            $byName = $this->operationalNameResolver->resolveWardByName($regionId, $name);
            if ($byName->isResolved()) {
                return $this->fromIdentity($byName->getIdentity());
            }
            if ($byName->getCandidateCodes() !== []) {
                return ['scheme' => null, 'unitCode' => null, 'candidates' => $byName->getCandidateCodes()];
            }
        }

        return ['scheme' => null, 'unitCode' => null, 'candidates' => []];
    }

    /**
     * @return array{scheme: ?string, unitCode: ?string, candidates: string[]}
     */
    private function fromIdentity(?\Secomm\VietNamAddress\Api\Data\VnOperationalIdentityInterface $identity): array
    {
        if ($identity === null) {
            return ['scheme' => null, 'unitCode' => null, 'candidates' => []];
        }

        return [
            'scheme' => $identity->getSchemeCode(),
            'unitCode' => $identity->getUnitCode(),
            'candidates' => [],
        ];
    }

    private function normalizeStreet(?string $streetText): ?string
    {
        $streetText = trim((string) $streetText);

        return $streetText !== '' ? $streetText : null;
    }
}
