<?php

declare(strict_types=1);

namespace Secomm\VNPAY\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Exposes the VNPAY payment logo URL to the checkout config
 * (window.checkoutConfig.payment.vnpay.logo).
 */
class VnpayConfigProvider implements ConfigProviderInterface
{
    /**
     * Media subdirectory configured as upload_dir of the logo field
     * (checkout logo upload, mirrors the Secomm_ZaloPay implementation).
     * The stored config value is "<scope>[/<scopeId>]/<file>" relative to
     * this directory, e.g. "default/logo.png" or "websites/1/logo.png".
     */
    private const MEDIA_LOGO_DIR = 'vnpay/';

    /**
     * Config path of the uploaded checkout logo (payment/vnpay/logo).
     */
    private const XML_PATH_LOGO = 'payment/vnpay/logo';

    public function __construct(
        private readonly Repository            $assetRepository,
        private readonly ScopeConfigInterface  $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getConfig(): array
    {
        return [
            'payment' => [
                'vnpay' => [
                    'logo' => $this->getLogoSrc(),
                ],
            ],
        ];
    }

    /**
     * Checkout logo URL. An uploaded logo (media storage - survives static
     * content deploy, per-website supported) takes precedence; when no logo
     * is configured the bundled module asset is used (unchanged fallback
     * behavior). The `logo` config key contract is unchanged: the renderer
     * keeps reading window.checkoutConfig.payment.vnpay.logo.
     *
     * @return string
     */
    private function getLogoSrc(): string
    {
        $logo = (string)$this->scopeConfig->getValue(
            self::XML_PATH_LOGO,
            ScopeInterface::SCOPE_WEBSITE,
            (int)$this->storeManager->getStore()->getWebsiteId()
        );

        if ($logo === '') {
            $logo = (string)$this->scopeConfig->getValue(self::XML_PATH_LOGO);
        }

        if ($logo !== '') {
            return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA)
                . self::MEDIA_LOGO_DIR . $logo;
        }

        return $this->assetRepository->getUrl('Secomm_VNPAY::images/logo-vnpay.png');
    }
}