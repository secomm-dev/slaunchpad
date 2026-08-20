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
use Secomm\Ahamove\Model\Carrier\ShippingMethod\Standard;
use Secomm\Ahamove\Model\Data\AhamoveAddressFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AhamoveTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Standard
     */
    private $carrier;

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
        $this->carrier = $objectManager->create(Standard::class);
        $this->ahamoveAddressFactory = $objectManager->create(AhamoveAddressFactory::class);
        $this->scopeConfig = $objectManager->create(ScopeConfigInterface::class);
        $objectManager->get(\Magento\Framework\App\CacheInterface::class)->clean();
    }

    /**
     * Test that calculateShippingFee returns a non-negative float
     *
     * @covers \Secomm\Ahamove\Model\Carrier\ShippingMethod\Standard::calculateShippingFee
     *
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     */
    public function testCalculateShippingFeeReturnsNonNegative(): void
    {
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

        $result = $this->carrier->calculateShippingFee($ahamoveAddressFactory);
        $this->assertIsFloat($result);
        $this->assertGreaterThanOrEqual(0.0, $result);
    }
}
