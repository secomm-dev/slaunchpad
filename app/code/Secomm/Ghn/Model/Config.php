<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Secomm\ShippingCore\Api\Address\AddressResolutionPolicy;
use Secomm\ShippingCore\Api\Rate\RateSourceMode;

/**
 * SPEC-FEAT-FQWEQ3 §9 — lean GHN configuration reader. The api_token config value is stored
 * encrypted (backend_model Magento\Config\Model\Config\Backend\Encrypted) and is decrypted here,
 * once, for consumers. The token is NEVER returned in any log context (§48).
 */
class Config
{
    /** Standard Delivery Methods placement (Sales → Delivery Methods → GHN Shipping). */
    public const XML_PATH_ENVIRONMENT = 'carriers/secomm_ghn/environment';
    public const XML_PATH_API_TOKEN = 'carriers/secomm_ghn/api_token';
    public const XML_PATH_SHOP_ID = 'carriers/secomm_ghn/shop_id';
    public const XML_PATH_ORIGIN_DISTRICT_ID = 'carriers/secomm_ghn/origin_district_id';
    public const XML_PATH_PAYMENT_TYPE = 'carriers/secomm_ghn/payment_type';
    public const XML_PATH_REQUIRED_NOTE = 'carriers/secomm_ghn/required_note';
    public const XML_PATH_DEBUG = 'carriers/secomm_ghn/debug';
    public const XML_PATH_WEBHOOK_SECRET = 'carriers/secomm_ghn/webhook_secret';
    public const XML_PATH_TRACKING_REFRESH_ENABLED = 'carriers/secomm_ghn/tracking_refresh_enabled';
    public const XML_PATH_TRACKING_REFRESH_THRESHOLD_HOURS = 'carriers/secomm_ghn/tracking_refresh_threshold_hours';
    public const XML_PATH_CONNECTION_TIMEOUT = 'carriers/secomm_ghn/connection_timeout';
    public const XML_PATH_REQUEST_TIMEOUT = 'carriers/secomm_ghn/request_timeout';
    public const XML_PATH_RATE_ADJ_ENABLED = 'carriers/secomm_ghn/rate_adjustment_enabled';
    public const XML_PATH_RATE_ADJ_TYPE = 'carriers/secomm_ghn/rate_adjustment_type';
    public const XML_PATH_RATE_ADJ_VALUE = 'carriers/secomm_ghn/rate_adjustment_value';
    public const XML_PATH_RATE_ADJ_ROUNDING = 'carriers/secomm_ghn/rate_adjustment_rounding';
    public const XML_PATH_RATE_ADJ_APPLY_TO = 'carriers/secomm_ghn/rate_adjustment_apply_to';
    public const XML_PATH_RATE_SOURCE_MODE = 'carriers/secomm_ghn/rate_source_mode';
    public const XML_PATH_ADDRESS_RESOLUTION_POLICY = 'carriers/secomm_ghn/address_resolution_policy';

    public const ENV_SANDBOX = 'sandbox';
    public const ENV_PRODUCTION = 'production';

    /**
     * GHN public API base URLs (developer.ghn.vn). Kept as constants — an environment switch is a
     * full profile switch (DEC-FEATYA2C0W-004 D3), not a free-form URL field.
     */
    private const BASE_URLS = [
        self::ENV_SANDBOX => 'https://dev-online-gateway.ghn.vn/shiip/public-api',
        self::ENV_PRODUCTION => 'https://online-gateway.ghn.vn/shiip/public-api',
    ];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function getEnvironment(?int $storeId = null): string
    {
        $environment = (string) $this->scopeConfig->getValue(
            self::XML_PATH_ENVIRONMENT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return isset(self::BASE_URLS[$environment]) ? $environment : self::ENV_SANDBOX;
    }

    /**
     * Decrypted GHN API token. Empty string when not configured.
     */
    public function getApiToken(?int $storeId = null): string
    {
        $encrypted = (string) $this->scopeConfig->getValue(
            self::XML_PATH_API_TOKEN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($encrypted === '') {
            return '';
        }

        return (string) $this->encryptor->decrypt($encrypted);
    }

    public function getShopId(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_SHOP_ID, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * GHN legacy district ID the shop ships FROM (fee `from_district_id` — optional per the
     * current Calculate Fee contract, default = the shop's registered GHN address).
     * 0 = unset → the payload simply omits the field (shop default applies provider-side).
     */
    public function getOriginDistrictId(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_ORIGIN_DISTRICT_ID,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Who pays the shipping fee on the GHN order: 1 = shop/seller, 2 = buyer/recipient.
     * Launchpad default 1 — Magento already charges shipping at checkout, so GHN collecting
     * from the recipient would double-charge. Deliberately NOT inferred from the payment
     * method (GHN payment_type_id is unrelated to COD, current docs 2026-09-11).
     */
    public function getPaymentType(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_PAYMENT_TYPE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getRequiredNote(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_REQUIRED_NOTE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isDebugEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_DEBUG, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * TASK-GKHXY1 (GHN-E1) — shared secret the merchant also configures as a GHN custom callback
     * header. GHN webhooks carry NO signature: possession of this secret IS the whole auth model
     * (documented — no cryptographic verification is claimed). Empty = webhook rejects all calls.
     */
    public function getWebhookSecret(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_WEBHOOK_SECRET,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /** Opt-in reconciliation refresh (r3-style lean mirror of the Ghtk precedent). */
    public function isTrackingRefreshEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_TRACKING_REFRESH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getTrackingRefreshThresholdHours(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_TRACKING_REFRESH_THRESHOLD_HOURS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getConnectionTimeout(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_CONNECTION_TIMEOUT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getRequestTimeout(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_REQUEST_TIMEOUT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getBaseUrl(?int $storeId = null): string
    {
        return self::BASE_URLS[$this->getEnvironment($storeId)] ?? self::BASE_URLS[self::ENV_SANDBOX];
    }

    /**
     * TASK-WAWNDS — GHN-carrier rate adjustment (post-rate only; never affects eligibility).
     */
    public function isRateAdjustmentEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_RATE_ADJ_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getRateAdjustmentType(?int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_PATH_RATE_ADJ_TYPE, ScopeInterface::SCOPE_STORE, $storeId) ?: "fixed");
    }

    public function getRateAdjustmentValue(?int $storeId = null): float
    {
        return (float) ($this->scopeConfig->getValue(self::XML_PATH_RATE_ADJ_VALUE, ScopeInterface::SCOPE_STORE, $storeId) ?: 0);
    }

    public function getRateAdjustmentRounding(?int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_PATH_RATE_ADJ_ROUNDING, ScopeInterface::SCOPE_STORE, $storeId) ?: "none");
    }

    public function getRateAdjustmentApplyTo(?int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_PATH_RATE_ADJ_APPLY_TO, ScopeInterface::SCOPE_STORE, $storeId) ?: "carrier_rate_only");
    }

    /**
     * TASK-MD2BD3 (v10) — RateSourceMode consumed from the frozen ShippingCore contract.
     * Thin adapter read ONLY: Secomm_Ghn never re-evaluates the mode inside provider logic;
     * unrecognized persisted values fail closed to the shared default (CARRIER_WITH_FALLBACK).
     */
    public function getRateSourceMode(?int $storeId = null): string
    {
        $mode = (string) ($this->scopeConfig->getValue(self::XML_PATH_RATE_SOURCE_MODE, ScopeInterface::SCOPE_STORE, $storeId) ?: '');
        $allowed = [RateSourceMode::CARRIER_ONLY, RateSourceMode::CARRIER_WITH_FALLBACK, RateSourceMode::FALLBACK_ONLY];

        return in_array($mode, $allowed, true) ? $mode : RateSourceMode::CARRIER_WITH_FALLBACK;
    }

    /**
     * TASK-MD2BD3 (v10) — AddressResolutionPolicy for the GHN RATE operation (legacy
     * PRE-2025 consumer). Passed through to the shared handoff service — selection/ranking
     * never happens in GHN. Unrecognized values fail closed to FALLBACK.
     */
    public function getAddressResolutionPolicy(?int $storeId = null): string
    {
        $policy = (string) ($this->scopeConfig->getValue(self::XML_PATH_ADDRESS_RESOLUTION_POLICY, ScopeInterface::SCOPE_STORE, $storeId) ?: '');
        $allowed = [AddressResolutionPolicy::STRICT, AddressResolutionPolicy::FALLBACK, AddressResolutionPolicy::PICK_PRIMARY];

        return in_array($policy, $allowed, true) ? $policy : AddressResolutionPolicy::FALLBACK;
    }
}
