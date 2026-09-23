<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Address;

use Magento\Quote\Model\Quote\Address;
use Secomm\ShippingCore\Api\Address\CarrierAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\CarrierOperationAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffServiceInterface;
use Secomm\ShippingCore\Api\Address\DestinationContextBuilderInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionManagerInterface;
use Secomm\ShippingCore\Api\Address\AddressResolutionPolicy;
use Secomm\VietNamAddress\Api\Data\VnPrimaryCandidateSelectionInterface;
use Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface;
use Secomm\VietNamAddress\Api\VnPrimaryCandidateSelectorInterface;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Model\Address\Exception\UnsupportedDestinationException;

/**
 * TASK-T78YH6 (Phase E-C0) — @see CarrierAddressHandoffServiceInterface.
 *
 * TASK-7AJ3K8 adds the prebuilt-context entry (handoffContext) for non-quote address sources;
 * both entries share ONE translation into the carrier-facing handoff. The manager is the sole
 * resolution path (its request cache keeps one canonical lookup per identity); the non-VN
 * exception stays a ShippingCore internal and is translated here — carriers never catch it.
 * Expected states are returned as explicit handoff state, not logged; the external resolver
 * pool and Magento rate outcomes are later-phase concerns and are never touched here.
 */
final class CarrierAddressHandoffService implements CarrierAddressHandoffServiceInterface
{
    public function __construct(
        private readonly DestinationContextBuilderInterface $contextBuilder,
        private readonly ShippingAddressResolutionManagerInterface $resolutionManager,
        private readonly VnPrimaryCandidateSelectorInterface $primaryCandidateSelector
    ) {
    }

    /**
     * @inheritDoc
     */
    public function handoff(
        Address $destination,
        CarrierAddressCapabilityInterface $capability
    ): CarrierAddressHandoffInterface {
        return $this->handoffContext(
            $this->contextBuilder->build($destination, $capability),
            $capability
        );
    }

    /**
     * @inheritDoc
     */
    public function handoffContext(
        ShippingAddressResolutionContextInterface $context,
        CarrierAddressCapabilityInterface $capability
    ): CarrierAddressHandoffInterface {
        try {
            $resolved = $this->resolutionManager->resolve($context, $capability);
        } catch (UnsupportedDestinationException) {
            return new CarrierAddressHandoff(
                applicable: false,
                resolvedAddress: null,
                textualFallbackEligible: false,
                failureReason: ShippingFailureReason::UNSUPPORTED_DESTINATION
            );
        }

        if ($resolved->isResolved()) {
            return new CarrierAddressHandoff(
                applicable: true,
                resolvedAddress: $resolved,
                textualFallbackEligible: false,
                failureReason: null
            );
        }

        return new CarrierAddressHandoff(
            applicable: true,
            resolvedAddress: null,
            textualFallbackEligible: $capability->supportsTextualFallback(),
            failureReason: ShippingFailureReason::CANONICAL_UNRESOLVED,
            candidateCodes: $resolved->getCandidateCodes()
        );
    }

    /**
     * @inheritDoc
     */
    public function handoffForOperation(
        Address $destination,
        CarrierOperationAddressCapabilityInterface $capability,
        string $operation,
        string $addressResolutionPolicy = AddressResolutionPolicy::FALLBACK
    ): CarrierAddressHandoffInterface {
        return $this->handoffContextForOperation(
            $this->contextBuilder->buildForOperation($destination, $capability, $operation),
            $capability,
            $operation,
            $addressResolutionPolicy
        );
    }

    /**
     * @inheritDoc
     */
    public function handoffContextForOperation(
        ShippingAddressResolutionContextInterface $context,
        CarrierOperationAddressCapabilityInterface $capability,
        string $operation,
        string $addressResolutionPolicy = AddressResolutionPolicy::FALLBACK
    ): CarrierAddressHandoffInterface {
        ShippingAddressOperation::assertKnown($operation);
        // TASK-MD2BD3 (v10 wiring completion) — fail-fast on unknown policies (mirror of the
        // operation guard): an unrecognized policy must never silently behave as FALLBACK.
        AddressResolutionPolicy::assertKnown($addressResolutionPolicy);

        $requiredScheme = $capability->getRequiredScheme($operation);
        if ($context->getTargetScheme() !== $requiredScheme) {
            // Fail-fast mirror of the E-SL2 aggregate/code mismatch guard: an aggregate built
            // for another scheme must never be silently resolved against this operation.
            throw new \LogicException(
                sprintf(
                    'Context targets scheme "%s" but operation "%s" requires scheme "%s".',
                    $context->getTargetScheme(),
                    $operation,
                    $requiredScheme
                )
            );
        }

        // The adapter funnels the per-operation scheme/textual-fallback through the legacy
        // per-carrier shape consumed by the (request-cached) resolution manager.
        $adaptedCapability = new OperationCapabilityAdapter($capability, $operation);

        try {
            $resolved = $this->resolutionManager->resolve($context, $adaptedCapability);
        } catch (UnsupportedDestinationException) {
            return new CarrierAddressHandoff(
                applicable: false,
                resolvedAddress: null,
                textualFallbackEligible: false,
                failureReason: ShippingFailureReason::UNSUPPORTED_DESTINATION
            );
        }

        $representations = $capability->getSupportedRepresentations($operation);

        if ($resolved->isResolved()) {
            return new CarrierAddressHandoff(
                applicable: true,
                resolvedAddress: $resolved,
                textualFallbackEligible: false,
                failureReason: null,
                candidateCodes: $resolved->getCandidateCodes(),
                supportedRepresentations: $representations
            );
        }

        // TASK-MD2BD3 (v10) — AddressResolutionPolicy applies to AMBIGUOUS outcomes only.
        $candidateCodes = $resolved->getCandidateCodes();
        if ($candidateCodes !== [] && $addressResolutionPolicy !== AddressResolutionPolicy::FALLBACK) {
            if ($addressResolutionPolicy === AddressResolutionPolicy::STRICT) {
                // STRICT: unresolved + NO ambiguity-driven fallback — candidates are dropped
                // so no downstream layer can auto-pick from them.
                return new CarrierAddressHandoff(
                    applicable: true,
                    resolvedAddress: null,
                    textualFallbackEligible: false,
                    failureReason: ShippingFailureReason::CANONICAL_UNRESOLVED,
                    candidateCodes: [],
                    supportedRepresentations: $representations
                );
            }

            // PICK_PRIMARY: deterministic curated-primary selection via the shared
            // Secomm_VietNamAddress selector. NO_DESIGNATED_PRIMARY / MULTIPLE_PRIMARY /
            // NOT_APPLICABLE fail closed to an unresolved, non-textual-fallback handoff
            // (never a first-candidate guess — DEC-FEATYA2C0W-006 amendment).
            $selection = $this->primaryCandidateSelector->selectPrimary(
                (string) $context->getSourceScheme(),
                (string) $context->getSourceUnitCode(),
                $context->getTargetScheme(),
                $candidateCodes
            );
            if ($selection->getStatus() === VnPrimaryCandidateSelectionInterface::STATUS_SELECTED
                && $selection->getSelectedCode() !== null
            ) {
                return new CarrierAddressHandoff(
                    applicable: true,
                    resolvedAddress: new ResolvedShippingAddress(
                        VnAddressResolutionInterface::STATUS_MAPPED,
                        $context->getTargetScheme(),
                        $selection->getSelectedCode()
                    ),
                    textualFallbackEligible: false,
                    failureReason: null,
                    candidateCodes: [],
                    supportedRepresentations: $representations
                );
            }

            return new CarrierAddressHandoff(
                applicable: true,
                resolvedAddress: null,
                textualFallbackEligible: false,
                failureReason: ShippingFailureReason::CANONICAL_UNRESOLVED,
                candidateCodes: [],
                supportedRepresentations: $representations
            );
        }

        return new CarrierAddressHandoff(
            applicable: true,
            resolvedAddress: null,
            textualFallbackEligible: $capability->supportsTextualFallback($operation),
            failureReason: ShippingFailureReason::CANONICAL_UNRESOLVED,
            candidateCodes: $candidateCodes,
            supportedRepresentations: $representations
        );
    }
}
