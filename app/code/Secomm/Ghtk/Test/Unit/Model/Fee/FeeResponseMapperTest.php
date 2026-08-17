<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Fee;

use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Fee\FeeResponseMapper;

class FeeResponseMapperTest extends TestCase
{
    private FeeResponseMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new FeeResponseMapper();
    }

    public function testMapsValidFeeBlock(): void
    {
        $result = $this->mapper->map([
            'fee' => ['fee' => 12000, 'insurance_fee' => 1500, 'extFees' => 800, 'delivery' => true, 'name' => 'area1'],
        ]);

        $this->assertNotNull($result);
        $this->assertSame(12000.0, $result->fee);
        $this->assertSame(1500.0, $result->insuranceFee);
        $this->assertSame(800.0, $result->extFees);
        $this->assertTrue($result->delivery);
        $this->assertSame('area1', $result->name);
    }

    public function testDeliveryFalse(): void
    {
        $result = $this->mapper->map(['fee' => ['fee' => 1, 'delivery' => false]]);
        $this->assertNotNull($result);
        $this->assertFalse($result->delivery);
    }

    public function testDeliveryMissingDefaultsToDenied(): void
    {
        // Safe default: missing delivery -> denied -> no rate (AC-007).
        $result = $this->mapper->map(['fee' => ['fee' => 1]]);
        $this->assertNotNull($result);
        $this->assertFalse($result->delivery);
    }

    public function testNonNumericCoercedToZero(): void
    {
        $result = $this->mapper->map(['fee' => ['fee' => 'abc', 'insurance_fee' => null, 'extFees' => 'x']]);
        $this->assertNotNull($result);
        $this->assertSame(0.0, $result->fee);
        $this->assertSame(0.0, $result->insuranceFee);
        $this->assertSame(0.0, $result->extFees);
    }

    public function testReturnsNullWithoutFeeBlock(): void
    {
        $this->assertNull($this->mapper->map(['other' => 1]));
        $this->assertNull($this->mapper->map(['fee' => 'not-an-array']));
    }
}
