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
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Newsletter\Model\Subscriber;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Observer\Customer\SubscriberSaveCommitAfter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(SubscriberSaveCommitAfter::class)]
class SubscriberSaveCommitAfterTest extends TestCase
{
    use MockCreationTrait;

    private EmailMarketing&MockObject $helperEmailMarketing;
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private LoggerInterface&MockObject $logger;

    private SubscriberSaveCommitAfter $subject;

    protected function setUp(): void
    {
        $this->helperEmailMarketing = $this->createMock(EmailMarketing::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subject = new SubscriberSaveCommitAfter(
            $this->helperEmailMarketing,
            $this->customerRepository,
            $this->logger
        );
    }

    private function createSubscriberMock(): Subscriber&MockObject
    {
        return $this->createPartialMockWithReflection(
            Subscriber::class,
            ['getOrigObject', 'isStatusChanged', 'getSubscriberEmail', 'getSubscriberStatus', 'getChangeStatusAt']
        );
    }

    private function createObserver(Subscriber $subscriber): Observer&MockObject
    {
        $event = $this->createPartialMockWithReflection(Event::class, ['getDataObject']);
        $event->method('getDataObject')->willReturn($subscriber);

        $observer = $this->createPartialMockWithReflection(Observer::class, ['getEvent']);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    private function enableGuardConditions(): void
    {
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('app-id');
        $this->helperEmailMarketing->method('isSyncedCustomer')->willReturn(false);
    }


    public function testExecuteNoOpWhenEmailMarketingDisabled(): void
    {
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(false);
        $this->helperEmailMarketing->expects($this->never())->method('getSecretKey');
        $this->customerRepository->expects($this->never())->method('get');

        $subscriber = $this->createSubscriberMock();

        $this->subject->execute($this->createObserver($subscriber));
    }

    public function testExecuteNoOpWhenAlreadySyncedViaCustomerFlow(): void
    {
        $this->helperEmailMarketing->method('isEnableEmailMarketing')->willReturn(true);
        $this->helperEmailMarketing->method('getSecretKey')->willReturn('secret');
        $this->helperEmailMarketing->method('getAppID')->willReturn('app-id');
        $this->helperEmailMarketing->method('isSyncedCustomer')->willReturn(true);
        $this->helperEmailMarketing->expects($this->never())->method('syncCustomer');
        $this->customerRepository->expects($this->never())->method('get');

        $subscriber = $this->createSubscriberMock();

        $this->subject->execute($this->createObserver($subscriber));
    }

    public function testExecuteNoOpWhenOrigObjectPresentAndStatusUnchanged(): void
    {
        $this->enableGuardConditions();
        $this->helperEmailMarketing->expects($this->never())->method('syncCustomer');
        $this->customerRepository->expects($this->never())->method('get');

        $subscriber = $this->createSubscriberMock();
        $subscriber->method('getOrigObject')->willReturn($subscriber);
        $subscriber->method('isStatusChanged')->willReturn(false);

        $this->subject->execute($this->createObserver($subscriber));
    }

    public function testExecuteProceedsWhenNewSubscriber(): void
    {
        $this->enableGuardConditions();

        $subscriber = $this->createSubscriberMock();
        $subscriber->method('getOrigObject')->willReturn(null);
        $subscriber->method('isStatusChanged')->willReturn(false);
        $subscriber->method('getSubscriberEmail')->willReturn('new@example.com');
        $subscriber->method('getSubscriberStatus')->willReturn(Subscriber::STATUS_SUBSCRIBED);
        $subscriber->method('getChangeStatusAt')->willReturn('2026-08-01 00:00:00');

        $this->helperEmailMarketing->method('formatDate')->with('2026-08-01 00:00:00')->willReturn('formatted');
        $this->customerRepository->method('get')->with('new@example.com')
            ->willThrowException(new Exception('not found'));

        $this->helperEmailMarketing->expects($this->once())->method('syncCustomer')->with(
            $this->callback(static function (array $data): bool {
                return $data['email'] === 'new@example.com'
                    && $data['firstName'] === ''
                    && $data['lastName'] === ''
                    && $data['isSubscriber'] === true
                    && $data['customer_type'] === 'new_subscriber'
                    && $data['updated_at'] === 'formatted';
            }),
            false
        );

        $this->subject->execute($this->createObserver($subscriber));
    }

    public function testExecuteProceedsWhenExistingSubscriberStatusChanged(): void
    {
        $this->enableGuardConditions();

        $subscriber = $this->createSubscriberMock();
        $subscriber->method('getOrigObject')->willReturn($subscriber);
        $subscriber->method('isStatusChanged')->willReturn(true);
        $subscriber->method('getSubscriberEmail')->willReturn('changed@example.com');
        $subscriber->method('getSubscriberStatus')->willReturn(0);
        $subscriber->method('getChangeStatusAt')->willReturn('2026-08-02 00:00:00');

        $this->helperEmailMarketing->method('formatDate')->willReturn('formatted');
        $this->customerRepository->method('get')->willThrowException(new Exception('not found'));

        $this->helperEmailMarketing->expects($this->once())->method('syncCustomer');

        $this->subject->execute($this->createObserver($subscriber));
    }

    public function testExecutePopulatesNameWhenMatchingCustomerFound(): void
    {
        $this->enableGuardConditions();

        $subscriber = $this->createSubscriberMock();
        $subscriber->method('getOrigObject')->willReturn(null);
        $subscriber->method('isStatusChanged')->willReturn(false);
        $subscriber->method('getSubscriberEmail')->willReturn('john@example.com');
        $subscriber->method('getSubscriberStatus')->willReturn(Subscriber::STATUS_SUBSCRIBED);
        $subscriber->method('getChangeStatusAt')->willReturn('2026-08-03 00:00:00');

        $this->helperEmailMarketing->method('formatDate')->willReturn('formatted');

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(9);
        $customer->method('getFirstname')->willReturn('John');
        $customer->method('getLastname')->willReturn('Doe');
        $this->customerRepository->method('get')->with('john@example.com')->willReturn($customer);

        $this->helperEmailMarketing->expects($this->once())->method('syncCustomer')->with(
            $this->callback(static function (array $data): bool {
                return $data['firstName'] === 'John' && $data['lastName'] === 'Doe';
            }),
            false
        );

        $this->subject->execute($this->createObserver($subscriber));
    }

    public function testExecuteLeavesNamesEmptyWhenCustomerLookupThrows(): void
    {
        $this->enableGuardConditions();

        $subscriber = $this->createSubscriberMock();
        $subscriber->method('getOrigObject')->willReturn(null);
        $subscriber->method('isStatusChanged')->willReturn(false);
        $subscriber->method('getSubscriberEmail')->willReturn('nobody@example.com');
        $subscriber->method('getSubscriberStatus')->willReturn(0);
        $subscriber->method('getChangeStatusAt')->willReturn('2026-08-04 00:00:00');

        $this->helperEmailMarketing->method('formatDate')->willReturn('formatted');
        $this->customerRepository->method('get')->willThrowException(new Exception('not found'));

        $this->helperEmailMarketing->expects($this->once())->method('syncCustomer')->with(
            $this->callback(static function (array $data): bool {
                return $data['firstName'] === '' && $data['lastName'] === '';
            }),
            false
        );

        $this->subject->execute($this->createObserver($subscriber));
    }

    public function testExecuteCatchesExceptionAndLogsWhenSyncCustomerThrows(): void
    {
        $this->enableGuardConditions();

        $subscriber = $this->createSubscriberMock();
        $subscriber->method('getOrigObject')->willReturn(null);
        $subscriber->method('isStatusChanged')->willReturn(false);
        $subscriber->method('getSubscriberEmail')->willReturn('fail@example.com');
        $subscriber->method('getSubscriberStatus')->willReturn(Subscriber::STATUS_SUBSCRIBED);
        $subscriber->method('getChangeStatusAt')->willReturn('2026-08-05 00:00:00');

        $this->helperEmailMarketing->method('formatDate')->willReturn('formatted');
        $this->customerRepository->method('get')->willThrowException(new Exception('not found'));
        $this->helperEmailMarketing->method('syncCustomer')->willThrowException(new Exception('sync failed'));

        $this->logger->expects($this->once())->method('critical')->with('sync failed');

        $this->subject->execute($this->createObserver($subscriber));
    }


    public function testGetCustomerByEmailReturnsCustomerWhenFound(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $this->customerRepository->method('get')->with('found@example.com')->willReturn($customer);

        $this->assertSame($customer, $this->subject->getCustomerByEmail('found@example.com'));
    }

    public function testGetCustomerByEmailReturnsEmptyStringWhenRepositoryThrows(): void
    {
        $this->customerRepository->method('get')->willThrowException(new Exception('not found'));

        $this->assertSame('', $this->subject->getCustomerByEmail('missing@example.com'));
    }
}
