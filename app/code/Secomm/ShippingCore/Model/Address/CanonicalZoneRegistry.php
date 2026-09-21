<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Address;

use Secomm\ShippingCore\Api\Address\CanonicalZoneInterface;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRegistryInterface;

/**
 * TASK-8MQHJX (Phase A) — shared canonical zone registry; @see CanonicalZoneRegistryInterface.
 *
 * Zone definitions contributed via DI (`canonicalZones` array argument). Zero zones is valid.
 * Duplicate zone codes fail fast at construction (misconfiguration).
 */
final class CanonicalZoneRegistry implements CanonicalZoneRegistryInterface
{
    /** @var array<string, CanonicalZoneInterface> keyed by zone code */
    private array $zonesByCode = [];

    /** @var CanonicalZoneInterface[] registration order preserved */
    private array $allZones = [];

    public function __construct(array $canonicalZones = [])
    {
        foreach ($canonicalZones as $zone) {
            $code = $zone->getCode();
            if (trim($code) === '') {
                throw new \LogicException('Canonical zone registry requires non-empty zone codes.');
            }
            if (isset($this->zonesByCode[$code])) {
                throw new \LogicException(sprintf('Duplicate canonical zone code "%s".', $code));
            }
            $this->zonesByCode[$code] = $zone;
            $this->allZones[] = $zone;
        }
    }

    /**
     * @inheritDoc
     */
    public function getByCode(string $zoneCode): ?CanonicalZoneInterface
    {
        return $this->zonesByCode[$zoneCode] ?? null;
    }

    public function getAll(): array
    {
        return $this->allZones;
    }

    public function getEnabled(): array
    {
        return array_values(array_filter($this->allZones, static fn (CanonicalZoneInterface $z): bool => $z->isEnabled()));
    }
}
