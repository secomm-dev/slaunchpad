<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * FEAT-31X6N2 — typed read access to the secomm_tracking config tree.
 * Scaffold scope (TASK-0TTQRX): getters only, no consumers yet.
 */
class Config
{
    public const XML_PATH_ENABLED = 'secomm_tracking/general/enabled';
    public const XML_PATH_DEBUG_LOG = 'secomm_tracking/general/debug_log';

    public const XML_PATH_TIKTOK_ENABLED = 'secomm_tracking/tiktok/enabled';
    public const XML_PATH_TIKTOK_PIXEL_ID = 'secomm_tracking/tiktok/pixel_id';
    public const XML_PATH_TIKTOK_ACCESS_TOKEN = 'secomm_tracking/tiktok/access_token';
    public const XML_PATH_TIKTOK_TEST_MODE = 'secomm_tracking/tiktok/test_mode';

    public const XML_PATH_REFUND_ENABLED = 'secomm_tracking/refund/enabled';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * Master switch — every pipeline (browser + server) is gated on this.
     *
     * @param string|null $scopeCode
     * @return bool
     */
    public function isEnabled(?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * @param string|null $scopeCode
     * @return bool
     */
    public function isDebugLog(?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_DEBUG_LOG, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * DEC-FEAT31X6N2-002: Meta getters removed — Magefan FacebookPixel(+Extra)
     * owns all Meta tracking. This module owns TikTok only.
     *
     * @param string|null $scopeCode
     * @return bool
     */
    public function isTiktokEnabled(?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_TIKTOK_ENABLED, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * @param string|null $scopeCode
     * @return string
     */
    public function getTiktokPixelId(?string $scopeCode = null): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_TIKTOK_PIXEL_ID, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * Empty/unset token returns '' — never throws.
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getTiktokAccessToken(?string $scopeCode = null): string
    {
        return $this->decryptConfigValue(self::XML_PATH_TIKTOK_ACCESS_TOKEN, $scopeCode);
    }

    /**
     * Read a credential config value. Encrypted envelopes
     * ("keyVersion:cryptVersion:iv:cipher" — saved via the admin form) are
     * decrypted; a plain value (e.g. set via `bin/magento config:set` without
     * --encrypt) is returned untouched — decrypting it would mangle it into
     * garbage. Empty/unset returns ''. Never throws: a misconfigured credential
     * degrades to its raw/empty value, it must not crash the flush cron.
     *
     * @param string $xmlPath
     * @param string|null $scopeCode
     * @return string
     */
    private function decryptConfigValue(string $xmlPath, ?string $scopeCode): string
    {
        $value = (string)$this->scopeConfig->getValue($xmlPath, ScopeInterface::SCOPE_STORE, $scopeCode);
        if ($value === '') {
            return '';
        }

        // Cipher envelopes start with numeric "keyVersion:cryptVersion:" — hex/
        // base64 tokens never match this shape.
        if (!preg_match('/^\d+:\d+:/', $value)) {
            return $value;
        }

        try {
            return $this->encryptor->decrypt($value);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @param string|null $scopeCode
     * @return bool
     */
    public function isTiktokTestMode(?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_TIKTOK_TEST_MODE, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * Refund events are opt-in (default OFF) — SPEC §9 / AC-006.
     *
     * @param string|null $scopeCode
     * @return bool
     */
    public function isRefundEnabled(?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_REFUND_ENABLED, ScopeInterface::SCOPE_STORE, $scopeCode);
    }
}
