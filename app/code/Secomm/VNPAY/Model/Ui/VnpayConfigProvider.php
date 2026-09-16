<?php

declare(strict_types=1);

namespace Secomm\VNPAY\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\View\Asset\Repository;

/**
 * Exposes the VNPAY payment logo URL to the checkout config
 * (window.checkoutConfig.payment.vnpay.logo).
 */
class VnpayConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly Repository $assetRepository
    ) {
    }

    public function getConfig(): array
    {
        return [
            'payment' => [
                'vnpay' => [
                    'logo' => $this->assetRepository->getUrl('Secomm_VNPAY::images/logo-vnpay.png'),
                ],
            ],
        ];
    }
}
