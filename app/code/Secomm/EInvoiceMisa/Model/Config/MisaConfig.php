<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * MeInvoice Integration API credentials and settings.
 */
class MisaConfig
{
    public const DEFAULT_BASE_URL = 'https://testapi.meinvoice.vn';
    public const SIGN_TYPE_HSM = 2;
    public const DEFAULT_SHIPPING_LINE_NAME = 'Phí vận chuyển';
    public const XML_PATH_BASE_URL = 'secomm_einvoice/misa/base_url';
    public const XML_PATH_APP_ID = 'secomm_einvoice/misa/app_id';
    public const XML_PATH_TAX_CODE = 'secomm_einvoice/misa/tax_code';
    public const XML_PATH_USERNAME = 'secomm_einvoice/misa/username';
    public const XML_PATH_PASSWORD = 'secomm_einvoice/misa/password';
    public const XML_PATH_SIGN_TYPE = 'secomm_einvoice/misa/sign_type';
    public const XML_PATH_USE_PREVIEW = 'secomm_einvoice/misa/use_preview_before_publish';
    public const XML_PATH_SEND_EMAIL_ON_ISSUE = 'secomm_einvoice/misa/send_email_on_issue';
    public const XML_PATH_INVOICE_WITH_CODE = 'secomm_einvoice/misa/invoice_with_code';
    public const XML_PATH_INVOICE_CALCULATING_MACHINE = 'secomm_einvoice/misa/invoice_calculating_machine';
    public const XML_PATH_CERTIFICATE_SN = 'secomm_einvoice/misa/certificate_sn';
    public const XML_PATH_INVOICE_TEMPLATE = 'secomm_einvoice/misa/invoice_template';
    public const XML_PATH_SHIPPING_LINE_NAME = 'secomm_einvoice/misa/shipping_line_name';

    /** @deprecated Legacy POC paths — read as fallback until migrated */
    private const LEGACY_PATH_APP_ID = 'secomm_einvoice/api/app_id';

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
     * MeInvoice API base URL for the store (trailing slash stripped).
     *
     * @param int|null $storeId
     * @return string
     */
    public function getBaseUrl(?int $storeId = null): string
    {
        $baseUrl = (string) $this->scopeConfig->getValue(self::XML_PATH_BASE_URL, ScopeInterface::SCOPE_STORE, $storeId);

        return rtrim($baseUrl !== '' ? $baseUrl : self::DEFAULT_BASE_URL, '/');
    }

    /**
     * Absolute URL for integration auth token endpoint.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getAuthTokenUrl(?int $storeId = null): string
    {
        return $this->getBaseUrl($storeId) . '/api/integration/auth/token';
    }

    /**
     * Base URL for invoice integration endpoints (publish, download, status, etc.).
     *
     * @param int|null $storeId
     * @return string
     */
    public function getInvoiceApiBaseUrl(?int $storeId = null): string
    {
        return $this->getBaseUrl($storeId) . '/api/integration/invoice';
    }

    /**
     * MISA application ID sent as Clientid header.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getAppId(?int $storeId = null): string
    {
        return $this->getScopedValue(self::XML_PATH_APP_ID, self::LEGACY_PATH_APP_ID, $storeId);
    }

    /**
     * Company tax code sent as CompanyTaxCode header.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getTaxCode(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_TAX_CODE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * MeInvoice integration username for token request.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getUsername(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_USERNAME, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Decrypted MeInvoice integration password for token request.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPassword(?int $storeId = null): string
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_PATH_PASSWORD, ScopeInterface::SCOPE_STORE, $storeId);
        if ($value === '') {
            return '';
        }

        return $this->encryptor->decrypt($value);
    }

    /**
     * HSM sign type sent on publish (defaults to SIGN_TYPE_HSM when unset).
     *
     * @param int|null $storeId
     * @return int
     */
    public function getSignType(?int $storeId = null): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_SIGN_TYPE, ScopeInterface::SCOPE_STORE, $storeId);

        return $value > 0 ? $value : self::SIGN_TYPE_HSM;
    }

    /**
     * Whether to call unpublished preview before HSM publish.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function usePreviewBeforePublish(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_USE_PREVIEW, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Whether MeInvoice should email the invoice to the buyer on publish (InvoiceData IsSendEmail).
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSendEmailOnIssue(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SEND_EMAIL_ON_ISSUE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Whether issued invoices use tax authority code (MeInvoice IsInvoiceCode / invoiceWithCode).
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isInvoiceWithCode(?int $storeId = null): bool
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_INVOICE_WITH_CODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value === null || $value === '' || (bool) $value;
    }

    /**
     * Whether invoices are calculating-machine (MTT) type (invoiceCalcu / IsInvoiceCalculatingMachine).
     */
    public function isInvoiceCalculatingMachine(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_INVOICE_CALCULATING_MACHINE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * HSM certificate serial number when multiple certificates are available (SignType=2).
     */
    public function getCertificateSn(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_CERTIFICATE_SN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Whether SignType requires post-publish status polling (async HSM).
     */
    public function requiresStatusPollAfterPublish(?int $storeId = null): bool
    {
        return in_array($this->getSignType($storeId), [3, 6], true);
    }

    /**
     * Configured default invoice template id (IPTemplateID) for the store.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getInvoiceTemplateId(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_INVOICE_TEMPLATE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * ItemName for the shipping line on MeInvoice InvoiceDetail.
     */
    public function getShippingLineName(?int $storeId = null): string
    {
        $value = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_SHIPPING_LINE_NAME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        return $value !== '' ? $value : self::DEFAULT_SHIPPING_LINE_NAME;
    }

    /**
     * Read config value with legacy path fallback.
     *
     * @param string $path
     * @param string $legacyPath
     * @param int|null $storeId
     * @return string
     */
    private function getScopedValue(string $path, string $legacyPath, ?int $storeId): string
    {
        $value = (string) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        if ($value !== '') {
            return $value;
        }

        return (string) $this->scopeConfig->getValue($legacyPath, ScopeInterface::SCOPE_STORE, $storeId);
    }

}
