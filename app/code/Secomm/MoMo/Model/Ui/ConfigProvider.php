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
use Secomm\MoMo\Gateway\Config\Config;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'momo_payment';

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * Constructor
     *
     * @param Config $config
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        Config $config,
        UrlInterface $urlBuilder
    ) {
        $this->config = $config;
        $this->urlBuilder = $urlBuilder;
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
