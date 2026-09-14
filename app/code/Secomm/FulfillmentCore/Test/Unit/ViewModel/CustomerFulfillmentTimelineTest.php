<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Test\Unit\ViewModel;

use PHPUnit\Framework\TestCase;
use Secomm\FulfillmentCore\Api\NormalizedFulfillmentStatus;
use Secomm\FulfillmentCore\Model\FulfillmentState;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentState\Collection;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentState\CollectionFactory;
use Secomm\FulfillmentCore\ViewModel\CustomerFulfillmentTimeline;

class CustomerFulfillmentTimelineTest extends TestCase
{
    public function testHasStateFalseWhenNoRow(): void
    {
        $empty = $this->createMock(FulfillmentState::class);
        $empty->method('getEntityId')->willReturn(null);

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($empty);

        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $vm = new CustomerFulfillmentTimeline($factory);
        $vm->setOrderId(10);

        $this->assertFalse($vm->hasState());
        $this->assertNull($vm->getCurrentStatus());
    }

    public function testStepReachedUsesRank(): void
    {
        $state = $this->createMock(FulfillmentState::class);
        $state->method('getEntityId')->willReturn(1);
        $state->method('getNormalizedStatus')->willReturn(NormalizedFulfillmentStatus::SHIPPED);
        $state->method('getCarrierName')->willReturn('CarrierX');
        $state->method('getTrackingNumber')->willReturn('T1');
        $state->method('getTrackingUrl')->willReturn(null);

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($state);

        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $vm = new CustomerFulfillmentTimeline($factory);
        $vm->setOrderId(10);

        $this->assertTrue($vm->hasState());
        $this->assertTrue($vm->isStepReached(NormalizedFulfillmentStatus::CONFIRMED));
        $this->assertTrue($vm->isStepReached(NormalizedFulfillmentStatus::SHIPPED));
        $this->assertFalse($vm->isStepReached(NormalizedFulfillmentStatus::DELIVERED));
        $this->assertTrue($vm->isCurrentStep(NormalizedFulfillmentStatus::SHIPPED));
        $this->assertSame('CarrierX', $vm->getCarrierName());
        $this->assertContains(NormalizedFulfillmentStatus::CONFIRMED, $vm->getSteps());
    }
}
