<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Shipment;

use Secomm\ShippingCore\Api\Physical\ShipmentPhysicalDataInterface;
use Secomm\ShippingCore\Model\Physical\PhysicalPackage;
use Secomm\ShippingCore\Model\Physical\ShipmentPhysicalData;
use Secomm\ShippingCore\Model\Physical\StoreWeightConverter;

/**
 * TASK-W5BW4F — THE single source of truth for deterministic GHN parcel validation, shared by
 * the creation service (post-commit create) and the pre-save validation observer (blocks a
 * fresh shipment save whose confirmed packages would deterministically fail INVALID_PARCEL).
 *
 * Two deterministic checks, both fail-closed and both owned here so the pre-save gate and the
 * post-commit gate can never drift apart:
 * - row usability (missing/zero weight or dimension) — {@see fromPostedRows()};
 * - GHN per-package hard limits (weight g / per-dimension cm) — {@see assertValid()} via the
 *   interpreter's own validation (fail-closed INVALID_PARCEL before any HTTP call).
 *
 * Missing data is a failure too (USER_GUIDE: physical facts are never invented, merchant
 * defaults are never silently substituted) — {@see missingParcelMessage()} is the canonical
 * admin-facing message for it.
 */
class GhnCreateParcelValidator
{
    public function __construct(
        private readonly GhnPhysicalParcelInterpreter $interpreter,
        private readonly StoreWeightConverter $weightConverter
    ) {
    }

    /**
     * Canonical fail-closed message for a shipment with NO confirmed physical source.
     */
    public static function missingParcelMessage(): \Magento\Framework\Phrase
    {
        return __(
            'GHN create: no confirmed package weight/dimensions on the shipment. '
            . 'Enter the real package information when creating the shipment.'
        );
    }

    /**
     * Builds confirmed physical facts from the admin POST rows, rejecting unusable rows.
     *
     * @param array<int, array<string, mixed>> $postedRawPackages rows [weight, lengthCm, widthCm, heightCm]
     *        — weight in the STORE weight unit
     * @throws GhnCreateValidationException INVALID_PARCEL on unusable rows
     * @throws \Magento\Framework\Exception\LocalizedException on an unusable store weight unit
     */
    public function fromPostedRows(array $postedRawPackages, ?int $storeId): ShipmentPhysicalDataInterface
    {
        $packages = [];
        foreach (array_values($postedRawPackages) as $index => $row) {
            $weight = (float) ($row['weight'] ?? 0);
            $length = (int) ($row['length'] ?? 0);
            $width = (int) ($row['width'] ?? 0);
            $height = (int) ($row['height'] ?? 0);
            if ($weight <= 0 || $length <= 0 || $width <= 0 || $height <= 0) {
                throw new GhnCreateValidationException(
                    GhnCreateValidationException::REASON_INVALID_PARCEL,
                    __('GHN create: package #%1 has an empty weight or dimension.', (string) ($index + 1))
                );
            }
            $packages[] = new PhysicalPackage(
                (int) round($this->weightConverter->toGrams($weight, $storeId)),
                $length,
                $width,
                $height
            );
        }

        return ShipmentPhysicalData::fromPackages($packages);
    }

    /**
     * Runs the full deterministic pre-flight (per-package GHN limits); the parcel plan is
     * discarded — callers only need the pass/fail contract.
     *
     * @throws GhnCreateValidationException INVALID_PARCEL when any package violates GHN hard limits
     */
    public function assertValid(ShipmentPhysicalDataInterface $physical): void
    {
        $this->interpreter->interpret($physical);
    }
}
