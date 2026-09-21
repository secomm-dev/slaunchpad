<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Address;

use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Address\ResolvedShippingAddressInterface;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;

/**
 * TASK-T78YH6 — immutable, self-guarding carrier handoff VO; @see CarrierAddressHandoffInterface.
 *
 * Invariants enforced HERE so no orchestration code can hand a carrier a contradictory outcome:
 * a non-applicable handoff never carries a resolved address, a resolved handoff never advertises
 * fallback or a failure, and an unresolved-but-applicable handoff always names the canonical
 * reason.
 */
final class CarrierAddressHandoff implements CarrierAddressHandoffInterface
{
    /**
     * @param bool $applicable false only for destinations outside the Vietnam canonical scope
     * @param ResolvedShippingAddressInterface|null $resolvedAddress EXACT/MAPPED result; null otherwise
     * @param bool $textualFallbackEligible ALLOWED carrier-side textual fallback (unresolved + capability)
     * @param string|null $failureReason REASON_* when not resolved; null when resolved
     * @param string[] $candidateCodes AMBIGUOUS candidates (TASK-7AJ3K8 — carrier disambiguation
     *                                 policy needs to distinguish AMBIGUOUS from UNMAPPED; never
     *                                 a pick list — candidates are never auto-selected)
     * @throws \LogicException on any contradictory combination
     */
    public function __construct(
        private readonly bool $applicable,
        private readonly ?ResolvedShippingAddressInterface $resolvedAddress,
        private readonly bool $textualFallbackEligible,
        private readonly ?string $failureReason,
        private readonly array $candidateCodes = [],
        private readonly array $supportedRepresentations = []
    ) {
        if (!$applicable) {
            if ($resolvedAddress !== null) {
                throw new \LogicException('A non-applicable handoff must not carry a resolved address.');
            }
            if ($failureReason === null || trim($failureReason) === '') {
                throw new \LogicException('A non-applicable handoff requires a failure reason.');
            }

            return;
        }

        if ($resolvedAddress !== null) {
            if ($textualFallbackEligible) {
                throw new \LogicException('A resolved handoff must not advertise textual fallback.');
            }
            if ($failureReason !== null) {
                throw new \LogicException('A resolved handoff must not carry a failure reason.');
            }

            return;
        }

        if ($failureReason !== ShippingFailureReason::CANONICAL_UNRESOLVED) {
            throw new \LogicException(
                sprintf('An unresolved handoff requires reason %s.', ShippingFailureReason::CANONICAL_UNRESOLVED)
            );
        }
    }

    public function isApplicable(): bool
    {
        return $this->applicable;
    }

    public function getResolvedAddress(): ?ResolvedShippingAddressInterface
    {
        return $this->resolvedAddress;
    }

    public function isTextualFallbackEligible(): bool
    {
        return $this->textualFallbackEligible;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    /**
     * AMBIGUOUS candidates (empty for every other state) — diagnostic/policy input for the
     * carrier, NEVER a pick list (DEC-FEATYA2C0W-004 D9).
     *
     * @return string[]
     */
    public function getCandidateCodes(): array
    {
        return $this->candidateCodes;
    }

    /**
     * TASK-Y3X6H5 — representation CATEGORIES for the operation (AddressRepresentation::*) —
     * the concrete provider values are rendered carrier-side (Stage 2). Empty for legacy
     * per-carrier handoff paths.
     *
     * @return string[]
     */
    public function getSupportedRepresentations(): array
    {
        return $this->supportedRepresentations;
    }
}
