<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PancakeFunction\Test\Unit\Model\Mapping;

use PHPUnit\Framework\TestCase;
use Secomm\FulfillmentCore\Api\NormalizedFulfillmentStatus;
use Secomm\PancakeFunction\Model\Mapping\PancakeStatusMapper;

class PancakeStatusMapperTest extends TestCase
{
    public function testShippedMapsToShipped(): void
    {
        $mapper = new PancakeStatusMapper();
        $this->assertSame(NormalizedFulfillmentStatus::SHIPPED, $mapper->map('2'));
    }

    public function testUnmappedReturnsUnknown(): void
    {
        $mapper = new PancakeStatusMapper();
        $this->assertSame(NormalizedFulfillmentStatus::UNKNOWN, $mapper->map('4242'));
    }
}
