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

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as ResourceCustomer;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Mageplaza\Osc\Observer\ReindexCustomer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReindexCustomerTest extends TestCase
{
    /**
     * @var CustomerFactory|MockObject
     */
    private $customerFactoryMock;

    /**
     * @var ResourceCustomer|MockObject
     */
    private $resourceCustomerMock;

    /**
     * @var ReindexCustomer
     */
    private $reindexCustomerObserver;

    protected function setUp(): void
    {
        $this->customerFactoryMock = $this->getMockBuilder(CustomerFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->resourceCustomerMock = $this->getMockBuilder(ResourceCustomer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->reindexCustomerObserver = new ReindexCustomer(
            $this->customerFactoryMock,
            $this->resourceCustomerMock
        );
    }

    public function testExecute()
    {
        $customerId = 1;
        $table = 'customer_grid_flat';

        /**
         * @var Observer $observerMock
         */
        $observerMock = $this->getMockBuilder(Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $eventMock = $this->getMockBuilder(Event::class)
            ->setMethods(['getOrder'])
            ->disableOriginalConstructor()
            ->getMock();
        $observerMock->expects($this->once())->method('getEvent')->willReturn($eventMock);
        $orderMock = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->once())->method('getOrder')->willReturn($orderMock);
        $orderMock->expects($this->once())->method('getCustomerId')->willReturn($customerId);
        $this->resourceCustomerMock->expects($this->once())
            ->method('getTable')
            ->with($table)
            ->willReturn($table);
        $connectionMock = $this->getMockForAbstractClass(AdapterInterface::class);
        $this->resourceCustomerMock->expects($this->once())
            ->method('getConnection')
            ->willReturn($connectionMock);
        $selectMock = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()->getMock();
        $connectionMock->expects($this->once())->method('select')->willReturn($selectMock);
        $selectMock->expects($this->once())
            ->method('from')
            ->with($table, 'COUNT(*)')
            ->willReturnSelf();
        $selectMock->expects($this->once())
            ->method('where')
            ->with('entity_id = ?', $customerId)
            ->willReturnSelf();

        $connectionMock->expects($this->once())->method('fetchOne')->with($selectMock);
        $customerMock = $this->getMockBuilder(Customer::class)->disableOriginalConstructor()->getMock();
        $this->customerFactoryMock->expects($this->once())->method('create')->willReturn($customerMock);
        $customerMock->expects($this->once())->method('load')->with($customerId)->willReturnSelf();
        $customerMock->expects($this->once())->method('reindex');

        $this->reindexCustomerObserver->execute($observerMock);
    }
}
