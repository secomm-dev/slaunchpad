<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Rate;

use Secomm\ShippingCore\Api\Address\CarrierOperationAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\AddressResolutionPolicy;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateExecutionRequestInterface;
use Secomm\ShippingCore\Api\Rate\RateSourceMode;
use Secomm\ShippingCore\Api\Rate\RealtimeCarrierRateContributorInterface;
use Secomm\ShippingCore\Api\ShippingContextInterface;

/**
 * TASK-8MQHJX (Phase C) — immutable execution input; @see CarrierRateExecutionRequestInterface.
 *
 * RateSourceMode + AddressResolutionPolicy are validated fail-fast at construction — an unknown
 * value can never reach the execution service and silently degrade into a default.
 *
 * DestinationScope is deliberately NOT re-validated here (TASK-R8WR1R r2): a config-sourced
 * unknown scope must REACH the eligibility step, where CarrierEligibilityEvaluator fails it
 * closed deterministically (unknown scope → ineligible, never coerced). Throwing at construction
 * would surface a merchant coverage-configuration error as a carrier runtime exception
 * (the TECHNICAL path) instead of a deterministic coverage miss.
 */
final class CarrierRateExecutionRequest implements CarrierRateExecutionRequestInterface
{
    /**
     * @param string $carrierCode
     * @param string $destinationScope DestinationScope::* or an unrecognized persisted value
     *        (fails closed at the eligibility step — see class docblock)
     * @param string[] $allowedZoneCodes
     * @param string $destinationProvinceCode
     * @param string|null $destinationWardCode
     * @param string $rateSourceMode RateSourceMode::*
     * @param string $addressResolutionPolicy AddressResolutionPolicy::*
     * @param CarrierOperationAddressCapabilityInterface $capability
     * @param ShippingAddressResolutionContextInterface $resolutionContext
     * @param ShippingContextInterface $shippingContext
     * @param RealtimeCarrierRateContributorInterface $realtimeContributor
     * @throws \InvalidArgumentException empty carrier code
     * @throws \Magento\Framework\Exception\LocalizedException unknown mode/policy
     */
    public function __construct(
        private readonly string $carrierCode,
        private readonly string $destinationScope,
        private readonly array $allowedZoneCodes,
        private readonly string $destinationProvinceCode,
        private readonly ?string $destinationWardCode,
        private readonly string $rateSourceMode,
        private readonly string $addressResolutionPolicy,
        private readonly CarrierOperationAddressCapabilityInterface $capability,
        private readonly ShippingAddressResolutionContextInterface $resolutionContext,
        private readonly ShippingContextInterface $shippingContext,
        private readonly RealtimeCarrierRateContributorInterface $realtimeContributor
    ) {
        if (trim($this->carrierCode) === '') {
            throw new \InvalidArgumentException('Carrier rate execution requires a non-empty carrier code.');
        }
        RateSourceMode::assertKnown($this->rateSourceMode);
        AddressResolutionPolicy::assertKnown($this->addressResolutionPolicy);
    }

    public function getCarrierCode(): string
    {
        return $this->carrierCode;
    }

    public function getDestinationScope(): string
    {
        return $this->destinationScope;
    }

    public function getAllowedZoneCodes(): array
    {
        return $this->allowedZoneCodes;
    }

    public function getDestinationProvinceCode(): string
    {
        return $this->destinationProvinceCode;
    }

    public function getDestinationWardCode(): ?string
    {
        return $this->destinationWardCode;
    }

    public function getRateSourceMode(): string
    {
        return $this->rateSourceMode;
    }

    public function getAddressResolutionPolicy(): string
    {
        return $this->addressResolutionPolicy;
    }

    public function getCapability(): CarrierOperationAddressCapabilityInterface
    {
        return $this->capability;
    }

    public function getResolutionContext(): ShippingAddressResolutionContextInterface
    {
        return $this->resolutionContext;
    }

    public function getShippingContext(): ShippingContextInterface
    {
        return $this->shippingContext;
    }

    public function getRealtimeContributor(): RealtimeCarrierRateContributorInterface
    {
        return $this->realtimeContributor;
    }
}
