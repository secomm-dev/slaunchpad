<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\OrderSubmit;

use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\OrderSubmit\OrderResponseMapper;

class OrderResponseMapperTest extends TestCase
{
    public function testSuccessWithTrackingCode(): void
    {
        $mapper = new OrderResponseMapper();
        $response = ['success' => true, 'order' => ['label' => 'S10001.P1', 'tracking_code' => 'S10001.P1.XXXX']];

        $this->assertTrue($mapper->success($response));
        $this->assertSame('S10001.P1', $mapper->labelId($response));
        $this->assertSame('S10001.P1.XXXX', $mapper->trackingNumber($response));
    }

    public function testTrackingFallsBackToLabel(): void
    {
        $mapper = new OrderResponseMapper();
        $response = ['success' => true, 'order' => ['label' => 'S10002.P1']];

        $this->assertSame('S10002.P1', $mapper->trackingNumber($response));
    }

    public function testNumericLabelIsCast(): void
    {
        $mapper = new OrderResponseMapper();
        $response = ['success' => true, 'order' => ['label_id' => 123456]];

        $this->assertSame('123456', $mapper->labelId($response));
    }

    public function testFailureCarriesMessage(): void
    {
        $mapper = new OrderResponseMapper();
        $response = ['success' => false, 'message' => 'Địa chỉ không hỗ trợ giao hàng'];

        $this->assertFalse($mapper->success($response));
        $this->assertSame('Địa chỉ không hỗ trợ giao hàng', $mapper->message($response));
    }

    public function testGarbageResponseIsSafe(): void
    {
        $mapper = new OrderResponseMapper();

        $this->assertFalse($mapper->success([]));
        $this->assertSame('Unknown GHTK error.', $mapper->message([]));
        $this->assertNull($mapper->labelId([]));
        $this->assertNull($mapper->trackingNumber([]));
    }
}
