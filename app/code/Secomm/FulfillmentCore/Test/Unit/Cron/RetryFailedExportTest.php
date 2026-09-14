<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Test\Unit\Cron;

use PHPUnit\Framework\TestCase;
use Secomm\FulfillmentCore\Api\ExportPushStatus;
use Secomm\FulfillmentCore\Cron\RetryFailedExport;
use Secomm\FulfillmentCore\Model\Config\FulfillmentConfig;
use Secomm\FulfillmentCore\Model\Export\ExportOrchestrator;
use Secomm\FulfillmentCore\Model\FulfillmentExport;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport\Collection;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentExport\CollectionFactory;

class RetryFailedExportTest extends TestCase
{
    public function testExecuteRetriesEligibleRows(): void
    {
        $config = $this->createMock(FulfillmentConfig::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('getMaxAttempts')->willReturn(5);

        $row = $this->createMock(FulfillmentExport::class);
        $row->method('getEntityId')->willReturn(42);

        $collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addFieldToFilter', 'setPageSize', 'getIterator'])
            ->getMock();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$row]));

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $orchestrator = $this->createMock(ExportOrchestrator::class);
        $orchestrator->expects($this->once())->method('retryExport')->with(42);

        $cron = new RetryFailedExport($config, $collectionFactory, $orchestrator);
        $cron->execute();
    }

    public function testExecuteSkipsWhenDisabled(): void
    {
        $config = $this->createMock(FulfillmentConfig::class);
        $config->method('isEnabled')->willReturn(false);

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects($this->never())->method('create');

        $cron = new RetryFailedExport(
            $config,
            $collectionFactory,
            $this->createMock(ExportOrchestrator::class)
        );
        $cron->execute();
        $this->assertSame(ExportPushStatus::FAILED, ExportPushStatus::FAILED);
    }
}
