<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\ServiceLevel;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\ServiceLevel\ShippingServiceLevel;

/**
 * TASK-XXBN5X r1 — dynamic service-level definition VO invariants.
 */
class ShippingServiceLevelTest extends TestCase
{
    public function testTransportsCodeLabelEnabledAndSortOrder(): void
    {
        $level = new ShippingServiceLevel('EXPRESS', 'Giao nhanh 2 giờ', true, 10);

        $this->assertSame('EXPRESS', $level->getCode());
        $this->assertSame('Giao nhanh 2 giờ', $level->getLabel());
        $this->assertTrue($level->isEnabled());
        $this->assertSame(10, $level->getSortOrder());
    }

    public function testDefaultsToEnabledWithZeroSortOrder(): void
    {
        $level = new ShippingServiceLevel('ECONOMY', 'Tiết kiệm');

        $this->assertTrue($level->isEnabled());
        $this->assertSame(0, $level->getSortOrder());
    }

    public function testDisabledLevelIsTransported(): void
    {
        $level = new ShippingServiceLevel('SAME_DAY', 'Giao trong ngày', false, 20);

        $this->assertFalse($level->isEnabled());
        $this->addToAssertionCount(1);
    }

    public function testRejectsEmptyCode(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('non-empty machine code');
        new ShippingServiceLevel('  ', 'Standard');
    }

    public function testRejectsEmptyLabel(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('non-empty label');
        new ShippingServiceLevel('STANDARD', '');
    }
}
