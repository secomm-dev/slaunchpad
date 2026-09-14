<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Test\Unit\Model\Export;

use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\TestCase;
use Secomm\FulfillmentCore\Api\Data\ExportResultInterface;
use Secomm\FulfillmentCore\Api\OrderExporterInterface;
use Secomm\FulfillmentCore\Model\Export\ExporterPool;

class ExporterPoolTest extends TestCase
{
    public function testGetExportersKeysByServiceCode(): void
    {
        $exporter = $this->createMock(OrderExporterInterface::class);
        $exporter->method('getServiceCode')->willReturn('stub_oms');
        $exporter->method('isEnabled')->willReturn(true);
        $exporter->method('export')->willReturn($this->createMock(ExportResultInterface::class));

        $pool = new ExporterPool([$exporter]);
        $map = $pool->getExporters();

        $this->assertArrayHasKey('stub_oms', $map);
        $this->assertSame($exporter, $map['stub_oms']);
        $this->assertSame($exporter, $pool->getExporter('stub_oms'));
        $this->assertNull($pool->getExporter('missing'));
    }

    public function testEmptyPool(): void
    {
        $pool = new ExporterPool([]);
        $this->assertSame([], $pool->getExporters());
    }
}
