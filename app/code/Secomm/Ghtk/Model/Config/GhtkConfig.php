<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Single config abstraction for the GHTK carrier (DEC-022/023 — "một API client/config
 * abstraction duy nhất"). Centralises every carriers/ghtk/* read + decrypts the API
 * token (ZaloPay precedent). Nothing else should read carriers/ghtk/* config directly.
 */
class GhtkConfig
{
    public const XML_PATH_PREFIX = 'carriers/ghtk/';
    public const DEFAULT_BASE_URL = 'https://services.giaohangtietkiem.vn';

    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private EncryptorInterface $encryptor
    ) {
    }

    public function isActive(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_PREFIX . 'active', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getTitle(?int $storeId = null): string
    {
        return (string) $this->getValue('title', $storeId);
    }

    public function getName(?int $storeId = null): string
    {
        return (string) $this->getValue('name', $storeId);
    }

    public function getApiBaseUrl(?int $storeId = null): string
    {
        $value = trim((string) $this->getValue('api_base_url', $storeId));

        return $value !== '' ? rtrim($value, '/') : self::DEFAULT_BASE_URL;
    }

    /**
     * Decrypted API token. Falls back to the raw value if it is not ciphertext
     * (e.g. a plaintext token during local testing) — never throws.
     */
    public function getApiToken(?int $storeId = null): string
    {
        $encrypted = (string) $this->getValue('api_token', $storeId);
        if ($encrypted === '') {
            return '';
        }

        try {
            return $this->encryptor->decrypt($encrypted);
        } catch (\Throwable $e) {
            return $encrypted;
        }
    }

    public function getClientSource(?int $storeId = null): string
    {
        return (string) $this->getValue('x_client_source', $storeId);
    }

    public function getTransport(?int $storeId = null): string
    {
        return (string) $this->getValue('transport', $storeId);
    }

    public function getWeightUnit(?int $storeId = null): string
    {
        $unit = (string) $this->getValue('weight_unit', $storeId);

        return $unit === 'g' ? 'g' : 'kg';
    }

    public function getMinWeight(?int $storeId = null): float
    {
        return (float) $this->getValue('min_weight', $storeId);
    }

    public function getTimeoutConnect(?int $storeId = null): int
    {
        return $this->getInt('timeout_connect', 2, $storeId);
    }

    public function getTimeoutTotal(?int $storeId = null): int
    {
        return $this->getInt('timeout_total', 5, $storeId);
    }

    public function getRetryMax(?int $storeId = null): int
    {
        return max(0, $this->getInt('retry_max', 1, $storeId));
    }

    public function getCacheTtl(?int $storeId = null): int
    {
        return $this->getInt('cache_ttl', 600, $storeId);
    }

    /**
     * @return string[] Selected rate components added on top of base fee.fee (DEC-022 Q1).
     */
    public function getRateInclude(?int $storeId = null): array
    {
        $value = (string) $this->getValue('rate_include', $storeId);
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * Payment method codes treated as COD (SL-016 / DEC-SL016-001 §4) — the
     * CodAmountResolver only collects for these; everything else is prepaid
     * (pick_money = 0). Default: Magento's offline "cashondelivery".
     *
     * @return string[]
     */
    public function getCodMethodCodes(?int $storeId = null): array
    {
        $value = (string) $this->getValue('cod_method_codes', $storeId);
        if ($value === '') {
            return ['cashondelivery'];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * Decrypted webhook secret (SL-017 / DEC-SL017-001 §4 — GHTK has no
     * documented HMAC; the secret rides in the webhook URL query instead).
     * Falls back to the raw value when not ciphertext; empty disables the check.
     */
    public function getWebhookSecret(?int $storeId = null): string
    {
        $encrypted = trim((string) $this->getValue('webhook_secret', $storeId));
        if ($encrypted === '') {
            return '';
        }

        try {
            return trim($this->encryptor->decrypt($encrypted));
        } catch (\Throwable $e) {
            return $encrypted;
        }
    }

    /**
     * Tracking reconciliation cron opt-in (SL-017 — webhook stays primary).
     */
    public function isTrackingRefreshEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_PREFIX . 'tracking_refresh_enabled', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getTrackingRefreshThresholdHours(?int $storeId = null): int
    {
        return max(1, $this->getInt('tracking_refresh_threshold_hours', 6, $storeId));
    }

    public function getPickAddressId(?int $storeId = null): string
    {
        return trim((string) $this->getValue('pick_address_id', $storeId));
    }

    public function getPickProvince(?int $storeId = null): string
    {
        return trim((string) $this->getValue('pick_province', $storeId));
    }

    public function getPickDistrict(?int $storeId = null): string
    {
        return trim((string) $this->getValue('pick_district', $storeId));
    }

    public function getPickWard(?int $storeId = null): string
    {
        return trim((string) $this->getValue('pick_ward', $storeId));
    }

    public function getSpecificerrmsg(?int $storeId = null): string
    {
        $msg = (string) $this->getValue('specificerrmsg', $storeId);

        return $msg !== '' ? $msg : 'This shipping method is not available for your address.';
    }

    public function isShowMethod(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_PREFIX . 'showmethod', ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function getValue(string $field, ?int $storeId): mixed
    {
        return $this->scopeConfig->getValue(self::XML_PATH_PREFIX . $field, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function getInt(string $field, int $default, ?int $storeId): int
    {
        $value = $this->getValue($field, $storeId);
        if ($value === null || $value === '' || !ctype_digit((string) $value)) {
            return $default;
        }

        return (int) $value;
    }
}
