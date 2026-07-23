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
use Magento\Catalog\Model\Product\Url;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\GiftMessage\Model\GiftMessageManager;
use Magento\GiftMessage\Model\Message;
use Magento\OfflinePayments\Model\Checkmo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use Magento\Quote\Model\Cart\ShippingMethod;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Cart\Totals;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\TotalsCollector;
use Mageplaza\Osc\Helper\Item as OscHelper;
use Mageplaza\Osc\Model\CheckoutManagement;
use Mageplaza\Osc\Model\OscDetails;
use Mageplaza\Osc\Model\OscDetailsFactory;
use PHPUnit\Framework\MockObject\Matcher\InvokedCount as InvokedCountMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionException;

class CheckoutManagementTest extends TestCase
{
    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $cartRepositoryMock;

    /**
     * @var OscDetailsFactory|MockObject
     */
    private $oscDetailsFactoryMock;

    /**
     * @var ShippingMethodManagementInterface|MockObject
     */
    private $shippingMethodManagementMock;

    /**
     * @var PaymentMethodManagementInterface|MockObject
     */
    private $paymentMethodManagementMock;

    /**
     * @var CartTotalRepositoryInterface|MockObject
     */
    private $cartTotalsRepositoryMock;

    /**
     * @var UrlInterface|MockObject
     */
    private $urlBuilderMock;

    /**
     * @var Session|MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var ShippingInformationManagementInterface|MockObject
     */
    private $shippingInformationManagementMock;

    /**
     * @var OscHelper|MockObject
     */
    private $oscHelperMock;

    /**
     * @var Message|MockObject
     */
    private $giftMessageMock;

    /**
     * @var GiftMessageManager|MockObject
     */
    private $giftMessageManagerMock;

    /**
     * @var CustomerSession|MockObject
     */
    private $customerSessionMock;

    /**
     * @var TotalsCollector|MockObject
     */
    private $totalsCollectorMock;

    /**
     * @var AddressInterface|MockObject
     */
    private $addressInterfaceMock;

    /**
     * @var ShippingMethodConverter|MockObject
     */
    private $shippingMethodConverterMock;

    /**
     * @var LoggerInterface|MockObject
     */
    private $loggerMock;

    /**
     * @var CheckoutManagement
     */
    private $checkoutManagement;

    protected function setUp(): void
    {
        $this->cartRepositoryMock = $this->getMockForAbstractClass(CartRepositoryInterface::class);
        $this->oscDetailsFactoryMock = $this->getMockBuilder(OscDetailsFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->shippingMethodManagementMock = $this->getMockForAbstractClass(ShippingMethodManagementInterface::class);
        $this->paymentMethodManagementMock = $this->getMockForAbstractClass(PaymentMethodManagementInterface::class);
        $this->cartTotalsRepositoryMock = $this->getMockForAbstractClass(CartTotalRepositoryInterface::class);
        $this->urlBuilderMock = $this->getMockForAbstractClass(UrlInterface::class);
        $this->checkoutSessionMock = $this->getMockBuilder(Session::class)
            ->setMethods(['setOscData'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->shippingInformationManagementMock = $this->getMockForAbstractClass(
            ShippingInformationManagementInterface::class
        );
        $this->oscHelperMock = $this->getMockBuilder(OscHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->giftMessageMock = $this->getMockBuilder(Message::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->giftMessageManagerMock = $this->getMockBuilder(GiftMessageManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->customerSessionMock = $this->getMockBuilder(CustomerSession::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->totalsCollectorMock = $this->getMockBuilder(TotalsCollector::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->addressInterfaceMock = $this->getMockBuilder(AddressInterface::class)
            ->setMethods(['getData'])
            ->getMockForAbstractClass();
        $this->shippingMethodConverterMock = $this->getMockBuilder(ShippingMethodConverter::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->loggerMock = $this->getMockForAbstractClass(LoggerInterface::class);

        $this->checkoutManagement = new CheckoutManagement(
            $this->cartRepositoryMock,
            $this->oscDetailsFactoryMock,
            $this->shippingMethodManagementMock,
            $this->paymentMethodManagementMock,
            $this->cartTotalsRepositoryMock,
            $this->urlBuilderMock,
            $this->checkoutSessionMock,
            $this->shippingInformationManagementMock,
            $this->oscHelperMock,
            $this->giftMessageMock,
            $this->giftMessageManagerMock,
            $this->customerSessionMock,
            $this->totalsCollectorMock,
            $this->addressInterfaceMock,
            $this->shippingMethodConverterMock,
            $this->loggerMock
        );
    }

    /**
     * With the case getResponseData see testGetResponseData()
     *
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function testUpdateItemQty()
    {
        $cartId = 1;
        $itemId = 1;
        $itemQty = 1;
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()->getMock();
        $quoteItemMock = $this->getMockBuilder(Quote\Item::class)
            ->disableOriginalConstructor()->getMock();
        $this->cartRepositoryMock->expects($this->once())->method('getActive')->with($cartId)->willReturn($quoteMock);
        $quoteMock->expects($this->once())->method('getItemById')->with($itemId)->willReturn($quoteItemMock);
        $quoteItemMock->expects($this->once())->method('setQty')->with($itemQty)->willReturnSelf();
        $quoteItemMock->expects($this->once())->method('save');
        $this->cartRepositoryMock->expects($this->once())
            ->method('save')
            ->with($quoteMock)
            ->willReturnSelf();
        $oscDetailsMock = $this->getMockBuilder(OscDetails::class)
            ->disableOriginalConstructor()->getMock();
        $this->oscDetailsFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($oscDetailsMock);

        $this->checkoutManagement->updateItemQty($cartId, $itemId, $itemQty);
    }

    public function testUpdateItemQtyWithCouldNotSaveException()
    {
        $cartId = 1;
        $itemId = 1;
        $itemQty = 0;
        $this->mockCouldNotSaveException($cartId, $itemId);

        $this->checkoutManagement->updateItemQty($cartId, $itemId, $itemQty);
    }

    /**
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function testUpdateItemQtyWithRemoveItemById()
    {
        $cartId = 1;
        $itemId = 1;
        $itemQty = 0;
        $this->mockRemoveItemById($cartId, $itemId);

        $this->checkoutManagement->updateItemQty($cartId, $itemId, $itemQty);
    }

    /**
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function testUpdateItemQtyWithNoSuchEntityException()
    {
        $cartId = 1;
        $itemId = 1;
        $itemQty = 1;
        $this->mockNoSuchEntityException($cartId, $itemId);

        $this->checkoutManagement->updateItemQty($cartId, $itemId, $itemQty);
    }

    /**
     * @param int $cartId
     * @param int $itemId
     */
    public function mockRemoveItemById($cartId, $itemId)
    {
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()->getMock();
        $quoteItemMock = $this->getMockBuilder(Quote\Item::class)
            ->disableOriginalConstructor()->getMock();
        $this->cartRepositoryMock->expects($this->once())->method('getActive')->with($cartId)->willReturn($quoteMock);
        $quoteMock->expects($this->once())->method('getItemById')->with($itemId)->willReturn($quoteItemMock);
        $quoteMock->expects($this->once())->method('removeItem')->with($itemId);
        $this->cartRepositoryMock->expects($this->once())
            ->method('save')
            ->with($quoteMock)
            ->willReturnSelf();
        $oscDetailsMock = $this->getMockBuilder(OscDetails::class)
            ->disableOriginalConstructor()->getMock();
        $this->oscDetailsFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($oscDetailsMock);
    }

    /**
     * With the case getResponseData see testGetResponseData()
     *
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function testRemoveItemById()
    {
        $cartId = 1;
        $itemId = 1;

        $this->mockRemoveItemById($cartId, $itemId);
        $this->checkoutManagement->removeItemById($cartId, $itemId);
    }

    public function testRemoveItemByIdWithCouldNotSaveException()
    {
        $cartId = 1;
        $itemId = 1;
        $this->mockCouldNotSaveException($cartId, $itemId);
        $this->checkoutManagement->removeItemById($cartId, $itemId);
    }

    /**
     * @param int $cartId
     * @param int $itemId
     */
    public function mockCouldNotSaveException($cartId, $itemId)
    {
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()->getMock();
        $quoteItemMock = $this->getMockBuilder(Quote\Item::class)
            ->disableOriginalConstructor()->getMock();
        $this->cartRepositoryMock->expects($this->once())->method('getActive')->with($cartId)->willReturn($quoteMock);
        $quoteMock->expects($this->once())->method('getItemById')->with($itemId)->willReturn($quoteItemMock);
        $quoteMock->expects($this->once())->method('removeItem')->with($itemId);
        $exception = new Exception('test');
        $this->cartRepositoryMock->expects($this->once())
            ->method('save')
            ->with($quoteMock)
            ->willThrowException($exception);
        $this->loggerMock->expects($this->once())->method('critical')->with('test');
        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Could not remove item from quote');
    }

    public function testRemoveItemByIdWithNoSuchEntityException()
    {
        $cartId = 1;
        $itemId = 1;
        $this->mockNoSuchEntityException($cartId, $itemId);

        $this->checkoutManagement->removeItemById($cartId, $itemId);
    }

    public function mockNoSuchEntityException($cartId, $itemId)
    {
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()->getMock();
        $this->cartRepositoryMock->expects($this->once())->method('getActive')->with($cartId)->willReturn($quoteMock);
        $quoteMock->expects($this->once())->method('getItemById')->with($itemId)->willReturn(null);
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Cart 1 doesn\'t contain item  1');
    }

    /**
     * With the case getResponseData see testGetResponseData()
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testGetPaymentTotalInformation()
    {
        $cartId = 1;
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()->getMock();
        $this->cartRepositoryMock->expects($this->once())->method('getActive')->with($cartId)->willReturn($quoteMock);
        $oscDetailsMock = $this->getMockBuilder(OscDetails::class)
            ->disableOriginalConstructor()->getMock();
        $this->oscDetailsFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($oscDetailsMock);

        $this->checkoutManagement->getPaymentTotalInformation($cartId);
    }

    /**
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function testUpdateGiftWrapWithException()
    {
        $cartId = 1;
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()->getMock();
        $this->cartRepositoryMock->expects($this->once())->method('getActive')->with($cartId)->willReturn($quoteMock);
        $shippingAddressMock = $this->getMockBuilder(Address::class)
            ->setMethods(['setUsedGiftWrap'])
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($this->atLeastOnce())->method('getShippingAddress')->willReturn($shippingAddressMock);
        $exception = new Exception('test');
        $this->cartRepositoryMock->expects($this->once())
            ->method('save')
            ->with($quoteMock)
            ->willThrowException($exception);
        $this->loggerMock->expects($this->once())->method('critical')->with('test');
        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Could not add gift wrap for this quote');

        $this->checkoutManagement->updateGiftWrap($cartId, false);
    }

    /**
     * With the case getResponseData see testGetResponseData()
     *
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function testUpdateGiftWrap()
    {
        $cartId = 1;
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()->getMock();
        $this->cartRepositoryMock->expects($this->once())->method('getActive')->with($cartId)->willReturn($quoteMock);
        $shippingAddressMock = $this->getMockBuilder(Address::class)
            ->setMethods(['setUsedGiftWrap'])
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($this->atLeastOnce())->method('getShippingAddress')->willReturn($shippingAddressMock);
        $this->cartRepositoryMock->expects($this->once())
            ->method('save')
            ->with($quoteMock)
            ->willReturnSelf();
        $oscDetailsMock = $this->getMockBuilder(OscDetails::class)
            ->disableOriginalConstructor()->getMock();
        $this->oscDetailsFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($oscDetailsMock);

        $this->checkoutManagement->updateGiftWrap($cartId, false);
    }

    /**
     * @return array
     */
    public function providerTestGetResponseDataWithRedirectUrl()
    {
        return [
            [
                false,
                false,
                self::never(),
                self::never(),
            ],
            [
                true,
                true,
                self::once(),
                self::never(),
            ],
            [
                true,
                false,
                self::once(),
                self::once(),
            ]
        ];
    }

    /**
     * @return array
     */
    public function providertestGetResponseData()
    {
        return [
            [
                ''
            ],
            [
                'US'
            ]
        ];
    }

    /**
     * @param string $countryId
     *
     * @dataProvider providertestGetResponseData
     *
     * @throws NoSuchEntityException
     */
    public function testGetResponseData($countryId)
    {
        $quoteId = 1;
        /**
         * @var Quote $quoteMock
         */
        $quoteMethods = get_class_methods(Quote::class);
        $quoteMethods[] = 'getHasError';
        $quoteMethods[] = 'getQuoteCurrencyCode';
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()->getMock();
        $oscDetailsMock = $this->getMockBuilder(OscDetails::class)
            ->disableOriginalConstructor()->getMock();
        $this->oscDetailsFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($oscDetailsMock);
        $quoteMock->expects($this->once())->method('hasItems')->willReturn(true);
        $quoteMock->expects($this->once())->method('getHasError')->willReturn(false);
        $quoteMock->expects($this->once())->method('validateMinimumAmount')->willReturn(true);
        $shippingAddressMethods = get_class_methods(Address::class);
        $shippingAddressMethods[] = 'setCollectShippingRates';
        $shippingAddressMock = $this->getMockBuilder(Address::class)
            ->setMethods($shippingAddressMethods)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($this->atLeastOnce())->method('getShippingAddress')->willReturn($shippingAddressMock);
        $shippingAddressMock->expects($this->once())->method('getCountryId')->willReturn($countryId);
        if ($countryId) {
            $addressData = [];
            $this->addressInterfaceMock->expects($this->once())->method('getData')->willReturn($addressData);
            $shippingAddressMock->expects($this->once())
                ->method('setCollectShippingRates')
                ->with(true);
            $this->totalsCollectorMock->expects($this->once())
                ->method('collectAddressTotals')->with($quoteMock, $shippingAddressMock);
            $rate = $this->getMockBuilder(Address\Rate::class)
                ->disableOriginalConstructor()
                ->getMock();
            $shippingRates = [
                'flatrate' => [
                    $rate
                ]
            ];
            $shippingAddressMock->expects($this->once())
                ->method('getGroupedAllShippingRates')
                ->willReturn($shippingRates);
            $quoteCurrencyCode = 'USD';
            $quoteMock->expects($this->once())->method('getQuoteCurrencyCode')->willReturn($quoteCurrencyCode);
            $shippingMethodMock = $this->getMockBuilder(ShippingMethod::class)
                ->disableOriginalConstructor()
                ->getMock();
            $this->shippingMethodConverterMock->expects($this->once())
                ->method('modelToDataObject')
                ->with($rate, $quoteCurrencyCode)
                ->willReturn($shippingMethodMock);
            $oscDetailsMock->expects($this->once())->method('setShippingMethods')->with([$shippingMethodMock]);
        }
        $quoteMock->expects($this->atLeastOnce())->method('getId')->willReturn($quoteId);
        $paymentMock = $this->getMockBuilder(Checkmo::class)
            ->disableOriginalConstructor()
            ->getMock();
        $paymentListMock = [$paymentMock];
        $cartTotalMock = $this->getMockBuilder(Totals::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentMethodManagementMock->expects($this->once())
            ->method('getList')
            ->with($quoteId)
            ->willReturn($paymentListMock);
        $this->cartTotalsRepositoryMock->expects($this->once())
            ->method('get')
            ->with($quoteId)
            ->willReturn($cartTotalMock);
        $oscDetailsMock->expects($this->once())->method('setPaymentMethods')->with($paymentListMock);
        $oscDetailsMock->expects($this->once())->method('setTotals')->with($cartTotalMock);
        $itemMock = $this->getMockBuilder(Quote\Item::class)->disableOriginalConstructor()->getMock();

        $quoteMock->expects($this->once())->method('getAllVisibleItems')->willReturn([$itemMock]);
        $productMock = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
        $itemId = 119;
        $itemMock->expects($this->once())->method('getProduct')->willReturn($productMock);
        $itemMock->expects($this->exactly(3))->method('getId')->willReturn($itemId);
        $optionData = [119 => []];
        $imageData = [
            119 => [
                'src' => 'https://test.com/pub/media/catalog/product/cache/fff/w/b/wb06-red-0.jpg',
                'width' => 75,
                'height' => 75,
                'alt' => 'Endeavor Daytrip Backpack',
            ]
        ];
        $requestPath = [119 => 'https://test.com/endeavor-daytrip-backpack.html'];
        $productUrlMock = $this->getMockBuilder(Url::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->oscHelperMock->expects($this->once())
            ->method('getItemOptionsConfig')
            ->with($quoteMock, $itemMock)
            ->willReturn([]);
        $this->oscHelperMock->expects($this->once())
            ->method('getItemImages')
            ->with($itemMock)
            ->willReturn(
                [
                'src' => 'https://test.com/pub/media/catalog/product/cache/fff/w/b/wb06-red-0.jpg',
                'width' => 75,
                'height' => 75,
                'alt' => 'Endeavor Daytrip Backpack'
                ]
            );
        $productMock->expects($this->once())->method('getUrlModel')->willReturn($productUrlMock);
        $productUrlMock->expects($this->once())->method('getUrl')
            ->with($productMock)
            ->willReturn('https://test.com/endeavor-daytrip-backpack.html');
        $optionDataJson = '{"119":[]}';
        $imageDataJson = '{"119":{"src":"https:\/\/test.com\/pub\/media\/catalog\/product\/cache\/fff\/w\/b\/wb06-red-0.jpg","width":75,"height":75,"alt":"Endeavor Daytrip Backpack"}}';
        $requestPathJson = '{"119":"https:\/\/test.com\/endeavor-daytrip-backpack.html"}';
        $this->oscHelperMock->expects($this->exactly(3))
            ->method('jsonEncodeData')
            ->withConsecutive([$imageData], [$optionData], [$requestPath])
            ->willReturnOnConsecutiveCalls($imageDataJson, $optionDataJson, $requestPathJson);
        $oscDetailsMock->expects($this->once())->method('setImageData')->with($imageDataJson)->willReturnSelf();
        $oscDetailsMock->expects($this->once())->method('setOptions')->with($optionDataJson)->willReturnSelf();
        $oscDetailsMock->expects($this->once())->method('setRequestPath')->with($requestPathJson)->willReturnSelf();

        $this->assertEquals($oscDetailsMock, $this->checkoutManagement->getResponseData($quoteMock));
    }

    /**
     * @param boolean             $hasItem
     * @param boolean             $hasError
     * @param InvokedCountMatcher $hasErrorExpects
     * @param InvokedCountMatcher $validateMinimumAmountExpects
     *
     * @dataProvider providerTestGetResponseDataWithRedirectUrl
     *
     * @throws NoSuchEntityException
     */
    public function testGetResponseDataWithRedirectUrl(
        $hasItem,
        $hasError,
        $hasErrorExpects,
        $validateMinimumAmountExpects
    ) {
        /**
         * @var Quote $quoteMock
         */
        $quoteMethods = get_class_methods(Quote::class);
        $quoteMethods[] = 'getHasError';
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()->getMock();
        $oscDetailsMock = $this->getMockBuilder(OscDetails::class)
            ->disableOriginalConstructor()->getMock();
        $this->oscDetailsFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($oscDetailsMock);
        $quoteMock->expects($this->once())->method('hasItems')->willReturn($hasItem);
        $quoteMock->expects($hasErrorExpects)->method('getHasError')->willReturn($hasError);
        $quoteMock->expects($validateMinimumAmountExpects)->method('validateMinimumAmount')->willReturn(false);
        $urlMock = 'https://test.com/checkout/cart';
        $this->urlBuilderMock->expects($this->once())
            ->method('getUrl')
            ->with('checkout/cart')
            ->willReturn($urlMock);
        $oscDetailsMock->expects($this->once())->method('setRedirectUrl')->with($urlMock);

        $this->checkoutManagement->getResponseData($quoteMock);
    }

    /**
     * @return array
     */
    public function providerTestSaveCheckoutInformation()
    {
        return [
            [
                true,
                true
            ],
            [
                true,
                false
            ]
        ];
    }

    /**
     * @param boolean $isBillingSameShipping
     * @param boolean $isLoggedIn
     *
     * @dataProvider providerTestSaveCheckoutInformation
     *
     * @throws InputException
     * @throws ReflectionException
     */
    public function testSaveCheckoutInformation($isBillingSameShipping, $isLoggedIn)
    {
        $customerAttributes = [];
        $cartId = 1;
        $giftMessage = [
            'sender' => 'test@gmail.com',
            'recipient' => 'test1@gmail.com',
            'message' => 'test'
        ];
        $giftMessageJson = '{"sender":"test@gmail.com","recipient":"test1@gmail.com","message":"test"}';

        $additionInformation['customerAttributes'] = $customerAttributes;
        $additionInformation['giftMessage'] = $giftMessageJson;

        $this->checkoutSessionMock->expects($this->once())
            ->method('setOscData')
            ->with($additionInformation);
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()->getMock();
        $this->oscHelperMock->expects($this->once())->method('isDisabledGiftMessage')->willReturn(false);
        $this->oscHelperMock->expects($this->once())->method('jsonDecodeData')
            ->with($giftMessageJson)
            ->willReturn($giftMessage);
        $this->giftMessageMock->expects($this->once())->method('setSender')->with('test@gmail.com');
        $this->giftMessageMock->expects($this->once())->method('setRecipient')->with('test1@gmail.com');
        $this->giftMessageMock->expects($this->once())->method('setMessage')->with('test');
        $this->giftMessageManagerMock->expects($this->once())
            ->method('setMessage')
            ->with($quoteMock, 'quote', $this->giftMessageMock);
        $quoteAddressMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->getMock();

        /**
         * @var ShippingInformationInterface $addressInformationMock
         */
        $addressInformationMock = $this->getMockForAbstractClass(ShippingInformationInterface::class);
        $addressInformationMock->expects($this->atLeastOnce())
            ->method('getShippingAddress')
            ->willReturn($quoteAddressMock);

        $this->cartRepositoryMock->expects($this->once())->method('getActive')->with($cartId)->willReturn($quoteMock);
        if (!$isBillingSameShipping && $isLoggedIn) {
            $quoteAddressMock->expects($this->once())->method('setSaveInAddressBook')->with(0);
        }
        $this->shippingInformationManagementMock->expects($this->once())
            ->method('saveAddressInformation')
            ->with($cartId, $addressInformationMock);

        $this->assertTrue(
            $this->checkoutManagement->saveCheckoutInformation(
                $cartId,
                $addressInformationMock,
                $customerAttributes,
                $additionInformation
            )
        );
    }

    /**
     * @throws InputException
     * @throws ReflectionException
     */
    public function testSaveCheckoutInformationWithException()
    {
        $cartId = 1;
        $additionInformation['customerAttributes'] = [];
        $this->checkoutSessionMock->expects($this->once())
            ->method('setOscData')
            ->with($additionInformation);

        $quoteAddressMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->getMock();

        /**
         * @var ShippingInformationInterface $addressInformationMock
         */
        $addressInformationMock = $this->getMockForAbstractClass(ShippingInformationInterface::class);
        $addressInformationMock->expects($this->atLeastOnce())
            ->method('getShippingAddress')
            ->willReturn($quoteAddressMock);

        $exception = new Exception('test');
        $this->shippingInformationManagementMock->expects($this->once())
            ->method('saveAddressInformation')
            ->with($cartId, $addressInformationMock)
            ->willThrowException($exception);
        $this->loggerMock->expects($this->once())->method('critical')->with('test');
        $this->expectException(InputException::class);
        $this->expectExceptionMessage('Unable to save order information. Please check input data.');
        $this->checkoutManagement->saveCheckoutInformation(
            $cartId,
            $addressInformationMock,
            [],
            $additionInformation
        );
    }
}
