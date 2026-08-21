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
use Magento\Customer\Model\Attribute;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Customer as ResourceCustomer;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Observer\Customer\CustomerSaveCommitAfter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(CustomerSaveCommitAfter::class)]
class CustomerSaveCommitAfterTest extends TestCase
{
    use MockCreationTrait;

    private EmailMarketing&MockObject $helperEmailMarketing;
    private LoggerInterface&MockObject $logger;
    private ResourceCustomer&MockObject $resourceCustomer;

    private CustomerSaveCommitAfter $subject;

    protected function setUp(): void
    {
        $this->helperEmailMarketing = $this->createMock(EmailMarketing::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->resourceCustomer = $this->createMock(ResourceCustomer::class);

        $this->subject = new CustomerSaveCommitAfter(
            $this->helperEmailMarketing,
            $this->logger,
            $this->resourceCustomer
        );
    }


    public function testExecuteNoopWhenMarketingDisabled(): void
    {
        $customer = $this->createCustomer(true);

        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(false);
        $this->helperEmailMarketing->expects($this->never())->method('getSecretKey');
        $this->helperEmailMarketing->expects($this->never())->method('getCustomerData');
        $this->resourceCustomer->expects($this->never())->method('getConnection');
        $this->logger->expects($this->never())->method('critical');

        $this->subject->execute($this->createObserver($customer));
    }

    public function testExecuteNoopWhenNoSecretKey(): void
    {
        $customer = $this->createCustomer(true);

        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('');
        $this->helperEmailMarketing->expects($this->never())->method('getAppID');
        $this->helperEmailMarketing->expects($this->never())->method('getCustomerData');
        $this->logger->expects($this->never())->method('critical');

        $this->subject->execute($this->createObserver($customer));
    }

    public function testExecuteNoopWhenNoAppId(): void
    {
        $customer = $this->createCustomer(true);

        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('');
        $this->helperEmailMarketing->expects($this->never())->method('getCustomerData');
        $this->logger->expects($this->never())->method('critical');

        $this->subject->execute($this->createObserver($customer));
    }

    public function testExecuteSyncsAndSetsSyncedFlagWhenNewRecordSyncSucceeds(): void
    {
        $customer = $this->createCustomer(true, null, 42);
        $data = ['id' => 42, 'email' => 'a@b.com'];

        $attribute = $this->createPartialMockWithReflection(Attribute::class, ['getId']);
        $attribute->method('getId')->willReturn(99);

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('insert')
            ->with('customer_entity_int', ['attribute_id' => 99, 'entity_id' => 42, 'value' => 1]);

        $this->resourceCustomer->method('getTable')->with('customer_entity_int')->willReturn('customer_entity_int');
        $this->resourceCustomer->method('getConnection')->willReturn($connection);

        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('app-id');
        $this->helperEmailMarketing->expects($this->once())
            ->method('getCustomerData')
            ->with($customer, false)
            ->willReturn($data);
        $this->helperEmailMarketing->expects($this->once())
            ->method('syncCustomer')
            ->with($data)
            ->willReturn(['success' => true]);
        $this->helperEmailMarketing->expects($this->once())->method('setIsSyncedCustomer')->with(true);
        $this->helperEmailMarketing->method('getSyncedAttribute')->willReturn($attribute);
        $this->logger->expects($this->never())->method('critical');

        $this->subject->execute($this->createObserver($customer));
    }

    public function testExecuteDoesNotSetSyncedFlagWhenNewRecordSyncFails(): void
    {
        $customer = $this->createCustomer(true, null, 42);
        $data = ['id' => 42];

        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('app-id');
        $this->helperEmailMarketing->method('getCustomerData')->willReturn($data);
        $this->helperEmailMarketing->method('syncCustomer')->willReturn(['success' => false]);
        $this->helperEmailMarketing->expects($this->never())->method('setIsSyncedCustomer');
        $this->helperEmailMarketing->expects($this->never())->method('getSyncedAttribute');
        $this->resourceCustomer->expects($this->never())->method('getConnection');
        $this->logger->expects($this->never())->method('critical');

        $this->subject->execute($this->createObserver($customer));
    }

    public function testExecuteSyncsExistingCustomerWhenDataChanged(): void
    {
        $origCustomer = $this->createCustomer(false, null, 42);
        $customer = $this->createCustomer(false, $origCustomer, 42);
        $data = ['id' => 42, 'email' => 'new@b.com'];
        $origData = ['id' => 42, 'email' => 'old@b.com'];

        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('app-id');
        // First call resolves $data (for $customer), second resolves $origData (for the orig object).
        $this->helperEmailMarketing->method('getCustomerData')->willReturn($data, $origData);
        $this->helperEmailMarketing->expects($this->once())->method('syncCustomer')->with($data, false);
        $this->logger->expects($this->never())->method('critical');

        $this->subject->execute($this->createObserver($customer));
    }

    public function testExecuteDoesNotSyncExistingCustomerWhenDataUnchanged(): void
    {
        $origCustomer = $this->createCustomer(false, null, 42);
        $customer = $this->createCustomer(false, $origCustomer, 42);
        $data = ['id' => 42, 'email' => 'same@b.com'];

        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('app-id');
        $this->helperEmailMarketing->method('getCustomerData')->willReturn($data, $data);
        $this->helperEmailMarketing->expects($this->never())->method('syncCustomer');
        $this->logger->expects($this->never())->method('critical');

        $this->subject->execute($this->createObserver($customer));
    }

    public function testExecuteLogsCriticalWhenGetCustomerDataThrows(): void
    {
        $customer = $this->createCustomer(true, null, 42);

        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('app-id');
        $this->helperEmailMarketing->method('getCustomerData')->willThrowException(new Exception('boom'));
        $this->helperEmailMarketing->expects($this->never())->method('syncCustomer');
        $this->logger->expects($this->once())->method('critical')->with('boom');

        $this->subject->execute($this->createObserver($customer));
    }

    public function testExecuteLogsCriticalWhenSyncCustomerThrows(): void
    {
        $customer = $this->createCustomer(true, null, 42);
        $data = ['id' => 42];

        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('app-id');
        $this->helperEmailMarketing->method('getCustomerData')->willReturn($data);
        $this->helperEmailMarketing->method('syncCustomer')->willThrowException(new Exception('sync failed'));
        $this->logger->expects($this->once())->method('critical')->with('sync failed');

        $this->subject->execute($this->createObserver($customer));
    }


    private function createObserver(Customer $customer): Observer&MockObject
    {
        $event = $this->createPartialMockWithReflection(Event::class, ['getDataObject']);
        $event->method('getDataObject')->willReturn($customer);

        $observer = $this->createPartialMockWithReflection(Observer::class, ['getEvent']);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    private function createCustomer(bool $isNewRecord, ?Customer $origObject = null, int $id = 1): Customer&MockObject
    {
        $customer = $this->createPartialMockWithReflection(
            Customer::class,
            ['getId', 'getIsNewRecord', 'getCustomOrigObject']
        );
        $customer->method('getId')->willReturn($id);
        $customer->method('getIsNewRecord')->willReturn($isNewRecord);
        $customer->method('getCustomOrigObject')->willReturn($origObject);

        return $customer;
    }
}
