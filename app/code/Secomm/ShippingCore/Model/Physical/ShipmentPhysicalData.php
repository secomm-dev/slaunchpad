<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Physical;

use Secomm\ShippingCore\Api\Physical\PhysicalPackageInterface;
use Secomm\ShippingCore\Api\Physical\ShipmentPhysicalDataInterface;

/**
 * DEC-TASK9Q5ZAK-001 — immutable physical-facts set of one shipment. Fail-fast on an empty
 * package list (facts must represent something that was actually packed) and on inconsistent
 * totals (totalWeightG is the factual Σ of the packages, computed by the factory — callers
 * cannot declare a total that contradicts the packages).
 */
final class ShipmentPhysicalData implements ShipmentPhysicalDataInterface
{
    /**
     * @param PhysicalPackageInterface[] $packages
     */
    public function __construct(
        private readonly array $packages,
        private readonly int $totalWeightG
    ) {
        if ($this->packages === []) {
            throw new \InvalidArgumentException('Shipment physical data requires at least one package.');
        }
        $sum = 0;
        foreach ($this->packages as $package) {
            if (!$package instanceof PhysicalPackageInterface) {
                throw new \InvalidArgumentException('Every package must implement PhysicalPackageInterface.');
            }
            $sum += $package->getWeightG();
        }
        if ($this->totalWeightG !== $sum) {
            throw new \InvalidArgumentException(
                sprintf('Total weight %d g contradicts the package sum %d g.', $this->totalWeightG, $sum)
            );
        }
    }

    public static function fromPackages(array $packages): self
    {
        $total = 0;
        foreach ($packages as $package) {
            $total += $package->getWeightG();
        }

        return new self($packages, $total);
    }

    public function getTotalWeightG(): int
    {
        return $this->totalWeightG;
    }

    public function getPackages(): array
    {
        return $this->packages;
    }
}
