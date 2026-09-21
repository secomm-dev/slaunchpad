<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Shipment;

/**
 * TASK-9Q5ZAK r3 (DEC-TASK9Q5ZAK-001) — the GHN-specific interpretation of ShippingCore
 * physical facts:
 *
 *   type 2 (light, one package <20,000 g) → root weight/length/width/height = the package;
 *                                           no items (content is contract-required instead)
 *   type 5 (>=20,000 g OR multi-package)  → items[] = ONE entry per physical package — the
 *                                           authoritative physical representation. SANDBOX-VERIFIED
 *                                           (2026-09-15): root `weight` is provider-MANDATORY
 *                                           ('required' tag validation rejects without it) and
 *                                           carries the factual Σ (values >50,000g accepted with
 *                                           items[]); root length/width/height are NOT required →
 *                                           OMITTED (null) — never a synthetic aggregate box.
 *
 * Built only by {@see GhnPhysicalParcelInterpreter} after per-package limit validation.
 */
final class GhnParcelPlan
{
    /**
     * @param array<int, array{name: string, quantity: int, weight: int, length: int, width: int, height: int}>|null $items
     *        type 5 only; null = type 2 (content carries the description instead)
     */
    public function __construct(
        private readonly int $serviceTypeId,
        private readonly ?int $rootWeightG,
        private readonly ?int $rootLengthCm,
        private readonly ?int $rootWidthCm,
        private readonly ?int $rootHeightCm,
        private readonly ?array $items = null
    ) {
    }

    public function getServiceTypeId(): int
    {
        return $this->serviceTypeId;
    }

    /**
     * Type 2: the single package weight. Type 5: the factual Σ of the packages
     * (provider-mandatory root field — sandbox-verified 2026-09-15; may exceed 50,000 g
     * when items[] carries the per-package weights).
     */
    public function getRootWeightG(): ?int
    {
        return $this->rootWeightG;
    }

    /**
     * Null for type 5 — sandbox-verified NOT required when items[] carries the dimensions.
     */
    public function getRootLengthCm(): ?int
    {
        return $this->rootLengthCm;
    }

    public function getRootWidthCm(): ?int
    {
        return $this->rootWidthCm;
    }

    /**
     * Null for type 5 — sandbox-verified NOT required when items[] carries the dimensions.
     */
    public function getRootHeightCm(): ?int
    {
        return $this->rootHeightCm;
    }

    /**
     * @return array<int, array{name: string, quantity: int, weight: int, length: int, width: int, height: int}>|null
     */
    public function getItems(): ?array
    {
        return $this->items;
    }
}
