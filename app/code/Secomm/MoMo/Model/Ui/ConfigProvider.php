<?php
/**
 * Checkout configuration provider for MoMo.
 *
 * Exposes MoMo method title + redirect start URL to the checkout JS.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;
use Secomm\MoMo\Model\Config;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'momo_payment';

    /**
     * Constructor
     *
     * @param Config $config
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly Config $config,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        return [
            'payment' => [
                self::CODE => [
                    'title' => (string)$this->config->getValue('title'),
                    'redirectUrl' => $this->urlBuilder->getUrl('momo/payment/redirect'),
                ],
            ],
        ];
    }
}
