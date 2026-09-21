<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Rate;

use Secomm\ShippingCore\Api\Address\CarrierOperationAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface;
use Secomm\ShippingCore\Api\ShippingContextInterface;

/**
 * TASK-8MQHJX (Phase C, architecture v10 §35) — immutable, provider-neutral input for ONE
 * carrier RATE execution. Only generic concepts cross this boundary (carrier identity,
 * destination scope, canonical destination identity, mode/policy configuration, fulfillment
 * context): never provider IDs, never AMBIGUOUS candidate lists, never is_primary metadata —
 * those stay inside the shared handoff/resolution path.
 *
 * The canonical destination identity (province/ward codes) drives eligibility BEFORE any
 * resolution/mapping work; the resolution context drives the carrier-facing handoff when the
 * configured mode reaches the address-policy stage.
 */
interface CarrierRateExecutionRequestInterface
{
    public function getCarrierCode(): string;

    /** DestinationScope::ALL | DestinationScope::SELECTED_ZONES. */
    public function getDestinationScope(): string;

    /** @return string[] allowed canonical zone codes (empty for ALL). */
    public function getAllowedZoneCodes(): array;

    /** Canonical VN province code of the destination (eligibility input). */
    public function getDestinationProvinceCode(): string;

    /** Canonical VN ward code of the destination; null when unknown. */
    public function getDestinationWardCode(): ?string;

    /** RateSourceMode::* (validated at construction). */
    public function getRateSourceMode(): string;

    /** AddressResolutionPolicy::* (validated at construction) — only read for realtime modes. */
    public function getAddressResolutionPolicy(): string;

    public function getCapability(): CarrierOperationAddressCapabilityInterface;

    /** Destination resolution context for the carrier-facing handoff (RATE operation). */
    public function getResolutionContext(): ShippingAddressResolutionContextInterface;

    /** Fulfillment/origin context (store-scoped); ShippingCore never selects the origin itself. */
    public function getShippingContext(): ShippingContextInterface;

    public function getRealtimeContributor(): RealtimeCarrierRateContributorInterface;
}
