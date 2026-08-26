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
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Helper\Data as PaymentHelper;

class ZaloPayConfigProvider implements ConfigProviderInterface
{
    /**
     * ZaloPayConfigProvider constructor.
     *
     * @param Repository    $assetRepository
     * @param PaymentHelper $paymentHelper
     * @param UrlInterface  $urlBuilder
     * @param ConfigInterface $config
     */
    public function __construct(
        private readonly Repository    $assetRepository,
        protected readonly PaymentHelper $paymentHelper,
        protected readonly UrlInterface  $urlBuilder,
        private readonly ConfigInterface $config
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
                    'redirectUrl' => $this->urlBuilder->getUrl('zalopay/payment/start'),
                    'logoSrc' => $this->assetRepository->getUrl('Secomm_ZaloPay::images/logo.png'),
                    // Payment-first: set payment method + redirect (PayPal Express
                    // pattern); the renderer must NOT placeOrder() before redirect.
                    'paymentFirst' => (bool)$this->config->getValue('payment_first')
                ]
            ]
        ];
    }
}
