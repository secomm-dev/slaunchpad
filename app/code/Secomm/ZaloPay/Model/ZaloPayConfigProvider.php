<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */
namespace Secomm\ZaloPay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;

class ZaloPayConfigProvider implements ConfigProviderInterface
{
    /**
     * @var PaymentHelper
     */
    protected PaymentHelper $paymentHelper;

    /**
     * @var UrlInterface
     */
    protected UrlInterface $urlBuilder;

    /**
     * @var Repository
     */
    private Repository $assetRepository;

    /**
     * ZaloPayConfigProvider constructor.
     *
     * @param Repository    $assetRepository
     * @param PaymentHelper $paymentHelper
     * @param UrlInterface  $urlBuilder
     */
    public function __construct(
        Repository $assetRepository,
        PaymentHelper $paymentHelper,
        UrlInterface $urlBuilder
    ) {
        $this->paymentHelper   = $paymentHelper;
        $this->urlBuilder      = $urlBuilder;
        $this->assetRepository = $assetRepository;
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
                    'logoSrc' => $this->assetRepository->getUrl('Secomm_ZaloPay::images/logo.png')
                ]
            ]
        ];
    }
}
