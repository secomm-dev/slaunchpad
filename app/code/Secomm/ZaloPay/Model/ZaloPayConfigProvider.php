<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ZaloPayConfigProvider implements ConfigProviderInterface
{
    /**
     * Media subdirectory configured as upload_dir of the logo field
     * (TASK-MCHN2T checkout logo upload).
     */
    private const MEDIA_LOGO_DIR = 'zalopay/';

    /**
     * Config path of the uploaded checkout logo (payment/zalopay/logo).
     */
    private const XML_PATH_LOGO = 'payment/zalopay/logo';

    /**
     * ZaloPayConfigProvider constructor.
     *
     * @param Repository             $assetRepository
     * @param ScopeConfigInterface   $scopeConfig
     * @param StoreManagerInterface  $storeManager
     */
    public function __construct(
        private readonly Repository            $assetRepository,
        private readonly ScopeConfigInterface  $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface          $urlBuilder
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        return [
            'payment' => [
                'zalopay' => [
                    // Payment-first: the renderer only saves the payment method
                    // and redirects here (PayPal Express pattern); the Magento
                    // order is created exclusively after verified payment
                    // (IpnProcessor/ReturnProcessor -> OrderFinalizer).
                    'redirectUrl' => $this->urlBuilder->getUrl('zalopay/payment/start'),
                    'logoSrc' => $this->getLogoSrc()
                ]
            ]
        ];
    }

    /**
     * Checkout logo URL (single `logoSrc` contract for the renderer).
     *
     * An uploaded logo (media storage - survives static content deploy,
     * AC8/AC9) takes precedence; when no logo is configured the bundled
     * module asset is used (unchanged fallback behavior).
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

        return $this->assetRepository->getUrl('Secomm_ZaloPay::images/logo.png');
    }
}
