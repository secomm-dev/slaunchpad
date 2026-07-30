<?php
/**
 * Copyright (c) 2023. Secomm All rights reserved
 * See COPYING.txt for license details.
 */

namespace Secomm\ZaloPay\Test\Integration\Helper;

use Secomm\ZaloPay\Gateway\Helper\Rate as Data;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Gateway\Config\Config;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDbIsolation enabled
 */
class RateTest extends TestCase
{

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var Data
     */
    private $data;

    /**
     * @var Config
     */
    private $config;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->data = $this->objectManager->create(Data::class);
        $this->config = $this->objectManager->create(Config::class);
        $this->config->setMethodCode('zalopay');
        $this->config->setPathPattern('payment/%s/%s');
    }

    /**
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testGetVndAmount(): void
    {
        $order = $this->getOrder(193);
        //USD to VND
        $this->assertEquals(2480000, $this->data->getVndAmount($order, 100));
    }

    /**
     * @param int $orderId
     * @return Order
     */
    private function getOrder(int $orderId): Order
    {
        return $this->objectManager->create(Order::class)->load($orderId);
    }
}
