<?php
/**
 * Copyright (c) 2023. Secomm All rights reserved
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Integration\Helper;

use Secomm\ZaloPay\Gateway\Helper\TransactionReader as Data;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Gateway\Config\Config;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDbIsolation enabled
 */
class TransactionReaderTest extends TestCase
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
     * TASK-EDS9T5: the reader's only job left is extracting the pay url —
     * the order-first readOrderId/isIpn helpers were removed with the
     * order-first flow.
     *
     * @return void
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
}
