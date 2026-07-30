<?php
/**
 * Copyright (c) 2023. Secomm All rights reserved
 * See COPYING.txt for license details.
 */

namespace Secomm\ZaloPay\Test\Integration\Helper;

use Secomm\ZaloPay\Gateway\Helper\Authorization as Data;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Magento\Payment\Gateway\Config\Config;

/**
 * @magentoDbIsolation enabled
 */
class AuthorizationTest extends TestCase
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
     */
    public function testGetMac(): void
    {
        $result = $this->data->getMac(['test,test1,test3']);
        $this->assertIsString($result);
        $this->assertEquals($result, $this->data->getParameter());
    }

    /**
     * @return void
     */
    public function testGetMacKey2(): void
    {
        $this->assertIsString($this->data->getMacKey2('!@#$%%^&*((abc123'));
    }

    /**
     * @return void
     */
    public function testGetHeaders(): void
    {
        $this->assertEquals([
            'Content-Type: application/x-www-form-urlencoded'
        ], $this->data->getHeaders());
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
