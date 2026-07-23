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

use Magento\Checkout\Model\Session;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Data\Address;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\DataObject\Copy;
use Magento\Framework\Encryption\EncryptorInterface as Encryptor;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\CustomerManagement;
use Magento\Quote\Model\Quote;
use Mageplaza\Osc\Helper\Data;
use Mageplaza\Osc\Model\CheckoutRegister;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CheckoutRegisterTest extends TestCase
{
    /**
     * @var Session|MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var Copy|MockObject
     */
    private $objectCopyServiceMock;

    /**
     * @var DataObjectHelper|MockObject
     */
    private $dataObjectHelperMock;

    /**
     * @var AccountManagementInterface|MockObject
     */
    private $accountManagementMock;

    /**
     * @var CustomerManagement|MockObject
     */
    private $customerManagementMock;

    /**
     * @var Data|MockObject
     */
    private $oscHelperMock;

    /**
     * @var Encryptor|MockObject
     */
    private $encryptorMock;

    /**
     * @var CheckoutRegister
     */
    private $checkoutRegister;

    /**
     * @var MockObject
     */
    private $shippingAddressMock;

    /**
     * @var MockObject
     */
    private $billingAddressMock;

    protected function setUp(): void
    {
        $checkoutSessionMethods = get_class_methods(Session::class);
        $checkoutSessionMethods[] = 'getOscData';
        $checkoutSessionMethods[] = 'setIsCreatedAccountPaypalExpress';
        $this->checkoutSessionMock = $this->getMockBuilder(Session::class)
            ->setMethods($checkoutSessionMethods)
            ->disableOriginalConstructor()
            ->getMock();
        $this->objectCopyServiceMock = $this->getMockBuilder(Copy::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->dataObjectHelperMock = $this->getMockBuilder(DataObjectHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->accountManagementMock = $this->getMockForAbstractClass(AccountManagementInterface::class);
        $this->customerManagementMock = $this->getMockBuilder(CustomerManagement::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->oscHelperMock = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->encryptorMock = $this->getMockBuilder(Encryptor::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->checkoutRegister = new CheckoutRegister(
            $this->checkoutSessionMock,
            $this->objectCopyServiceMock,
            $this->dataObjectHelperMock,
            $this->accountManagementMock,
            $this->customerManagementMock,
            $this->oscHelperMock,
            $this->encryptorMock
        );
    }

    /**
     * @return array
     */
    public function providerTestCheckRegisterNewCustomer()
    {
        return [
            [
                false,
                true
            ]
        ];
    }

    /**
     * @param boolean $isVirtual
     *
     * @dataProvider providerTestCheckRegisterNewCustomer
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testCheckRegisterNewCustomerWithoutCreateAccount($isVirtual)
    {
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()->getMock();
        $this->mockValidateAddressBeforeSubmit($quoteMock, $isVirtual);

        $this->checkoutRegister->checkRegisterNewCustomer();
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testCheckRegisterNewCustomer()
    {
        $quoteMethods = get_class_methods(Quote::class);
        $quoteMethods[] = 'setCustomerGroupId';
        $quoteMethods[] = 'setPasswordHash';
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()->getMock();
        $oscData = [
            'register' => true,
            'password' => '123456'
        ];
        $this->mockValidateAddressBeforeSubmit($quoteMock, true, $oscData);
        $passwordHash = 'ac596951d6bb006ad1f386f6cb1da4e0d008250a6dcf90f0a1f6b22fc1b09a15:50XywpCu15tVwJsQ:2';
        $this->checkoutSessionMock->expects($this->once())
            ->method('setIsCreatedAccountPaypalExpress')
            ->with(true);
        $this->encryptorMock->expects($this->once())
            ->method('getHash')->with('123456', true)->willReturn($passwordHash);
        $quoteMock->expects($this->once())->method('setCheckoutMethod')
            ->with(Onepage::METHOD_REGISTER)->willReturnSelf();
        $quoteMock->expects($this->once())->method('setCustomerIsGuest')
            ->with(false)->willReturnSelf();
        $quoteMock->expects($this->once())->method('setCustomerGroupId')
            ->with(null)->willReturnSelf();
        $quoteMock->expects($this->once())->method('setPasswordHash')
            ->with($passwordHash)->willReturnSelf();

        $customerMock = $this->getMockBuilder(\Magento\Customer\Model\Data\Customer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($this->once())->method('getCustomer')->willReturn($customerMock);
        $customerBillingDataMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->billingAddressMock->expects($this->once())->method('exportCustomerAddress')
            ->willReturn($customerBillingDataMock);
        $this->billingAddressMock->expects($this->once())->method('getData')->willReturn([]);
        $customerBillingDataMock->expects($this->once())
            ->method('setIsDefaultBilling')
            ->with(true)
            ->willReturnSelf();

        $this->checkoutRegister->checkRegisterNewCustomer();
    }

    /**
     * @return array
     */
    public function providerTestPrepareNewCustomerQuoteWithQuoteIsVirtual()
    {
        return [
            [1]
        ];
    }

    /**
     * @param int $customerId
     *
     * @dataProvider providerTestPrepareNewCustomerQuoteWithQuoteIsVirtual
     */
    public function testPrepareNewCustomerQuoteWithQuoteIsVirtual($customerId)
    {
        $quoteMethods = get_class_methods(Quote::class);
        $quoteMethods[] = 'getCustomerEmail';
        $quoteMethods[] = 'setPasswordHash';
        $quoteMethods[] = 'getCustomerId';

        /**
         * @var Quote|MockObject $quoteMock
         */
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()->getMock();

        $billingAddressMethods = get_class_methods(Quote\Address::class);
        $billingAddressMethods[] = 'setShouldIgnoreValidation';
        $billingAddressMethods[] = 'setCustomerAddressData';
        $billingAddressMock = $this->getMockBuilder(Quote\Address::class)
            ->setMethods($billingAddressMethods)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($this->atLeastOnce())->method('getBillingAddress')->willReturn($billingAddressMock);

        $quoteMock->expects($this->atLeastOnce())->method('isVirtual')->willReturn(true);
        $customerMethods = get_class_methods(Customer::class);
        $customerMethods[] = 'setEmail';
        $customerMock = $this->getMockBuilder(\Magento\Customer\Model\Data\Customer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($this->once())->method('getCustomer')->willReturn($customerMock);
        $billingAddressMock->expects($this->once())->method('getData')->willReturn([]);
        $oscDataMock = [
            'customerAttributes' => []
        ];
        $this->dataObjectHelperMock->expects($this->once())->method('populateWithArray')
            ->with($customerMock, [], CustomerInterface::class);
        $email = 'test@gmail.com';
        $quoteMock->expects($this->once())->method('getCustomerEmail')->willReturn($email);
        $customerMock->expects($this->once())->method('setEmail')->with($email);
        $quoteMock->expects($this->once())->method('setCustomer')->with($customerMock)->willReturnSelf();
        $this->customerManagementMock->expects($this->once())->method('populateCustomerInfo')->with($quoteMock);
        $this->oscHelperMock->expects($this->once())->method('setFlagOscMethodRegister')->with(true);
        $customerBillingDataMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->getMock();
        $billingAddressMock->expects($this->once())->method('exportCustomerAddress')
            ->willReturn($customerBillingDataMock);
        $customerBillingDataMock->expects($this->once())->method('setIsDefaultBilling')->with(true)->willReturnSelf();
        $customerBillingDataMock->expects($this->once())->method('setData')->with('should_ignore_validation', true)
            ->willReturnSelf();
        $customerBillingDataMock->expects($this->once())->method('setIsDefaultShipping')->with(true)->willReturnSelf();
        $billingAddressMock->expects($this->once())->method('setCustomerAddressData')->with($customerBillingDataMock);
        $quoteMock->expects($this->once())->method('addCustomerAddress')->with($customerBillingDataMock);
        $quoteMock->expects($this->atLeastOnce())->method('getCustomerId')->willReturn($customerId);

        if ($customerId) {
            $billingAddressMock->expects($this->once())->method('setCustomerId')->with($customerId)->willReturnSelf();
        }

        $this->checkoutRegister->_prepareNewCustomerQuote($quoteMock, $oscDataMock);
    }

    /**
     * @return array
     */
    public function providerTestPrepareNewCustomerQuote()
    {
        return [
            [1, true],
            [1, false]
        ];
    }

    /**
     * @param int     $customerId
     * @param boolean $isSameAsShipping
     *
     * @dataProvider providerTestPrepareNewCustomerQuote
     */
    public function testPrepareNewCustomerQuote($customerId, $isSameAsShipping)
    {
        $quoteMethods = get_class_methods(Quote::class);
        $quoteMethods[] = 'getCustomerEmail';
        $quoteMethods[] = 'setPasswordHash';
        $quoteMethods[] = 'getCustomerId';

        $oscDataMock = [
            'customerAttributes' => [],
            'same_as_shipping' => $isSameAsShipping
        ];

        /**
         * @var Quote|MockObject $quoteMock
         */
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()->getMock();

        $billingAddressMethods = get_class_methods(Quote\Address::class);
        $billingAddressMethods[] = 'setShouldIgnoreValidation';
        $billingAddressMethods[] = 'setCustomerAddressData';
        $billingAddressMock = $this->getMockBuilder(Quote\Address::class)
            ->setMethods($billingAddressMethods)
            ->disableOriginalConstructor()
            ->getMock();

        $shippingAddressMethods = get_class_methods(Quote\Address::class);
        $shippingAddressMethods[] = 'setCustomerAddressData';
        $shippingAddressMock = $this->getMockBuilder(Quote\Address::class)
            ->setMethods($shippingAddressMethods)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($this->atLeastOnce())->method('getShippingAddress')->willReturn($shippingAddressMock);
        $quoteMock->expects($this->atLeastOnce())->method('getBillingAddress')->willReturn($billingAddressMock);

        $quoteMock->expects($this->atLeastOnce())->method('isVirtual')->willReturn(false);
        $customerMethods = get_class_methods(Customer::class);
        $customerMethods[] = 'setEmail';
        $customerMock = $this->getMockBuilder(\Magento\Customer\Model\Data\Customer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($this->once())->method('getCustomer')->willReturn($customerMock);
        $billingAddressMock->expects($this->once())->method('getData')->willReturn([]);

        $this->dataObjectHelperMock->expects($this->once())->method('populateWithArray')
            ->with($customerMock, [], CustomerInterface::class);
        $email = 'test@gmail.com';
        $quoteMock->expects($this->once())->method('getCustomerEmail')->willReturn($email);
        $customerMock->expects($this->once())->method('setEmail')->with($email);
        $quoteMock->expects($this->once())->method('setCustomer')->with($customerMock)->willReturnSelf();
        $this->customerManagementMock->expects($this->once())->method('populateCustomerInfo')->with($quoteMock);
        $this->oscHelperMock->expects($this->once())->method('setFlagOscMethodRegister')->with(true);
        $customerBillingDataMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->getMock();
        $billingAddressMock->expects($this->once())->method('exportCustomerAddress')
            ->willReturn($customerBillingDataMock);
        $customerBillingDataMock->expects($this->once())->method('setIsDefaultBilling')->with(true)->willReturnSelf();
        $customerBillingDataMock->expects($this->once())->method('setData')->with('should_ignore_validation', true)
            ->willReturnSelf();
        $quoteAt = 6;
        if ($isSameAsShipping) {
            $shippingAddressMock->expects($this->once())->method('setCustomerAddressData')
                ->with($customerBillingDataMock);
            $customerBillingDataMock->expects($this->once())
                ->method('setIsDefaultShipping')
                ->with(true)
                ->willReturnSelf();
        } else {
            $customerShippingDataMock = $this->getMockBuilder(Address::class)
                ->disableOriginalConstructor()
                ->getMock();
            $shippingAddressMock->expects($this->once())
                ->method('exportCustomerAddress')
                ->willReturn($customerShippingDataMock);
            $customerShippingDataMock->expects($this->once())->method('setIsDefaultShipping')
                ->with(true)->willReturnSelf();
            $customerShippingDataMock->expects($this->once())->method('setData')
                ->with('should_ignore_validation', true)->willReturnSelf();
            $shippingAddressMock->expects($this->once())
                ->method('setCustomerAddressData')
                ->with($customerShippingDataMock);
            $quoteMock->expects($this->at($quoteAt))->method('addCustomerAddress')->with($customerShippingDataMock);
            $quoteAt++;
        }

        $billingAddressMock->expects($this->once())->method('setCustomerAddressData')->with($customerBillingDataMock);
        $quoteMock->expects($this->at($quoteAt))->method('addCustomerAddress')->with($customerBillingDataMock);
        $quoteMock->expects($this->atLeastOnce())->method('getCustomerId')->willReturn($customerId);

        if ($customerId) {
            $billingAddressMock->expects($this->once())
                ->method('setCustomerId')->with($customerId)->willReturnSelf();
            $shippingAddressMock->expects($this->once())
                ->method('setCustomerId')->with($customerId)->willReturnSelf();
        }

        $this->checkoutRegister->_prepareNewCustomerQuote($quoteMock, $oscDataMock);
    }

    /**
     * @param Quote|MockObject $quoteMock
     * @param boolean          $isVirtual
     * @param array            $oscData
     */
    public function mockValidateAddressBeforeSubmit($quoteMock, $isVirtual, $oscData = [])
    {
        $this->checkoutSessionMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);
        $quoteMock->expects($this->atLeastOnce())->method('isVirtual')->willReturn($isVirtual);
        if (!$isVirtual) {
            $this->shippingAddressMock = $this->getMockBuilder(Quote\Address::class)
                ->setMethods(['setShouldIgnoreValidation'])
                ->disableOriginalConstructor()
                ->getMock();
            $quoteMock->expects($this->atLeastOnce())->method('getShippingAddress')
                ->willReturn($this->shippingAddressMock);
            $this->shippingAddressMock->expects($this->once())->method('setShouldIgnoreValidation')->with(true);
        }
        $billingAddressMethods = get_class_methods(Quote\Address::class);
        $billingAddressMethods[] = 'setShouldIgnoreValidation';

        $this->billingAddressMock = $this->getMockBuilder(Quote\Address::class)
            ->setMethods($billingAddressMethods)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteMock->expects($this->atLeastOnce())->method('getBillingAddress')->willReturn($this->billingAddressMock);
        $this->billingAddressMock->expects($this->once())->method('setShouldIgnoreValidation')->with(true);
        $this->checkoutSessionMock->expects($this->once())->method('getOscData')->willReturn($oscData);
    }
}
