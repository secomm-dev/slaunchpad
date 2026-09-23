<?php
/*
 * TASK-78PVR1 (architecture v9/v10 §35) — per-member RateSourceMode / AddressResolutionPolicy
 * composition reader.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Secomm\ShippingCore\Api\Address\AddressResolutionPolicy;
use Secomm\ShippingCore\Api\Rate\RateSourceMode;

/**
 * Reads the frozen ShippingCore v10 orchestration configuration for a fallback-group MEMBER
 * carrier, by the shared config-path convention `carriers/<carrier_code>/<field>` — the same
 * paths the carrier modules themselves consume (e.g. Secomm_Ghn Config::XML_PATH_*). The
 * bridge therefore stays composition-level: no carrier module dependency, no carrier-specific
 * policy logic, no candidate/candidate-count inspection (architecture §35.4 — selection and
 * ranking live in the shared handoff/selector, never here).
 *
 * Unrecognized persisted values fail closed to the shared v10 defaults (§35.7):
 * RateSourceMode = CARRIER_WITH_FALLBACK, AddressResolutionPolicy = FALLBACK.
 */
class MemberRatePolicy
{
    public const MODE_CONFIG_TEMPLATE = 'carriers/%s/rate_source_mode';
    public const POLICY_CONFIG_TEMPLATE = 'carriers/%s/address_resolution_policy';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function rateSourceMode(string $carrierCode, ?int $storeId = null): string
    {
        $mode = (string) $this->scopeConfig->getValue(
            sprintf(self::MODE_CONFIG_TEMPLATE, $carrierCode),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        try {
            if ($mode !== '' && RateSourceMode::exists($mode)) {
                return $mode;
            }
        } catch (LocalizedException) {
            // fall through to the shared default
        }

        return RateSourceMode::CARRIER_WITH_FALLBACK;
    }

    public function addressResolutionPolicy(string $carrierCode, ?int $storeId = null): string
    {
        $policy = (string) $this->scopeConfig->getValue(
            sprintf(self::POLICY_CONFIG_TEMPLATE, $carrierCode),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        try {
            if ($policy !== '' && AddressResolutionPolicy::exists($policy)) {
                return $policy;
            }
        } catch (LocalizedException) {
            // fall through to the shared default
        }

        return AddressResolutionPolicy::FALLBACK;
    }
}
