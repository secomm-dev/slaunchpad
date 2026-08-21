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
 * @category    Mageplaza
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */
declare(strict_types=1);

namespace Mageplaza\Smtp\Test\Unit\Observer\Customer;

use Exception;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\Customer;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Observer\Customer\SaveAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(SaveAddress::class)]
class SaveAddressTest extends TestCase
{
    use MockCreationTrait;

    private EmailMarketing&MockObject $helperEmailMarketing;
    private LoggerInterface&MockObject $logger;

    private SaveAddress $subject;

    protected function setUp(): void
    {
        $this->helperEmailMarketing = $this->createMock(EmailMarketing::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subject = new SaveAddress($this->helperEmailMarketing, $this->logger);
    }


    public function testExecuteNoopWhenNotDefaultBilling(): void
    {
        $customer = $this->createCustomer(true);
        $customer->expects($this->never())->method('getMpSmtpIsSynced');
        $address = $this->createAddress(false, $customer);

        $this->helperEmailMarketing->expects($this->never())->method('isEnableEmailMarketing');
        $this->logger->expects($this->never())->method('critical');

        $this->subject->execute($this->createObserver($address));
    }

    public function testExecuteNoopWhenCustomerNotSynced(): void
    {
        $customer = $this->createCustomer(false);
        $address = $this->createAddress(true, $customer);

        $this->helperEmailMarketing->expects($this->never())->method('isEnableEmailMarketing');
        $this->logger->expects($this->never())->method('critical');

        $this->subject->execute($this->createObserver($address));
    }

    public function testExecuteNoopWhenMarketingDisabled(): void
    {
        $customer = $this->createCustomer(true);
        $address = $this->createAddress(true, $customer);

        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(false);
        $this->helperEmailMarketing->expects($this->never())->method('getSecretKey');
        $this->helperEmailMarketing->expects($this->never())->method('getCustomerData');
        $this->logger->expects($this->never())->method('critical');

        $this->subject->execute($this->createObserver($address));
    }

    public function testExecuteSyncsCustomerWhenAllConditionsTrue(): void
    {
        $customer = $this->createCustomer(true);
        $address = $this->createAddress(true, $customer);
        $data = ['id' => 1, 'email' => 'a@b.com'];

        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('app-id');
        $this->helperEmailMarketing->expects($this->once())
            ->method('getCustomerData')
            ->with($customer, true, false, $address)
            ->willReturn($data);
        $this->helperEmailMarketing->expects($this->once())
            ->method('syncCustomer')
            ->with($data, false);
        $this->logger->expects($this->never())->method('critical');

        $this->subject->execute($this->createObserver($address));
    }

    public function testExecuteLogsCriticalWhenGetCustomerDataThrows(): void
    {
        $customer = $this->createCustomer(true);
        $address = $this->createAddress(true, $customer);

        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('app-id');
        $this->helperEmailMarketing->method('getCustomerData')->willThrowException(new Exception('boom'));
        $this->helperEmailMarketing->expects($this->never())->method('syncCustomer');
        $this->logger->expects($this->once())->method('critical')->with('boom');

        $this->subject->execute($this->createObserver($address));
    }

    public function testExecuteLogsCriticalWhenSyncCustomerThrows(): void
    {
        $customer = $this->createCustomer(true);
        $address = $this->createAddress(true, $customer);
        $data = ['id' => 1];

        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('app-id');
        $this->helperEmailMarketing->method('getCustomerData')->willReturn($data);
        $this->helperEmailMarketing->method('syncCustomer')->willThrowException(new Exception('sync failed'));
        $this->logger->expects($this->once())->method('critical')->with('sync failed');

        $this->subject->execute($this->createObserver($address));
    }


    private function createObserver(Address $address): Observer&MockObject
    {
        $event = $this->createPartialMockWithReflection(Event::class, ['getDataObject']);
        $event->method('getDataObject')->willReturn($address);

        $observer = $this->createPartialMockWithReflection(Observer::class, ['getEvent']);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    private function createAddress(bool $isDefaultBilling, Customer $customer): Address&MockObject
    {
        $address = $this->createPartialMockWithReflection(Address::class, ['getIsDefaultBilling', 'getCustomer']);
        $address->method('getIsDefaultBilling')->willReturn($isDefaultBilling);
        $address->method('getCustomer')->willReturn($customer);

        return $address;
    }

    private function createCustomer(bool $isSynced): Customer&MockObject
    {
        $customer = $this->createPartialMockWithReflection(Customer::class, ['getMpSmtpIsSynced']);
        $customer->method('getMpSmtpIsSynced')->willReturn($isSynced);

        return $customer;
    }
}
