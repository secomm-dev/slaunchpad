<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Test\Integration\Model\Carrier;

use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Secomm\Ahamove\Model\Carrier\Ahamove;
use Secomm\Ahamove\Model\Data\AhamoveAddressFactory;

class AhamoveTest extends \PHPUnit\Framework\TestCase
{
    const URL_SANDBOX = 'ahamove/general/url_sandbox';

    const RETURN_ARRAY = true;

    /**
     * @var Ahamove
     */
    private $ahamove;

    /**
     * @var AhamoveAddressFactory|mixed
     */
    protected $ahamoveAddressFactory;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->ahamove = $objectManager->create(Ahamove::class);
        $this->ahamoveAddressFactory = $objectManager->create(AhamoveAddressFactory::class);
        $this->scopeConfig = $objectManager->create(ScopeConfigInterface::class);
        $objectManager->get(\Magento\Framework\App\CacheInterface::class)->clean();
    }

    /**
     * Time: 00:05.830, Memory: 92.50 MB
     * This function is used to test the calculateShippingFee function
     *
     * @covers \Secomm\Ahamove\Model\Carrier\Ahamove::collectRates
     *
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     */
    public function testCalculateShippingFee(): void
    {
        $mutableScopeConfig = Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class);
        $mutableScopeConfig->setValue(self::URL_SANDBOX, 'https://apistg.ahamove.com');
        $this->scopeConfig->getValue(self::URL_SANDBOX);
        $ahamoveAddressFactory = $this->ahamoveAddressFactory->create();
        $ahamoveAddressFactory->setCityFrom('Ho Chi Minh')
            ->setRegionCodeFrom('Ho Chi Minh')
            ->setStreetFrom('Ho Chi Minh')
            ->setPostCodeFrom('Ho Chi Minh')
            ->setNameFrom('Ho Chi Minh')
            ->setPhoneFrom('0393839306');

        $ahamoveAddressFactory->setCityTo('Ho Chi Minh')
            ->setRegionCodeTo('Ho Chi Minh')
            ->setStreetTo('Ho Chi Minh')
            ->setPostCodeTo('Ho Chi Minh')
            ->setNameTo('Ho Chi Minh')
            ->setRemark('Ho Chi Minh')
            ->setPhoneTo('Ho Chi Minh');
        $result = $this->ahamove->calculateShippingFee($ahamoveAddressFactory);
        $this->assertEquals(0.87, $result);
    }
}
