<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PancakeBridge\Model\Config;

use Secomm\PancakeFunction\Api\PosApiConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class PancakeConfig implements PosApiConfigInterface
{
    public const XML_PATH_ENABLED = 'pancake/general/enabled';
    public const XML_PATH_BASE_URL = 'pancake/api/base_url';
    public const XML_PATH_SHOP_ID = 'pancake/api/shop_id';
    public const XML_PATH_API_KEY = 'pancake/api/api_key';
    public const XML_PATH_WEBHOOK_SECRET = 'pancake/webhook/secret';
    public const XML_PATH_POLL_ENABLED = 'pancake/poll/enabled';
    public const XML_PATH_ENABLE_LOG = 'pancake/general/enable_log';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Whether Pancake file logs may be written under var/log/fulfillment/pancake/.
     *
     * @param int|null $storeId Store scope; null uses default config scope
     */
    public function isLogEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLE_LOG, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isPollEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_POLL_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getBaseUrl(?int $storeId = null): string
    {
        $url = (string) $this->scopeConfig->getValue(self::XML_PATH_BASE_URL, ScopeInterface::SCOPE_STORE, $storeId);
        return rtrim($url !== '' ? $url : 'https://pos.pages.fm/api/v1', '/');
    }

    public function getShopId(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(self::XML_PATH_SHOP_ID, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getApiKey(?int $storeId = null): string
    {
        $encrypted = (string) $this->scopeConfig->getValue(self::XML_PATH_API_KEY, ScopeInterface::SCOPE_STORE, $storeId);
        if ($encrypted === '') {
            return '';
        }
        try {
            return $this->encryptor->decrypt($encrypted);
        } catch (\Throwable) {
            return $encrypted;
        }
    }

    public function getWebhookSecret(?int $storeId = null): string
    {
        $encrypted = (string) $this->scopeConfig->getValue(
            self::XML_PATH_WEBHOOK_SECRET,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($encrypted === '') {
            return '';
        }
        try {
            return $this->encryptor->decrypt($encrypted);
        } catch (\Throwable) {
            return $encrypted;
        }
    }
}
