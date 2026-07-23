<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category  Mageplaza
 * @package   Mageplaza_Osc
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Osc\Test\Unit\Helper;

use Exception;
use Magento\Backend\App\ConfigInterface;
use Magento\Catalog\Model\Product;
use Magento\Downloadable\Model\Product\Type;
use Magento\Downloadable\Observer\IsAllowedGuestCheckoutObserver;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Osc\Helper\Data;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class DataTest extends TestCase
{
    /**
     * @var EncryptorInterface|MockObject
     */
    private $encryptorMock;

    /**
     * @var Json|MockObject
     */
    private $jsonMock;

    /**
     * @var ObjectManagerInterface|MockObject
     */
    private $objectManagerMock;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var RequestInterface|MockObject
     */
    private $requestMock;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $scopeConfigMock;

    protected function setUp(): void
    {
        /**
         * @var Context|MockObject $contextMock
         */
        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $this->requestMock = $this->getMockBuilder(RequestInterface::class)
            ->setMethods(['getRouteName'])
            ->getMockForAbstractClass();
        $this->scopeConfigMock = $this->getMockForAbstractClass(ScopeConfigInterface::class);
        $contextMock->method('getRequest')->willReturn($this->requestMock);
        $contextMock->method('getScopeConfig')->willReturn($this->scopeConfigMock);
        $this->objectManagerMock = $this->getMockForAbstractClass(ObjectManagerInterface::class);
        /**
         * @var StoreManagerInterface $storeManagerMock
         */
        $storeManagerMock = $this->getMockForAbstractClass(StoreManagerInterface::class);
        $this->encryptorMock = $this->getMockForAbstractClass(EncryptorInterface::class);
        $this->jsonMock = $this->getMockBuilder(Json::class)->disableOriginalConstructor()->getMock();

        $this->helper = new Data(
            $contextMock,
            $this->objectManagerMock,
            $storeManagerMock,
            $this->encryptorMock,
            $this->jsonMock
        );
    }

    public function testJsonEncodeData()
    {
        $value = [
            'my_attribute' => 'test'
        ];
        $result = '{"my_attribute":"test"}';

        $this->jsonMock->expects($this->once())->method('serialize')->with($value)->willReturn($result);

        $this->helper->jsonEncodeData($value);
    }

    public function testJsonEncodeDataWithException()
    {
        $value = [
            'my_attribute' => 'test'
        ];

        $exception = new Exception();
        $this->jsonMock->expects($this->once())
            ->method('serialize')
            ->with($value)
            ->willThrowException($exception);

        $this->assertEquals('{}', $this->helper->jsonEncodeData($value));
    }

    public function testJsonDecodeData()
    {
        $result = [
            'my_attribute' => 'test'
        ];
        $value = '{"my_attribute":"test"}';

        $this->jsonMock->expects($this->once())->method('unserialize')->with($value)->willReturn($result);

        $this->helper->jsonDecodeData($value);
    }

    public function testJsonDecodeWithException()
    {
        $value = '{"my_attribute":"test"}';
        $exception = new Exception();
        $this->jsonMock->expects($this->once())
            ->method('unserialize')
            ->with($value)
            ->willThrowException($exception);

        $this->assertEquals([], $this->helper->jsonDecodeData($value));
    }

    /**
     * @return array
     */
    public function providerTestIsOscPage()
    {
        return [
            [Area::AREA_FRONTEND],
            [Area::AREA_ADMINHTML]
        ];
    }

    /**
     * @param string $area
     *
     * @dataProvider providerTestIsOscPage
     *
     * @throws ReflectionException
     */
    public function testIsOscPage($area)
    {
        $stateMock = $this->getMockBuilder(State::class)
            ->disableOriginalConstructor()->getMock();
        $this->objectManagerMock->expects($this->at(0))
            ->method('get')
            ->with('Magento\Framework\App\State')
            ->willReturn($stateMock);
        $stateMock->expects($this->once())
            ->method('getAreaCode')
            ->willReturn($area);
        $field = 'osc/general/enabled';
        if ($area === Area::AREA_FRONTEND) {
            $this->scopeConfigMock->expects($this->once())
                ->method('getValue')
                ->with($field, ScopeInterface::SCOPE_STORE, null)
                ->willReturn(true);
        } else {
            $backendConfigMock = $this->getMockForAbstractClass(ConfigInterface::class);
            $this->objectManagerMock->expects($this->at(1))
                ->method('get')
                ->with('Magento\Backend\App\ConfigInterface')
                ->willReturn($backendConfigMock);
            $backendConfigMock->expects($this->once())
                ->method('getValue')
                ->with('osc/general/enabled')
                ->willReturn(true);
        }

        $this->requestMock->expects($this->once())->method('getRouteName')->willReturn('onestepcheckout');

        $this->assertTrue($this->helper->isOscPage());
    }

    public function testGetDefaultCountryId()
    {
        $store = 1;
        $directHelperDataMock = $this->getMockBuilder(\Magento\Directory\Helper\Data::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->objectManagerMock->expects($this->once())
            ->method('get')
            ->with(\Magento\Directory\Helper\Data::class)
            ->willReturn($directHelperDataMock);
        $directHelperDataMock->expects($this->once())->method('getDefaultCountry')->with($store);

        $this->helper->getDefaultCountryId($store);
    }

    /**
     * @return array
     */
    public function providerTestGetAllowGuestCheckout()
    {
        return [
            [true, false, ''],
            [false, true, Type::TYPE_DOWNLOADABLE],
            [true, true, '']
        ];
    }

    /**
     * @param boolean $result
     * @param boolean $flag
     * @param string  $typeId
     *
     * @dataProvider providerTestGetAllowGuestCheckout
     */
    public function testGetAllowGuestCheckout($result, $flag, $typeId)
    {
        $store = 1;

        /**
         * @var Quote $quoteMock
         */
        $quoteMock = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->getMock();
        $field = 'osc/general/allow_guest_checkout';
        $this->mockConfig(true, $field, $store);
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with(
                'catalog/downloadable/disable_guest_checkout',
                ScopeInterface::SCOPE_STORE,
                $store
            )
            ->willReturn($flag);
        if ($flag) {
            $itemMock = $this->getMockBuilder(Item::class)->disableOriginalConstructor()->getMock();
            $quoteMock->expects($this->once())->method('getAllItems')->willReturn([$itemMock]);
            $productMock = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
            $itemMock->expects($this->once())->method('getProduct')->willReturn($productMock);
            $productMock->expects($this->once())->method('getTypeId')->willReturn($typeId);
        }

        $this->assertEquals($result, $this->helper->getAllowGuestCheckout($quoteMock, $store));
    }

    /**
     * @param int|string|boolean $result
     * @param string             $field
     * @param int                $store
     * @param string             $area
     * @param int                $at
     */
    public function mockConfig($result, $field, $store, $area = Area::AREA_FRONTEND, $at = 0)
    {
        $stateMock = $this->getMockBuilder(State::class)
            ->disableOriginalConstructor()->getMock();
        $this->objectManagerMock->expects($this->at($at))
            ->method('get')
            ->with('Magento\Framework\App\State')
            ->willReturn($stateMock);
        $stateMock->expects($this->once())
            ->method('getAreaCode')
            ->willReturn($area);
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with($field, ScopeInterface::SCOPE_STORE, $store)
            ->willReturn($result);
    }

    public function testGetGoogleApiKey()
    {
        $store = 1;
        $this->mockConfig('test', 'osc/general/google_api_key', $store);
        $this->encryptorMock->expects($this->once())
            ->method('decrypt')
            ->with('test');

        $this->helper->getGoogleApiKey($store);
    }

    public function testIsGoogleHttps()
    {
        $this->mockConfig('google', 'osc/general/auto_detect_address', null);

        $this->requestMock->expects($this->once())->method('isSecure')->willReturn(true);

        $this->assertTrue($this->helper->isGoogleHttps());
    }

    public function testFormatGiftWrapAmount()
    {
        $checkoutMock = $this->getMockBuilder(\Magento\Checkout\Helper\Data::class)
            ->disableOriginalConstructor()->getMock();
        $this->objectManagerMock->expects($this->at(0))
            ->method('get')
            ->with(\Magento\Checkout\Helper\Data::class)
            ->willReturn($checkoutMock);
        $this->mockConfig(
            1,
            'osc/display_configuration/gift_wrap_amount',
            null,
            Area::AREA_FRONTEND,
            1
        );
        $checkoutMock->expects($this->once())
            ->method('formatPrice')
            ->with(1);

        $this->helper->formatGiftWrapAmount();
    }

    /**
     * @throws ReflectionException
     */
    public function testVersionCompare()
    {
        $productMetaMock = $this->getMockForAbstractClass(ProductMetadataInterface::class);
        $this->objectManagerMock->expects($this->once())
            ->method('get')
            ->with(ProductMetadataInterface::class)
            ->willReturn($productMetaMock);
        $productMetaMock->expects($this->once())->method('getVersion')->willReturn('2.3.5');

        $this->assertTrue($this->helper->versionCompare('2.3.4'));
    }
}
