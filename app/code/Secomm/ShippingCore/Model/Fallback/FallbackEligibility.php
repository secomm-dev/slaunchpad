<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Fallback;

use Secomm\ShippingCore\Api\Fallback\FallbackEligibilityInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackEligibilitySource;

/**
 * TASK-5JQYMP — immutable fallback eligibility state VO; @see FallbackEligibilityInterface.
 *
 * TASK-8MQHJX Phase C amendment: INTEGRATION_LIMITATION source added (TL-approved, closes the
 * representational gap in frozen v10 §35.5). Named factories are the ergonomic construction
 * path; the constructor stays public for parity with the module's other VOs.
 */
final class FallbackEligibility implements FallbackEligibilityInterface
{
    /**
     * @param bool $technical TECHNICAL_FALLBACK source applies
     * @param bool $legacyAddress LEGACY_ADDRESS_FALLBACK source applies
     * @param bool $integration INTEGRATION_LIMITATION source applies
     */
    public function __construct(
        private readonly bool $technical = false,
        private readonly bool $legacyAddress = false,
        private readonly bool $integration = false
    ) {
    }

    public static function technical(): self
    {
        return new self(true, false, false);
    }

    public static function legacyAddress(): self
    {
        return new self(false, true, false);
    }

    public static function integrationLimitation(): self
    {
        return new self(false, false, true);
    }

    public static function none(): self
    {
        return new self(false, false, false);
    }

    /**
     * var_export round-trip: the DI compiler serializes object defaults (e.g. the
     * CarrierRateExecutionDecision constructor default) through __set_state — without this,
     * every generated-config include fatals at runtime.
     */
    public static function __set_state(array $state): self
    {
        return new self(
            (bool) ($state['technical'] ?? false),
            (bool) ($state['legacyAddress'] ?? false),
            (bool) ($state['integration'] ?? false)
        );
    }

    public function hasTechnicalFallbackEligibility(): bool
    {
        return $this->technical;
    }

    public function hasLegacyAddressFallbackEligibility(): bool
    {
        return $this->legacyAddress;
    }

    public function hasIntegrationLimitationEligibility(): bool
    {
        return $this->integration;
    }

    public function isEligible(): bool
    {
        return $this->technical || $this->legacyAddress || $this->integration;
    }

    /**
     * @return string[] FallbackEligibilitySource::* in deterministic order
     */
    public function getSources(): array
    {
        $sources = [];
        if ($this->technical) {
            $sources[] = FallbackEligibilitySource::TECHNICAL_FALLBACK;
        }
        if ($this->legacyAddress) {
            $sources[] = FallbackEligibilitySource::LEGACY_ADDRESS_FALLBACK;
        }
        if ($this->integration) {
            $sources[] = FallbackEligibilitySource::INTEGRATION_LIMITATION;
        }

        return $sources;
    }
}
