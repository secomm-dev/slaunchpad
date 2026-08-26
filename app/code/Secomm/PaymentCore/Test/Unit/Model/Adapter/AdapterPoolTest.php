<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PaymentCore\Test\Unit\Model\Adapter;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Secomm\PaymentCore\Model\Adapter\AdapterPool;
use Secomm\PaymentCore\Model\Adapter\PaymentProviderAdapterInterface;

/**
 * FEAT-CSWYEJ / TASK-M20PT6 — adapter registry (SPEC §4.3, DEC D1).
 */
class AdapterPoolTest extends TestCase
{
    public function testEmptyPoolHasNoAdapter(): void
    {
        $pool = new AdapterPool([]);
        $this->assertFalse($pool->hasAdapter('vnpay'));
        $this->expectException(InvalidArgumentException::class);
        $pool->getAdapter('vnpay');
    }

    public function testPoolIndexesByMethodCode(): void
    {
        $adapter = $this->createMock(PaymentProviderAdapterInterface::class);
        $adapter->method('getMethodCode')->willReturn('vnpay');
        $pool = new AdapterPool([$adapter]);
        $this->assertTrue($pool->hasAdapter('vnpay'));
        $this->assertSame($adapter, $pool->getAdapter('vnpay'));
    }

    public function testPoolRejectsNonAdapterEntries(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AdapterPool([new \stdClass()]);
    }
}
