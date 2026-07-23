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

namespace Mageplaza\Osc\Test\Unit\Model;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Cms\Block\Block;
use Magento\Customer\Model\AccountManagement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\UrlInterface;
use Magento\GiftMessage\Model\CompositeConfigProvider;
use Magento\Paypal\Model\Config as PaypalConfig;
use Magento\Quote\Api\CartItemRepositoryInterface as QuoteItemRepository;
use Magento\Quote\Api\Data\PaymentMethodInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use Magento\Quote\Model\Cart\ShippingMethod;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Osc\Helper\Address as OscHelper;
use Mageplaza\Osc\Model\DefaultConfigProvider;
use PHPUnit\Framework\MockObject\Matcher\InvokedCount as InvokedCountMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

class DefaultConfigProviderTest extends TestCase
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSessionMock;

    /**
     * @var PaymentMethodManagementInterface
     */
    private $paymentMethodManagementMock;

    /**
     * @var ShippingMethodManagementInterface
     */
    private $shippingMethodManagementMock;

    /**
     * @var CompositeConfigProvider
     */
    private $giftMessageConfigProviderMock;

    /**
     * @var ModuleManager
     */
    private $moduleManagerMock;

    /**
     * @var OscHelper
     */
    private $oscHelperMock;

    /**
     * @var QuoteItemRepository
     */
    private $quoteItemRepositoryMock;

    /**
     * @var StockRegistryInterface
     */
    private $stockRegistryMock;

    /**
     * @var Block
     */
    private $cmsBlockMock;

    /**
     * @var StoreManagerInterface
     */
    private $storeManagerMock;

    /**
     * @var PaypalConfig
     */
    private $paypalConfigMock;

    /**
     * @var UrlInterface
     */
    private $urlMock;

    /**
     * @var DefaultConfigProvider
     */
    private $model;

    /**
     * @var Quote|MockObject
     */
    private $quoteMock;

    /**
     * @var Address|MockObject
     */
    private $shippingAddressMock;

    protected function setUp(): void
    {
        $this->checkoutSessionMock = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentMethodManagementMock = $this->getMockForAbstractClass(PaymentMethodManagementInterface::class);
        $this->shippingMethodManagementMock = $this->getMockForAbstractClass(ShippingMethodManagementInterface::class);
        $this->giftMessageConfigProviderMock = $this->getMockBuilder(CompositeConfigProvider::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteItemRepositoryMock = $this->getMockBuilder(QuoteItemRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->stockRegistryMock = $this->getMockForAbstractClass(StockRegistryInterface::class);
        $this->moduleManagerMock = $this->getMockBuilder(ModuleManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->oscHelperMock = $this->getMockBuilder(OscHelper::class)->disableOriginalConstructor()->getMock();
        $cmsBlockMethods = get_class_methods(Block::class);
        $cmsBlockMethods[] = 'setBlockId';
        $this->cmsBlockMock = $this->getMockBuilder(Block::class)
            ->setMethods($cmsBlockMethods)
            ->disableOriginalConstructor()->getMock();
        $this->storeManagerMock = $this->getMockForAbstractClass(StoreManagerInterface::class);
        $this->paypalConfigMock = $this->getMockBuilder(PaypalConfig::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->urlMock = $this->getMockForAbstractClass(UrlInterface::class);
        $this->quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteId = 1;
        $this->quoteMock->method('getId')->willReturn($quoteId);
        $this->checkoutSessionMock->method('getQuote')->willReturn($this->quoteMock);

        $this->model = new DefaultConfigProvider(
            $this->checkoutSessionMock,
            $this->paymentMethodManagementMock,
            $this->shippingMethodManagementMock,
            $this->giftMessageConfigProviderMock,
            $this->quoteItemRepositoryMock,
            $this->stockRegistryMock,
            $this->moduleManagerMock,
            $this->oscHelperMock,
            $this->cmsBlockMock,
            $this->storeManagerMock,
            $this->paypalConfigMock,
            $this->urlMock
        );
    }

    /**
     * @return array
     */
    public function providerTestGetConfig()
    {
        return [
            [
                'flatrate'
            ]
        ];
    }

    /**
     * @param string $selectedShippingRate
     *
     * @throws       LocalizedException
     * @throws       NoSuchEntityException
     * @throws       StateException
     * @throws       ReflectionException
     * @dataProvider providerTestGetConfig
     */
    public function testGetConfig($selectedShippingRate)
    {
        $checkVersion = 1;
        $defaultPaymentMethod = 'checkmo';

        $this->oscHelperMock->expects($this->once())->method('isOscPage')->willReturn(true);
        $resultShipping = $this->mockGetShippingMethodsWithCountry();
        $this->oscHelperMock->expects($this->atLeastOnce())->method('checkVersion')->willReturn($checkVersion);
        $this->oscHelperMock->expects($this->once())
            ->method('getDefaultPaymentMethod')
            ->willReturn($defaultPaymentMethod);
        $paymentMethod = $this->mockGetPaymentMethods();

        $this->shippingAddressMock->expects($this->once())
            ->method('getShippingMethod')
            ->willReturn($selectedShippingRate);
        if (!$selectedShippingRate) {
            $selectedShippingRate = 'flatrate';
            $this->oscHelperMock->expects($this->once())
                ->method('getDefaultShippingMethod')
                ->willReturn($selectedShippingRate);
        }

        $result = [
            'shippingMethods' => $resultShipping,
            'selectedShippingRate' => $selectedShippingRate,
            'paymentMethods' => $paymentMethod,
            'selectedPaymentMethod' => $defaultPaymentMethod,
            'oscConfig' => $this->getOscConfigMock(),
            'checkVersion' => $checkVersion
        ];

        $this->assertEquals($result, $this->model->getConfig());
    }

    /**
     * @return array
     */
    public function getOscConfigMock()
    {
        $addressFields = [
            0 => 'firstname',
            1 => 'lastname',
            2 => 'street',
            3 => 'country_id',
            4 => 'city',
            5 => 'postcode',
            6 => 'region_id',
            7 => 'company',
            8 => 'telephone',
            9 => 'region_id_input'
        ];
        $dataPasswordMinLength = 8;
        $dataPasswordMinCharacterSets = 3;
        $giftMessageConfig = [
            'storeCode' => 'default',
            'isCustomerLoggedIn' => false,
            'formKey' => 'AoGkLHzGBoQdUT5g',
            'baseUrl' => 'https://test.com/',
        ];

        $this->oscHelperMock->expects($this->once())->method('getAddressFields')->willReturn($addressFields);
        $this->oscHelperMock->expects($this->once())->method('getAutoDetectedAddress')->willReturn(null);
        $this->oscHelperMock->expects($this->once())->method('getGoogleSpecificCountry')->willReturn(null);
        $this->oscHelperMock->expects($this->exactly(3))->method('getConfigValue')
            ->withConsecutive(
                [AccountManagement::XML_PATH_MINIMUM_PASSWORD_LENGTH],
                [AccountManagement::XML_PATH_REQUIRED_CHARACTER_CLASSES_NUMBER],
                ['sociallogin/general/popup_login']
            )->willReturnOnConsecutiveCalls($dataPasswordMinLength, $dataPasswordMinCharacterSets, 'popup_slide');
        $this->oscHelperMock->expects($this->once())->method('getAllowGuestCheckout')
            ->with($this->quoteMock)
            ->willReturn(1);
        $this->oscHelperMock->expects($this->once())->method('getShowBillingAddress')->willReturn(true);
        $this->oscHelperMock->expects($this->once())->method('isSubscribedByDefault')->willReturn(false);
        $this->shippingAddressMock->expects($this->once())->method('getUsedGiftWrap')->willReturn(false);
        $this->giftMessageConfigProviderMock->expects($this->once())
            ->method('getConfig')
            ->willReturn($giftMessageConfig);
        $this->oscHelperMock->expects($this->once())->method('isEnableGiftMessageItems')->willReturn(false);
        $this->moduleManagerMock->expects($this->atLeastOnce())->method('isOutputEnabled')
            ->with('Mageplaza_SocialLogin')
            ->willReturn(true);
        $this->oscHelperMock->expects($this->atLeastOnce())
            ->method('isDisabledSocialLoginOnCheckout')
            ->willReturn(false);
        $this->oscHelperMock->expects($this->once())
            ->method('isUsedMaterialDesign')
            ->willReturn(false);
        $this->oscHelperMock->expects($this->once())
            ->method('isEnableGeoIP')
            ->willReturn(false);
        $this->oscHelperMock->expects($this->once())
            ->method('getGeoIpData')
            ->willReturn([]);
        $this->oscHelperMock->expects($this->once())
            ->method('isEnableModulePostNL')
            ->willReturn(false);
        $this->oscHelperMock->expects($this->once())
            ->method('getShowTOC')
            ->willReturn(false);
        $this->oscHelperMock->expects($this->once())
            ->method('isShowItemListToggle')
            ->willReturn(false);
        $updateItemOptions = 'https://test.com/onestepcheckout/index/updateItemOptions/';
        $this->urlMock->expects($this->once())->method('getUrl')
            ->with('onestepcheckout/index/updateItemOptions', ['_secure' => true])
            ->willReturn($updateItemOptions);

        $this->paypalConfigMock->expects($this->once())->method('setMethod')->with(PaypalConfig::METHOD_EXPRESS);
        $this->oscHelperMock->expects($this->atLeastOnce())->method('isEnabledSealBlock')->willReturn(0);

        return [
            'addressFields' => $addressFields,
            'autocomplete' => [
                'type' => null,
                'google_default_country' => null,
            ],
            'register' => [
                'dataPasswordMinLength' => $dataPasswordMinLength,
                'dataPasswordMinCharacterSets' => $dataPasswordMinCharacterSets
            ],
            'allowGuestCheckout' => false,
            'showBillingAddress' => true,
            'newsletterDefault' => false,
            'isUsedGiftWrap' => false,
            'giftMessageOptions' => [
                'storeCode' => 'default',
                'isCustomerLoggedIn' => false,
                'formKey' => 'AoGkLHzGBoQdUT5g',
                'baseUrl' => 'https://test.com/',
                'isEnableOscGiftMessageItems' => false,
            ],
            'isDisplaySocialLogin' => true,
            'isPopupSlideSocialLogin' => true,
            'isUsedMaterialDesign' => false,
            'isAmazonAccountLoggedIn' => false,
            'geoIpOptions' => [
                'isEnableGeoIp' => false,
                'geoIpData' => []
            ],
            'compatible' => [
                'isEnableModulePostNL' => false,
            ],
            'show_toc' => false,
            'qtyIncrements' => [],
            'sealBlock' => '',
            'isShowItemListToggle' => false,
            'paymentCustomBtn' => [],
            'updateCartUrl' => $updateItemOptions
        ];
    }

    public function testGetSealBlockWithStaticBlock()
    {
        $this->oscHelperMock->expects($this->once())->method('isEnabledSealBlock')->willReturn(1);
        $blockId = 1;
        $this->oscHelperMock->expects($this->once())->method('getSealStaticBlock')->willReturn($blockId);
        $this->cmsBlockMock->expects($this->once())->method('setBlockId')->with($blockId)->willReturnSelf();
        $this->cmsBlockMock->expects($this->once())->method('toHtml')->willReturn('test');

        $this->assertEquals(
            'test',
            $this->model->getSealBlock()
        );
    }

    public function testGetSealBlockUseDefaultDesign()
    {
        $this->oscHelperMock->expects($this->exactly(2))->method('isEnabledSealBlock')->willReturn(2);
        $storeMock = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()->getMock();
        $this->storeManagerMock->expects($this->once())->method('getStore')->willReturn($storeMock);
        $storeMock->expects($this->once())->method('getBaseUrl')
            ->with(UrlInterface::URL_TYPE_MEDIA)
            ->willReturn('some_url/');
        $this->oscHelperMock->expects($this->once())->method('getSealImage')->willReturn('seal.jpg');
        $this->oscHelperMock->expects($this->once())->method('getSealDescription')->willReturn('description');

        $this->assertEquals(
            '<img alt="seal-img" src="some_url/mageplaza/osc/seal/seal.jpg"><p>description</p>',
            $this->model->getSealBlock()
        );
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    public function mockGetPaymentMethods()
    {
        $quoteId = 1;
        $this->quoteMock->expects($this->once())->method('getIsVirtual')->willReturn(false);
        $paymentMethodMock = $this->getMockForAbstractclass(PaymentMethodInterface::class);
        $this->paymentMethodManagementMock->expects($this->once())
            ->method('getList')
            ->with($quoteId)
            ->willReturn([$paymentMethodMock]);
        $paymentTitle = 'Check / Money order';
        $paymentCode = 'checkmo';
        $paymentMethodMock->expects($this->once())->method('getCode')->willReturn($paymentCode);
        $paymentMethodMock->expects($this->once())->method('getTitle')->willReturn($paymentTitle);

        return [
            [
                'code' => $paymentCode,
                'title' => $paymentTitle
            ]
        ];
    }

    public function testGetPaymentMethods()
    {
        $this->assertEquals(
            $this->mockGetPaymentMethods(),
            $this->invokeMethod('getPaymentMethods')
        );
    }

    public function testGetPaymentMethodsWithQuoteVirtual()
    {
        $this->quoteMock->expects($this->once())->method('getIsVirtual')->willReturn(true);
        $this->assertEquals(
            [],
            $this->invokeMethod('getPaymentMethods')
        );
    }

    /**
     * @return array
     */
    public function mockGetShippingMethodsWithCountry()
    {
        $quoteId = 1;
        $shippingAddressMethods = get_class_methods(Address::class);
        $shippingAddressMethods[] = 'getUsedGiftWrap';
        $this->shippingAddressMock = $this->getMockBuilder(Address::class)
            ->setMethods($shippingAddressMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $this->quoteMock->expects($this->atLeastOnce())
            ->method('getShippingAddress')->willReturn($this->shippingAddressMock);
        $this->shippingAddressMock->expects($this->once())->method('getCountryId')->willReturn('US');
        $shippingMethodMock = $this->getMockBuilder(ShippingMethod::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->shippingMethodManagementMock->expects($this->once())
            ->method('getList')
            ->with($quoteId)
            ->willReturn([$shippingMethodMock]);

        $methodToArray = [
            'carrier_code' => 'flatrate',
            'method_code' => 'flatrate',
            'carrier_title' => 'Flat Rate',
            'method_title' => 'Fixed',
            'amount' => 5.0,
            'base_amount' => '5.0000',
            'available' => true,
            'error_message' => false,
            'price_excl_tax' => 5.0,
            'price_incl_tax' => 5.0,
        ];
        $shippingMethodMock->expects($this->once())
            ->method('__toArray')
            ->willReturn($methodToArray);

        return [$methodToArray];
    }

    public function testGetShippingMethodsWithCountry()
    {
        $this->assertEquals(
            $this->mockGetShippingMethodsWithCountry(),
            $this->invokeMethod('getShippingMethods')
        );
    }

    public function testGetShippingMethods()
    {
        $shippingAddressMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteMock->expects($this->once())->method('getShippingAddress')->willReturn($shippingAddressMock);
        $this->assertEquals(
            [],
            $this->invokeMethod('getShippingMethods')
        );
    }

    /**
     * @return array
     */
    public function providerTestGetItemQtyIncrement()
    {
        return [
            [
                [],
                false,
                1,
                self::never()
            ],
            [
                [],
                true,
                0,
                self::once()
            ],
            [
                [
                    1 => 2
                ],
                true,
                2,
                self::exactly(2)
            ]
        ];
    }

    /**
     * @param array               $result
     * @param boolean             $enableQtyIncrements
     * @param int                 $qtyIncrements
     * @param InvokedCountMatcher $qtyIncrementsExpect
     *
     * @dataProvider providerTestGetItemQtyIncrement
     *
     * @throws ReflectionException
     */
    public function testGetItemQtyIncrement($result, $enableQtyIncrements, $qtyIncrements, $qtyIncrementsExpect)
    {
        $productId = 1;
        $websiteId = 1;
        $item = $this->getMockBuilder(Item::class)->disableOriginalConstructor()->getMock();
        $quoteItems = [$item];
        $this->quoteItemRepositoryMock->expects($this->once())->method('getList')->willReturn($quoteItems);
        $productMock = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
        $storeMock = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()->getMock();
        $item->expects($this->once())->method('getProduct')->willReturn($productMock);
        $item->expects($this->once())->method('getStore')->willReturn($storeMock);
        $storeMock->expects($this->once())->method('getWebsiteId')->willReturn($websiteId);
        $productMock->expects($this->once())->method('getId')->willReturn($websiteId);
        $stockItemMock = $this->getMockBuilder(\Magento\CatalogInventory\Model\Stock\Item::class)
            ->disableOriginalConstructor()
            ->getMock();
        $stockItemMock->expects($this->once())->method('getEnableQtyIncrements')->willReturn($enableQtyIncrements);
        $this->stockRegistryMock->expects($this->once())
            ->method('getStockItem')
            ->with($productId, $websiteId)
            ->willReturn($stockItemMock);
        $stockItemMock->expects($qtyIncrementsExpect)->method('getQtyIncrements')->willReturn($qtyIncrements);
        if ($enableQtyIncrements && $qtyIncrements) {
            $item->expects($this->once())->method('getId')->willReturn(1);
        }

        $this->assertEquals($result, $this->invokeMethod('getItemQtyIncrement'));
    }

    public function testGetItemQtyIncrementWithException()
    {
        $quoteId = 1;
        $this->quoteMock->expects($this->once())->method('getId')->willReturn($quoteId);
        $exception = new Exception();
        $this->quoteItemRepositoryMock->expects($this->once())->method('getList')->willThrowException($exception);

        $this->assertEquals([], $this->invokeMethod('getItemQtyIncrement'));
    }

    /**
     * @param string $methodName
     * @param array  $parameters
     *
     * @return mixed
     * @throws ReflectionException
     */
    public function invokeMethod($methodName, $parameters = [])
    {
        $reflection = new ReflectionClass(DefaultConfigProvider::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->model, $parameters);
    }

    /**
     * @return array
     */
    public function providerTestGetPaymentCustomBtn()
    {
        return [
            [
                ['paypal_express'],
                false,
                self::once()
            ],
            [
                [],
                true,
                self::never()
            ]
        ];
    }

    /**
     * @param array               $result
     * @param boolean             $resultCheckVersion
     * @param InvokedCountMatcher $expects
     *
     * @dataProvider providerTestGetPaymentCustomBtn
     */
    public function testGetPaymentCustomBtn($result, $resultCheckVersion, $expects)
    {
        $this->paypalConfigMock->expects($this->once())->method('setMethod')->with(PaypalConfig::METHOD_EXPRESS);
        $this->oscHelperMock->expects($this->once())->method('checkVersion')->willReturn($resultCheckVersion);
        $this->paypalConfigMock->expects($expects)->method('getValue')->with('in_context')->willReturn(true);

        $this->assertEquals($result, $this->model->getPaymentCustomBtn());
    }
}
