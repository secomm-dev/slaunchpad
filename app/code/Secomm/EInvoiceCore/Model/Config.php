<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Provider-agnostic electronic invoice configuration.
 */
class Config
{
    public const XML_PATH_ENABLED = 'secomm_einvoice/general/enabled';
    public const XML_PATH_PROVIDER = 'secomm_einvoice/general/provider';
    public const XML_PATH_ISSUE_TRIGGER = 'secomm_einvoice/issuance/issue_trigger';
    public const XML_PATH_AUTO_ISSUE = 'secomm_einvoice/issuance/auto_issue';
    public const XML_PATH_AUTO_CREDITMEMO_ADJUSTMENT = 'secomm_einvoice/issuance/auto_creditmemo_adjustment';
    public const XML_PATH_COMMERCIAL_DISCOUNT_ENABLED = 'secomm_einvoice/issuance/commercial_discount_enabled';

    public const PROVIDER_MISA = 'misa';
    public const PROVIDER_VIETTEL = 'viettel';

    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_ON_SHIPMENT = 'on_shipment';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Whether electronic invoice is enabled for the store.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Configured provider code for the store.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getProvider(?int $storeId = null): string
    {
        $provider = (string) $this->scopeConfig->getValue(
            self::XML_PATH_PROVIDER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $provider !== '' ? $provider : self::PROVIDER_MISA;
    }

    /**
     * Event trigger that may schedule invoice issuance.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getIssueTrigger(?int $storeId = null): string
    {
        $trigger = (string) $this->scopeConfig->getValue(
            self::XML_PATH_ISSUE_TRIGGER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $trigger !== '' ? $trigger : self::TRIGGER_MANUAL;
    }

    /**
     * Whether automatic issuance is enabled for the configured trigger.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isAutoIssue(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_AUTO_ISSUE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Whether an adjustment invoice is issued automatically when a credit memo is created.
     */
    public function isAutoCreditmemoAdjustment(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_AUTO_CREDITMEMO_ADJUSTMENT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Whether commercial discount (CKTM, ReferenceType=5) is available in admin.
     */
    public function isCommercialDiscountEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_COMMERCIAL_DISCOUNT_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
