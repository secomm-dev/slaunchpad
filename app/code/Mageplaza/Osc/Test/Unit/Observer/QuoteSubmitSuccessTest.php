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

namespace Mageplaza\Osc\Test\Unit\Observer;

use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Session;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\GroupManagementInterface as CustomerGroupManagement;
use Magento\Customer\Model\Data\Customer;
use Magento\Customer\Model\Data\Group;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url;
use Magento\Downloadable\Model\Link\Purchased\ItemFactory;
use Magento\Downloadable\Model\Link\PurchasedFactory;
use Magento\Downloadable\Model\ResourceModel\Link\Purchased\Item\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject\Copy;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Phrase;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CustomerManagement;
use Mageplaza\Osc\Observer\QuoteSubmitSuccess;
use PHPUnit\Framework\MockObject\Matcher\InvokedCount as InvokedCountMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QuoteSubmitSuccessTest extends TestCase
{
    /**
     * @var Session|MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var AccountManagementInterface|MockObject
     */
    private $accountManagementMock;

    /**
     * @var Url|MockObject
     */
    private $customerUrlMock;

    /**
     * @var ManagerInterface|MockObject
     */
    private $messageManagerMock;

    /**
     * @var CustomerSession|MockObject
     */
    private $customerSessionMock;

    /**
     * @var SubscriberFactory|MockObject
     */
    private $subscriberFactoryMock;

    /**
     * @var CustomerManagement|MockObject
     */
    private $customerManagementMock;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfigMock;

    /**
     * @var PurchasedFactory|MockObject
     */
    private $purchasedFactoryMock;

    /**
     * @var ProductFactory|MockObject
     */
    private $productFactoryMock;

    /**
     * @var ItemFactory|MockObject
     */
    private $itemFactoryMock;

    /**
     * @var CollectionFactory|MockObject
     */
    private $itemsFactoryMock;

    /**
     * @var Copy|MockObject
     */
    private $objectCopyServiceMock;

    /**
     * @var CustomerGroupManagement|MockObject
     */
    private $customerGroupManagementMock;

    /**
     * @var QuoteSubmitSuccess
     */
    private $quoteSubmitSuccess;

    /**
     * @var MockObject
     */
    private $orderMock;

    /**
     * @var MockObject
     */
    private $quoteMock;

    /**
     * @var Observer|MockObject
     */
    private $observerMock;

    protected function setUp(): void
    {
        $this->checkoutSessionMock = $this->getMockBuilder(Session::class)
            ->setMethods(
                [
                'getOscData',
                'getIsCreatedAccountPaypalExpress',
                'unsIsCreatedAccountPaypalExpress',
                'unsOscData'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $this->accountManagementMock = $this->getMockForAbstractClass(AccountManagementInterface::class);
        $this->customerUrlMock = $this->getMockBuilder(Url::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->messageManagerMock = $this->getMockForAbstractClass(ManagerInterface::class);
        $this->customerSessionMock = $this->getMockBuilder(CustomerSession::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->subscriberFactoryMock = $this->getMockBuilder(SubscriberFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->customerManagementMock = $this->getMockBuilder(CustomerManagement::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->scopeConfigMock = $this->getMockForAbstractClass(ScopeConfigInterface::class);
        $this->purchasedFactoryMock = $this->getMockBuilder(PurchasedFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->productFactoryMock = $this->getMockBuilder(ProductFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->itemFactoryMock = $this->getMockBuilder(ItemFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->itemsFactoryMock = $this->getMockBuilder(CollectionFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->objectCopyServiceMock = $this->getMockBuilder(Copy::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->customerGroupManagementMock = $this->getMockBuilder(CustomerGroupManagement::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->observerMock = $this->getMockBuilder(Observer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $eventMock = $this->getMockBuilder(Event::class)
            ->setMethods(['getOrder', 'getQuote'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->observerMock->expects($this->exactly(2))->method('getEvent')->willReturn($eventMock);
        $eventMock->expects($this->once())->method('getOrder')->willReturn($this->orderMock);
        $eventMock->expects($this->once())->method('getQuote')->willReturn($this->quoteMock);

        $this->quoteSubmitSuccess = new QuoteSubmitSuccess(
            $this->checkoutSessionMock,
            $this->accountManagementMock,
            $this->customerUrlMock,
            $this->messageManagerMock,
            $this->customerSessionMock,
            $this->subscriberFactoryMock,
            $this->customerManagementMock,
            $this->scopeConfigMock,
            $this->purchasedFactoryMock,
            $this->productFactoryMock,
            $this->itemFactoryMock,
            $this->itemsFactoryMock,
            $this->objectCopyServiceMock,
            $this->customerGroupManagementMock
        );
    }

    /**
     * @return array
     */
    public function providerTestExecuteWithRegisterAccount()
    {
        return [
            [true, 1, 'account_confirmation_required', self::once()],
            [false, 0, '', self::never()],
            [false, 1, '', self::once()]
        ];
    }

    /**
     * @param boolean             $isCreatedAccountPaypalExpress
     * @param int                 $customerId
     * @param string              $confirmationStatus
     * @param InvokedCountMatcher $confirmationStatusExpects
     *
     * @dataProvider providerTestExecuteWithRegisterAccount
     *
     * @throws LocalizedException
     */
    public function testExecuteWithRegisterAccount(
        $isCreatedAccountPaypalExpress,
        $customerId,
        $confirmationStatus,
        $confirmationStatusExpects
    ) {
        $firstName = 'Veronica';
        $lastName = 'Costello';
        $middleName = '';
        $storeId = 1;
        $customerGroupId = 1;
        $orderId = 1;
        $email = 'test@gmail.com';
        $this->checkoutSessionMock->expects($this->once())
            ->method('getOscData')
            ->willReturn(
                [
                    'register' => true,
                    'password' => '12345'
                ]
            );
        $customerGroupMock = $this->getMockBuilder(Group::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteMock->expects($this->once())->method('getStoreId')->willReturn($storeId);
        $this->customerGroupManagementMock->expects($this->once())->method('getDefaultGroup')
            ->with($storeId)
            ->willReturn($customerGroupMock);
        $customerGroupMock->expects($this->once())->method('getId')->willReturn($customerGroupId);

        $billingAddressMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->getMock();
        $shippingAddressMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteMock->method('getBillingAddress')->willReturn($billingAddressMock);
        $this->quoteMock->method('getShippingAddress')->willReturn($shippingAddressMock);
        $billingAddressMock->expects($this->once())->method('getFirstname')->willReturn($firstName);
        $billingAddressMock->expects($this->once())->method('getLastname')->willReturn($lastName);
        $billingAddressMock->expects($this->once())->method('getMiddlename')->willReturn($middleName);
        $this->orderMock->expects($this->once())->method('setCustomerFirstname')
            ->with($firstName)
            ->willReturnSelf();
        $this->orderMock->expects($this->once())->method('setCustomerLastname')
            ->with($lastName)
            ->willReturnSelf();
        $this->orderMock->expects($this->once())->method('setCustomerMiddlename')
            ->with($middleName)
            ->willReturnSelf();
        $this->orderMock->expects($this->once())->method('setCustomerGroupId')
            ->with($customerGroupId)
            ->willReturnSelf();
        $billingAddressMock->expects($this->once())->method('setSaveInAddressBook')->with(1)->willReturnSelf();
        $shippingAddressMock->expects($this->once())->method('setSaveInAddressBook')->with(1)->willReturnSelf();
        $this->quoteMock->expects($this->once())->method('save');

        $this->checkoutSessionMock->expects($this->once())->method('getIsCreatedAccountPaypalExpress')
            ->willReturn($isCreatedAccountPaypalExpress);
        $customerMock = $this->getMockBuilder(Customer::class)
            ->disableOriginalConstructor()
            ->getMock();
        if ($isCreatedAccountPaypalExpress) {
            $this->quoteMock->expects($this->once())->method('getCustomer')->willReturn($customerMock);
        } else {
            $this->orderMock->expects($this->once())->method('getId')->willReturn($orderId);
            $this->customerManagementMock->expects($this->once())
                ->method('create')
                ->with($orderId)
                ->willReturn($customerMock);
        }
        $customerMock->expects($this->atLeastOnce())->method('getId')->willReturn($customerId);
        if ($customerId) {
            $billingAddressMock->expects($this->once())->method('setCustomerId')->with($customerId)->willReturnSelf();
            $shippingAddressMock->expects($this->once())->method('setCustomerId')->with($customerId)->willReturnSelf();
        }

        $this->accountManagementMock->expects($confirmationStatusExpects)
            ->method('getConfirmationStatus')
            ->with($customerId)
            ->willReturn($confirmationStatus);
        if ($customerId && $confirmationStatus) {
            $customerMock->expects($this->atLeastOnce())->method('getEmail')->willReturn($email);
            $customerUrl = 'some_url';
            $this->customerUrlMock->expects($this->once())
                ->method('getEmailConfirmationUrl')
                ->with($email)
                ->willReturn($customerUrl);
            $this->messageManagerMock->expects($this->once())->method('addSuccessMessage')
                ->with(
                    new Phrase(
                        'You must confirm your account. Please check your email for the confirmation link or <a href="%1">click here</a> for a new link.',
                        [$customerUrl]
                    )
                );
        } else {
            $this->customerSessionMock->expects($this->once())->method('loginById')->with($customerId);
        }

        $this->quoteMock->method('getAllItems')->willReturn([]);
        $this->checkoutSessionMock->expects($this->once())->method('unsOscData');

        $this->quoteSubmitSuccess->execute($this->observerMock);
    }

    /**
     * @return array
     */
    public function providerTestExecuteWithSubscribed()
    {
        return [
            [false],
            [true]
        ];
    }

    /**
     * @param boolean $isCustomerLogin
     *
     * @dataProvider providerTestExecuteWithSubscribed
     *
     * @throws LocalizedException
     */
    public function testExecuteWithSubscribed($isCustomerLogin)
    {
        $this->checkoutSessionMock->expects($this->once())
            ->method('getOscData')
            ->willReturn(
                [
                    'is_subscribed' => true
                ]
            );

        $this->customerSessionMock->expects($this->once())->method('isLoggedIn')->willReturn($isCustomerLogin);
        $subscribedEmail = 'test@gmail.com';
        if (!$isCustomerLogin) {
            $billingAddressMock = $this->getMockBuilder(Address::class)
                ->disableOriginalConstructor()
                ->getMock();
            $this->quoteMock->method('getBillingAddress')->willReturn($billingAddressMock);
            $billingAddressMock->expects($this->once())->method('getEmail')->willReturn($subscribedEmail);
        } else {
            $customerMock = $this->getMockBuilder(\Magento\Customer\Model\Customer::class)
                ->setMethods(['getEmail'])
                ->disableOriginalConstructor()
                ->getMock();
            $this->customerSessionMock->expects($this->once())->method('getCustomer')
                ->willReturn($customerMock);
            $customerMock->expects($this->once())->method('getEmail')->willReturn($subscribedEmail);
        }

        $subscribeMock = $this->getMockBuilder(Subscriber::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->subscriberFactoryMock->expects($this->once())->method('create')
            ->willReturn($subscribeMock);
        $subscribeMock->expects($this->once())->method('subscribe')->with($subscribedEmail);

        $this->checkoutSessionMock->expects($this->once())->method('unsOscData');

        $this->quoteSubmitSuccess->execute($this->observerMock);
    }
}
