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

namespace Mageplaza\Osc\Test\Unit\Controller\Index;

use Exception;
use Magento\Catalog\Api\Data\ProductExtension;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Model\Type\Onepage;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Osc\Controller\Index\Index;
use Mageplaza\Osc\Helper\Data;
use PHPUnit\Framework\MockObject\Matcher\InvokedCount as InvokedCount;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Log\LoggerInterface;
use ReflectionException;

class IndexTest extends TestCase
{
    /**
     * @var ProductRepository|PHPUnit_Framework_MockObject_MockObject
     */
    private $productRepositoryMock;

    /**
     * @var StoreManagerInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $storeManagerMock;

    /**
     * @var Cart|PHPUnit_Framework_MockObject_MockObject
     */
    private $cartMock;

    /**
     * @var Configurable|PHPUnit_Framework_MockObject_MockObject
     */
    private $configurableMock;

    /**
     * @var TotalsCollector|PHPUnit_Framework_MockObject_MockObject
     */
    private $totalsCollectorMock;

    /**
     * @var ShippingMethodManagementInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $shippingMethodManagementMock;

    /**
     * @var CheckoutSession|PHPUnit_Framework_MockObject_MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var Data|PHPUnit_Framework_MockObject_MockObject
     */
    private $helperMock;

    /**
     * @var ManagerInterface|MockObject
     */
    private $messageManager;

    /**
     * @var RedirectFactory|MockObject
     */
    private $resultRedirectFactoryMock;

    /**
     * @var Session|MockObject
     */
    private $customerSessionMock;

    /**
     * @var LoggerInterface|MockObject
     */
    private $loggerMock;

    /**
     * @var RequestInterface|MockObject
     */
    private $requestMock;

    /**
     * @var MockObject|CartRepositoryInterface
     */
    protected $quoteRepositoryMock;

    /**
     * @var MockObject
     */
    protected $resultPageFactoryMock;

    /**
     * @var ObjectManagerInterface|MockObject $objectManagerMock
     */
    private $objectManagerMock;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var Index
     */
    private $indexController;

    protected function setup(): void
    {
        $this->objectManager = new ObjectManager($this);
        $contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $this->helperMock = $this->getMockBuilder(Data::class)->disableOriginalConstructor()->getMock();
        $this->customerSessionMock = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $this->productRepositoryMock = $this->getMockBuilder(ProductRepository::class)
            ->disableOriginalConstructor()->getMock();
        $this->totalsCollectorMock = $this->getMockBuilder(TotalsCollector::class)
            ->disableOriginalConstructor()->getMock();
        $this->resultPageFactoryMock = $this->getMockBuilder(PageFactory::class)
            ->disableOriginalConstructor()->getMock();
        $this->cartMock = $this->getMockBuilder(Cart::class)->disableOriginalConstructor()->getMock();
        $this->configurableMock = $this->getMockBuilder(Configurable::class)->disableOriginalConstructor()->getMock();
        $this->checkoutSessionMock = $this->getMockBuilder(CheckoutSession::class)
            ->setMethods(['setCartWasUpdated'])
            ->disableOriginalConstructor()->getMock();

        $this->storeManagerMock = $this->getMockForAbstractClass(StoreManagerInterface::class);
        $this->messageManager = $this->getMockForAbstractClass(ManagerInterface::class);
        $this->objectManagerMock = $this->getMockForAbstractClass(ObjectManagerInterface::class);
        $this->shippingMethodManagementMock = $this->getMockForAbstractClass(ShippingMethodManagementInterface::class);

        $this->requestMock = $this->getMockBuilder(RequestInterface::class)
            ->setMethods(['isSecure', 'getHeader'])
            ->getMockForAbstractClass();
        $this->quoteRepositoryMock = $this->getMockForAbstractClass(CartRepositoryInterface::class);
        $this->loggerMock = $this->getMockForAbstractClass(LoggerInterface::class);

        $this->resultRedirectFactoryMock = $this->getMockBuilder(RedirectFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();

        $contextMock->method('getResultRedirectFactory')->willReturn($this->resultRedirectFactoryMock);
        $contextMock->method('getMessageManager')->willReturn($this->messageManager);
        $contextMock->method('getObjectManager')->willReturn($this->objectManagerMock);
        $contextMock->method('getRequest')->willReturn($this->requestMock);
        $this->indexController = $this->objectManager->getObject(
            Index::class,
            [
                'context' => $contextMock,
                'helper' => $this->helperMock,
                'customerSession' => $this->customerSessionMock,
                'storeManager' => $this->storeManagerMock,
                'productRepository' => $this->productRepositoryMock,
                'configurable' => $this->configurableMock,
                'cart' => $this->cartMock,
                'quoteRepository' => $this->quoteRepositoryMock,
                'logger' => $this->loggerMock,
                'resultPageFactory' => $this->resultPageFactoryMock,
                'checkoutSession' => $this->checkoutSessionMock,
                'totalsCollector' => $this->totalsCollectorMock,
                'shippingMethodManagement' => $this->shippingMethodManagementMock
            ]
        );
    }

    public function testExecuteIsTurnedOff()
    {
        $this->helperMock->expects($this->once())->method('isEnabled')->willReturn(false);
        $this->messageManager->expects($this->once())
            ->method('addErrorMessage')
            ->with(new Phrase('One step checkout is turned off.'))
            ->willReturnSelf();
        $resultRedirectMock = $this->getMockBuilder(Redirect::class)
            ->disableOriginalConstructor()->getMock();
        $this->resultRedirectFactoryMock->expects($this->once())->method('create')->willReturn($resultRedirectMock);

        $resultRedirectMock->expects($this->once())
            ->method('setPath')
            ->with('checkout')
            ->willReturnSelf();

        $this->indexController->execute();
    }

    public function testExecuteWithGuestCheckoutDisabled()
    {
        $this->helperMock->expects($this->once())->method('isEnabled')->willReturn(true);
        $onepageMock = $this->getMockBuilder(Onepage::class)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->objectManagerMock->expects($this->once())
            ->method('get')
            ->with(Onepage::class)
            ->willReturn($onepageMock);

        $onepageMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);
        $this->customerSessionMock->expects($this->once())->method('isLoggedIn')->willReturn(false);
        $this->helperMock->expects($this->once())
            ->method('getAllowGuestCheckout')
            ->with($quoteMock)
            ->willReturn(false);
        $this->messageManager->expects($this->once())
            ->method('addErrorMessage')
            ->with(new Phrase('Guest checkout is disabled.'))
            ->willReturnSelf();

        $resultRedirectMock = $this->getMockBuilder(Redirect::class)
            ->disableOriginalConstructor()->getMock();
        $this->resultRedirectFactoryMock->expects($this->once())->method('create')->willReturn($resultRedirectMock);

        $resultRedirectMock->expects($this->once())
            ->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        $this->indexController->execute();
    }

    /**
     * @return array
     */
    public function providerTestExecuteWithAddProductCoupon()
    {
        return [
            [
                ['MB-001' => 2],
                '',
                true
            ],
            [
                ['MB-001' => 2],
                'test',
                true
            ],
            [
                ['MB-001' => 2],
                'test',
                true,
                [1]
            ]
        ];
    }

    /**
     * @param array   $sku
     * @param string  $coupon
     * @param boolean $isMinAmount
     * @param array   $configurableProductId
     *
     * @throws       LocalizedException
     * @throws       NoSuchEntityException
     * @dataProvider providerTestExecuteWithAddProductCoupon
     * @throws       ReflectionException
     */
    public function testExecuteWithAddProductCoupon($sku, $coupon, $isMinAmount, $configurableProductId = [])
    {
        $storeId = 1;
        $productId = 1;

        $this->helperMock->expects($this->once())->method('isEnabled')->willReturn(true);
        $onepageMock = $this->getMockBuilder(Onepage::class)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMethods = get_class_methods(Quote::class);
        $quoteMethods[] = 'getHasError';
        $quoteMethods[] = 'getCouponCode';
        $quoteMethods[] = 'setCouponCode';
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectManagerMock->expects($this->once())
            ->method('get')
            ->with(Onepage::class)
            ->willReturn($onepageMock);

        $onepageMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);
        $this->customerSessionMock->expects($this->once())->method('isLoggedIn')->willReturn(false);
        $this->helperMock->expects($this->once())
            ->method('getAllowGuestCheckout')
            ->with($quoteMock)
            ->willReturn(true);

        $this->requestMock->expects($this->at(0))
            ->method('getParam')
            ->with('sku')
            ->willReturn($sku);

        if ($sku) {
            $storeMock = $this->getMockForAbstractClass(StoreInterface::class);
            $this->storeManagerMock->expects($this->once())->method('getStore')->willReturn($storeMock);
            $storeMock->expects($this->once())->method('getId')->willReturn($storeId);
            $productMock = $this->getMockBuilder(Product::class)
                ->disableOriginalConstructor()
                ->getMock();
            $this->productRepositoryMock->expects($this->once())->method('get')
                ->with('MB-001', false, $storeId, true)
                ->willReturn($productMock);
            $extensionAttributes = $this->getMockBuilder(ProductExtension::class)
                ->disableOriginalConstructor()
                ->getMock();
            $stockItemInterface = $this->getMockForAbstractClass(StockItemInterface::class);
            $stockItemInterface->expects($this->once())->method('getIsInStock')->willReturn(true);

            $extensionAttributes->expects($this->atLeastOnce())
                ->method('getStockItem')
                ->willReturn($stockItemInterface);

            $productMock->expects($this->atLeastOnce())
                ->method('getExtensionAttributes')
                ->willReturn($extensionAttributes);
            $productMock->expects($this->once())->method('getId')->willReturn($productId);
            $this->configurableMock->expects($this->once())
                ->method('getParentIdsByChild')
                ->with($productId)
                ->willReturn($configurableProductId);

            if ($configurableProductId) {
                $parentProduct = $this->getMockBuilder(Product::class)
                    ->disableOriginalConstructor()
                    ->getMock();

                $this->productRepositoryMock->expects($this->once())
                    ->method('getById')
                    ->with(1)
                    ->willReturn($parentProduct);
                $configurableMock = $this->getMockBuilder(Configurable::class)
                    ->disableOriginalConstructor()
                    ->getMock();
                $parentProduct->expects($this->once())->method('getTypeInstance')
                    ->with(true)->willReturn($configurableMock);

                $attributes = [
                    142 => [
                        'attribute_id' => '212',
                        'attribute_code' => 'size'
                    ],
                    93 => [
                        'attribute_id' => '213',
                        'attribute_code' => 'color'
                    ]
                ];

                $configurableMock->expects($this->once())
                    ->method('getConfigurableAttributesAsArray')
                    ->with($productMock)
                    ->willReturn($attributes);
                $productMock->expects($this->exactly(2))
                    ->method('getData')
                    ->withConsecutive(['size'], ['color'])
                    ->willReturnOnConsecutiveCalls('5594', '5477');
                $requestInfoMock = [
                    'product' => 1,
                    'super_attribute' => [
                        212 => '5594',
                        213 => '5477'
                    ],
                    'qty' => 2
                ];
                $this->cartMock->expects($this->atLeastOnce())
                    ->method('addProduct')
                    ->with($parentProduct, $requestInfoMock)
                    ->willReturnSelf();
            } else {
                $this->cartMock->expects($this->atLeastOnce())
                    ->method('addProduct')
                    ->with($productMock, 2)
                    ->willReturnSelf();
            }

            $this->cartMock->expects($this->atLeastOnce())
                ->method('save')
                ->willReturnSelf();
        }

        $quoteMock->expects($this->once())->method('hasItems')->willReturn(true);
        $quoteMock->expects($this->once())->method('getHasError')->willReturn(false);
        $quoteMock->expects($this->once())->method('validateMinimumAmount')->willReturn($isMinAmount);

        $this->requestMock->expects($this->at(1))
            ->method('getParam')
            ->with('coupon')
            ->willReturn($coupon);

        if ($coupon) {
            $quoteMock->expects($this->once())->method('getCouponCode')->willReturn('');
            $quoteMock->expects($this->once())->method('getItemsCount')->willReturn(true);
            $addressMock = $this->getMockBuilder(Address::class)
                ->setMethods(['setCollectShippingRates'])
                ->disableOriginalConstructor()
                ->getMock();
            $quoteMock->expects($this->once())->method('getShippingAddress')->willReturn($addressMock);
            $addressMock->expects($this->once())
                ->method('setCollectShippingRates')
                ->with(true);
            $quoteMock->expects($this->once())->method('setCouponCode')->with($coupon)->willReturnSelf();
            $quoteMock->expects($this->once())->method('collectTotals')->willReturnSelf();
            $this->quoteRepositoryMock->method('save')->with($quoteMock);
        }

        $redirectPath = 'onestepcheckout';
        $this->helperMock->expects($this->once())->method('getOscRoute')->willReturn($redirectPath);

        $resultRedirectMock = $this->getMockBuilder(Redirect::class)
            ->disableOriginalConstructor()->getMock();
        $this->resultRedirectFactoryMock->expects($this->once())->method('create')->willReturn($resultRedirectMock);

        $resultRedirectMock->expects($this->once())
            ->method('setPath')
            ->with($redirectPath)
            ->willReturnSelf();

        $this->indexController->execute();
    }

    /**
     * @return array
     */
    public function providerTestExecuteWithRegenerateSessionId()
    {
        return [
            [
                'secure' => false,
                'referer' => 'https://abcd.com/',
                'expectedCall' => self::once()
            ],
            [
                'secure' => true,
                'referer' => false,
                'expectedCall' => self::once()
            ],
            [
                'secure' => true,
                'referer' => 'http://abcd.com/',
                'expectedCall' => self::once()
            ],
            [
                'secure' => true,
                'referer' => 'https://abcd.com/',
                'expectedCall' => self::never()
            ],
        ];
    }

    /**
     * @param string       $refer
     * @param boolean      $secure
     * @param InvokedCount $expectedCall
     *
     * @dataProvider providerTestExecuteWithRegenerateSessionId
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testExecuteWithRegenerateSessionId($secure, $refer, $expectedCall)
    {
        $this->helperMock->expects($this->once())->method('isEnabled')->willReturn(true);
        $onepageMock = $this->getMockBuilder(Onepage::class)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMethods = get_class_methods(Quote::class);
        $quoteMethods[] = 'getHasError';
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectManagerMock->expects($this->atLeastOnce())
            ->method('get')
            ->with(Onepage::class)
            ->willReturn($onepageMock);

        $onepageMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);
        $this->customerSessionMock->expects($this->once())->method('isLoggedIn')->willReturn(false);
        $this->helperMock->expects($this->once())
            ->method('getAllowGuestCheckout')
            ->with($quoteMock)
            ->willReturn(true);

        $this->requestMock->expects($this->exactly(2))
            ->method('getParam')
            ->withConsecutive(['sku'], ['coupon'])
            ->willReturnOnConsecutiveCalls([], '');

        $quoteMock->expects($this->once())->method('hasItems')->willReturn(true);
        $quoteMock->expects($this->once())->method('getHasError')->willReturn(false);
        $quoteMock->expects($this->once())->method('validateMinimumAmount')->willReturn(true);

        $this->requestMock->expects($this->once())
            ->method('getHeader')
            ->with('referer')
            ->willReturn($refer);
        $this->requestMock->method('isSecure')
            ->willReturn($secure);

        $this->customerSessionMock->expects($expectedCall)->method('regenerateId')->willReturnSelf();
        $addressMock = $this->getMockBuilder(Address::class)
            ->setMethods(['setCollectShippingRates', 'getCountryId'])
            ->disableOriginalConstructor()
            ->getMock();
        $addressMock->method('getCountryId')->willReturn('US');
        $quoteMock->expects($this->once())->method('getShippingAddress')->willReturn($addressMock);
        $resultPageMock = $this->getMockBuilder(Page::class)
            ->disableOriginalConstructor()->getMock();
        $this->resultPageFactoryMock->expects($this->once())->method('create')->willReturn($resultPageMock);

        $configMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $resultPageMock->expects($this->exactly(2))->method('getConfig')->willReturn($configMock);
        $titleMock = $this->getMockBuilder(Title::class)
            ->disableOriginalConstructor()->getMock();
        $configMock->expects($this->once())->method('getTitle')->willReturn($titleMock);

        $this->indexController->execute();
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    public function providerTestExecuteWithInitDefaultMethod()
    {
        $shippingMethodMock = $this->getMockForAbstractClass(ShippingMethodInterface::class);
        $shippingMethod2Mock = $this->getMockForAbstractClass(ShippingMethodInterface::class);
        $shippingMethodCode = 'tablerate_bestway';

        return [
            [
                'UK',
                [$shippingMethodMock],
                $shippingMethodCode,
                false
            ],
            [
                '',
                [$shippingMethodMock, $shippingMethod2Mock],
                '',
                true
            ],
            [
                'UK',
                [],
                '',
                false
            ],
            [
                'UK',
                [$shippingMethodMock, $shippingMethod2Mock],
                $shippingMethodCode,
                true
            ]
        ];
    }

    /**
     * @param string $defaultCountryId
     * @param array  $shippingMethodsMock
     * @param string $shippingMethod
     * @param string $isShowHeaderFooter
     *
     * @dataProvider providerTestExecuteWithInitDefaultMethod
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testExecuteWithInitDefaultMethod(
        $defaultCountryId,
        $shippingMethodsMock,
        $shippingMethod,
        $isShowHeaderFooter
    ) {
        $this->helperMock->expects($this->once())->method('isEnabled')->willReturn(true);
        $onepageMock = $this->getMockBuilder(Onepage::class)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMethods = get_class_methods(Quote::class);
        $quoteMethods[] = 'getHasError';
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectManagerMock->expects($this->atLeastOnce())
            ->method('get')
            ->with(Onepage::class)
            ->willReturn($onepageMock);

        $onepageMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);
        $this->customerSessionMock->expects($this->once())->method('isLoggedIn')->willReturn(false);
        $this->helperMock->expects($this->once())
            ->method('getAllowGuestCheckout')
            ->with($quoteMock)
            ->willReturn(true);

        $this->requestMock->expects($this->exactly(2))
            ->method('getParam')
            ->withConsecutive(['sku'], ['coupon'])
            ->willReturnOnConsecutiveCalls([], '');

        $quoteMock->expects($this->once())->method('hasItems')->willReturn(true);
        $quoteMock->expects($this->once())->method('getHasError')->willReturn(false);
        $quoteMock->expects($this->once())->method('validateMinimumAmount')->willReturn(true);

        $this->requestMock->expects($this->once())
            ->method('getHeader')
            ->with('referer')
            ->willReturn('https://test.domain.com/');
        $this->requestMock->method('isSecure')
            ->willReturn(true);

        $this->checkoutSessionMock->expects($this->once())->method('setCartWasUpdated')->with(false);
        $onepageMock->expects($this->once())->method('initCheckout')->willReturnSelf();

        $shippingAddressMethods = get_class_methods(Address::class);
        $shippingAddressMethods [] = 'setCollectShippingRates';
        $shippingAddressMock = $this->getMockBuilder(Address::class)
            ->setMethods($shippingAddressMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $quoteMock->expects($this->once())->method('getShippingAddress')->willReturn($shippingAddressMock);
        $shippingAddressMock->method('getCountryId')->willReturn($defaultCountryId);

        if (!$defaultCountryId) {
            $defaultCountryId = 'US';
            $this->helperMock->expects($this->once())
                ->method('getDefaultCountryId')
                ->willReturn($defaultCountryId);
            $shippingAddressMock->expects($this->once())
                ->method('setCountryId')
                ->with($defaultCountryId)
                ->willReturnSelf();
            $shippingAddressMock->expects($this->once())->method('save')->willReturnSelf();
        }

        $shippingAddressMock->expects($this->once())->method('setCollectShippingRates')->with(true);
        $this->totalsCollectorMock->expects($this->once())
            ->method('collectAddressTotals')
            ->with($quoteMock, $shippingAddressMock);
        $quoteId = 1;
        $quoteMock->expects($this->once())->method('getId')->willReturn($quoteId);

        $this->shippingMethodManagementMock->expects($this->once())
            ->method('getList')
            ->willReturn($shippingMethodsMock);
        $carrierCode = 'tablerate';
        $methodCode = 'bestway';
        $shippingMethodMock = '';
        if (count($shippingMethodsMock) === 1) {
            $shippingMethodMock = $shippingMethodsMock[0];
        } elseif (!$shippingMethod && $shippingMethodsMock) {
            $this->helperMock->expects($this->atLeastOnce())
                ->method('getDefaultShippingMethod')
                ->willReturnOnConsecutiveCalls('', $carrierCode . '_' . $methodCode);
            $shippingMethodMock = $shippingMethodsMock[1];
        }

        if ($shippingMethodMock) {
            $shippingMethodMock->expects($this->atLeastOnce())
                ->method('getCarrierCode')->willReturn($carrierCode);
            $shippingMethodMock->expects($this->atLeastOnce())
                ->method('getMethodCode')->willReturn($methodCode);
            $onepageMock->expects($this->once())->method('saveShippingMethod')->with($carrierCode . '_' . $methodCode);
            $this->quoteRepositoryMock->expects($this->once())
                ->method('save')
                ->with($quoteMock)
                ->willReturnSelf();
        }

        $resultPageMock = $this->getMockBuilder(Page::class)
            ->disableOriginalConstructor()->getMock();
        $this->resultPageFactoryMock->expects($this->once())->method('create')->willReturn($resultPageMock);
        $checkoutTitle = 'Test';

        $this->helperMock->expects($this->once())->method('getCheckoutTitle')->willReturn($checkoutTitle);

        $configMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $resultPageMock->expects($this->exactly(2))->method('getConfig')->willReturn($configMock);
        $titleMock = $this->getMockBuilder(Title::class)
            ->disableOriginalConstructor()->getMock();
        $titleMock->expects($this->once())->method('set')->with($checkoutTitle)->willReturnSelf();

        $this->helperMock->expects($this->once())->method('isShowHeaderFooter')->willReturn($isShowHeaderFooter);
        $configMock->expects($this->once())
            ->method('setPageLayout')
            ->willReturn($isShowHeaderFooter ? '1column' : 'checkout');

        $configMock->expects($this->once())->method('getTitle')->willReturn($titleMock);

        $this->indexController->execute();
    }

    public function testExecuteWithInitDefaultMethodException()
    {
        $this->helperMock->expects($this->once())->method('isEnabled')->willReturn(true);
        $onepageMock = $this->getMockBuilder(Onepage::class)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMethods = get_class_methods(Quote::class);
        $quoteMethods[] = 'getHasError';
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectManagerMock->expects($this->atLeastOnce())
            ->method('get')
            ->with(Onepage::class)
            ->willReturn($onepageMock);

        $onepageMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);
        $this->customerSessionMock->expects($this->once())->method('isLoggedIn')->willReturn(false);
        $this->helperMock->expects($this->once())
            ->method('getAllowGuestCheckout')
            ->with($quoteMock)
            ->willReturn(true);

        $this->requestMock->expects($this->exactly(2))
            ->method('getParam')
            ->withConsecutive(['sku'], ['coupon'])
            ->willReturnOnConsecutiveCalls([], '');

        $quoteMock->expects($this->once())->method('hasItems')->willReturn(true);
        $quoteMock->expects($this->once())->method('getHasError')->willReturn(false);
        $quoteMock->expects($this->once())->method('validateMinimumAmount')->willReturn(true);

        $this->requestMock->expects($this->once())
            ->method('getHeader')
            ->with('referer')
            ->willReturn('https://test.domain.com/');
        $this->requestMock->method('isSecure')
            ->willReturn(true);

        $this->checkoutSessionMock->expects($this->once())->method('setCartWasUpdated')->with(false);
        $onepageMock->expects($this->once())->method('initCheckout')->willReturnSelf();

        $shippingAddressMethods = get_class_methods(Address::class);
        $shippingAddressMethods [] = 'setCollectShippingRates';
        $shippingAddressMock = $this->getMockBuilder(Address::class)
            ->setMethods($shippingAddressMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $quoteMock->expects($this->once())->method('getShippingAddress')->willReturn($shippingAddressMock);
        $shippingAddressMock->expects($this->once())->method('getCountryId')->willReturn('US');
        $shippingAddressMock->expects($this->once())->method('setCollectShippingRates')->with(true);
        $this->totalsCollectorMock->expects($this->once())
            ->method('collectAddressTotals')
            ->with($quoteMock, $shippingAddressMock);
        $exception = new Exception('Test');
        $this->shippingMethodManagementMock->expects($this->once())
            ->method('getList')
            ->with(null)
            ->willThrowException($exception);
        $this->loggerMock->expects($this->once())->method('critical')->with('Test');

        $resultPageMock = $this->getMockBuilder(Page::class)
            ->disableOriginalConstructor()->getMock();
        $this->resultPageFactoryMock->expects($this->once())->method('create')->willReturn($resultPageMock);

        $configMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $resultPageMock->expects($this->exactly(2))->method('getConfig')->willReturn($configMock);
        $titleMock = $this->getMockBuilder(Title::class)
            ->disableOriginalConstructor()->getMock();
        $configMock->expects($this->once())->method('getTitle')->willReturn($titleMock);

        $this->indexController->execute();
    }

    /**
     * @return array
     */
    public function providerTestExecuteWithException()
    {
        return [
            [true],
            [false]
        ];
    }

    /**
     * @param boolean $isNoSuchEntity
     *
     * @dataProvider providerTestExecuteWithException
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws ReflectionException
     */
    public function testExecuteWithException($isNoSuchEntity)
    {
        $storeId = 1;

        $this->helperMock->expects($this->once())->method('isEnabled')->willReturn(true);
        $onepageMock = $this->getMockBuilder(Onepage::class)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMethods = get_class_methods(Quote::class);
        $quoteMethods[] = 'getHasError';
        $quoteMethods[] = 'getCouponCode';
        $quoteMethods[] = 'setCouponCode';
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectManagerMock->expects($this->once())
            ->method('get')
            ->with(Onepage::class)
            ->willReturn($onepageMock);

        $onepageMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);
        $this->customerSessionMock->expects($this->once())->method('isLoggedIn')->willReturn(false);
        $this->helperMock->expects($this->once())
            ->method('getAllowGuestCheckout')
            ->with($quoteMock)
            ->willReturn(true);

        $this->requestMock->expects($this->at(0))
            ->method('getParam')
            ->with('sku')
            ->willReturn(['MB-001' => 1]);

        $storeMock = $this->getMockForAbstractClass(StoreInterface::class);
        $this->storeManagerMock->expects($this->once())->method('getStore')->willReturn($storeMock);
        $storeMock->expects($this->once())->method('getId')->willReturn($storeId);

        if ($isNoSuchEntity) {
            $exception = new NoSuchEntityException(
                new Phrase("The product that was requested doesn't exist. Verify the product and try again.")
            );
            $this->productRepositoryMock->expects($this->once())->method('get')
                ->with('MB-001', false, $storeId, true)
                ->willThrowException($exception);
            $this->messageManager->expects($this->once())
                ->method('addErrorMessage')
                ->with(new Phrase('Requested %1 product doesn\'t exist', ['MB-001']))
                ->willReturnSelf();

            $criticalMessage = 'The product that was requested doesn\'t exist. Verify the product and try again.';
        } else {
            $criticalMessage = 'test exception';
            $this->productRepositoryMock->expects($this->once())->method('get')
                ->with('MB-001', false, $storeId, true)
                ->willThrowException(new Exception($criticalMessage));
        }

        $this->loggerMock->expects($this->once())->method('critical')
            ->with($criticalMessage);

        $resultRedirectMock = $this->getMockBuilder(Redirect::class)
            ->disableOriginalConstructor()->getMock();
        $this->resultRedirectFactoryMock->expects($this->once())->method('create')->willReturn($resultRedirectMock);

        $resultRedirectMock->expects($this->once())
            ->method('setPath')
            ->with('checkout/cart')
            ->willReturnSelf();

        $this->indexController->execute();
    }
}
