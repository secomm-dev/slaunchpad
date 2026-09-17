<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietQr\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Provides access to the VietQR payment method's system configuration values
 * (TASK-N35E28 / SPEC-FEAT-ZKD4VA §3 AC-001..AC-004).
 *
 * Reads from the `payment/secomm_vietqr/*` config paths at the store scope.
 */
class Config
{
    private const XML_PATH_PREFIX = 'payment/secomm_vietqr/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . 'active',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function isDebugEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . 'debug',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'title',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Uploaded logo filename relative to media/vietqr/ (empty when not uploaded).
     *
     * @return string
     */
    public function getLogo(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'logo',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getBankCode(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'bank_code',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getBankAccount(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'bank_account',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getAccountName(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'account_name',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getApiEndpoint(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'api_endpoint',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getApiClientId(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'api_client_id',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'api_key',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return int
     */
    public function getRequestTimeout(): int
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'request_timeout',
            ScopeInterface::SCOPE_STORE
        ) ?: 10;
    }

    /**
     * @return string
     */
    public function getTransferContentTemplate(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'transfer_content_template',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getPaymentInstructions(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'payment_instructions',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getNewOrderStatus(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'order_status',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return string
     */
    public function getAwaitingConfirmStatus(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'awaiting_confirm_status',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getCustomerConfirmComment(): string
    {
        $comment = (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'customer_confirm_comment',
            ScopeInterface::SCOPE_STORE
        );

        return $comment !== '' ? $comment : (string)__('Customer confirmed bank transfer via VietQR page.');
    }

    /**
     * Whether the auto-cancel cron is enabled for the given store (AC-024).
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isAutoCancelEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . 'autocancel_active',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Max minutes an order may stay pending before auto-cancel (AC-024).
     *
     * @param int|null $storeId
     * @return int
     */
    public function getAutoCancelTimeout(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'autocancel_timeout',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 1440;
    }

    /**
     * Cancel reason recorded in the order status history (AC-024).
     *
     * @param int|null $storeId
     * @return string
     */
    public function getAutoCancelReason(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'autocancel_reason',
            ScopeInterface::SCOPE_STORE,
            $storeId
        )) ?: (string)__('Canceled automatically because payment timeout exceeded.');
    }
}
