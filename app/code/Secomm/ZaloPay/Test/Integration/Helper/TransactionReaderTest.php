<?php
/**
 * Copyright (c) 2023. Secomm All rights reserved
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Integration\Helper;

use \Secomm\ZaloPay\Gateway\Helper\TransactionReader as Data;
use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Gateway\Config\Config;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use InvalidArgumentException;

/**
 * @magentoDbIsolation enabled
 */
class TransactionReaderTest extends TestCase
{

    const IS_IPN = 'is_ipn';

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
    public function testReadPayUrl(): void
    {
        $dataTest = [
            AbstractResponseValidator::PAY_URL => AbstractResponseValidator::PAY_URL
        ];
        $dataTestError = [
            'error' => AbstractResponseValidator::PAY_URL
        ];
        $this->assertEquals(AbstractResponseValidator::PAY_URL, $this->data->readPayUrl($dataTest));
        $this->expectException(\InvalidArgumentException::class);

        $this->expectExceptionMessage('Pay Url should be provided');
        $this->assertEquals([], $this->data->readPayUrl($dataTestError));
    }

    /**
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testReadOrderId(): void
    {
        $dataTest = [
            AbstractResponseValidator::TRANS_DATA => [
                AbstractDataBuilder::APP_TRANS_ID => 'orderid_1'
            ]
        ];
        $dataTestError = [
            'error' => AbstractResponseValidator::PAY_URL
        ];
        $this->assertEquals('1', $this->data->readOrderId($dataTest));
        $this->expectException(\InvalidArgumentException::class);

        $this->expectExceptionMessage('Order Id doesn\'t exist');
        $this->assertEquals([], $this->data->readOrderId($dataTestError));
    }

    /**
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testIsIpn(): void
    {
        $dataTest = [
            self::IS_IPN => 'test'
        ];
        $dataTestError = [
            'error' => 'test'
        ];
        $this->assertTrue($this->data->isIpn($dataTest));
        $this->assertFalse($this->data->isIpn($dataTestError));
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
