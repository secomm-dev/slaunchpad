<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Shipment;

use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Address\RateRequest;
use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\Shipment\ShipmentWeightCalculator;

class ShipmentWeightCalculatorTest extends TestCase
{
    private function calculator(string $unit, float $minWeight): ShipmentWeightCalculator
    {
        $config = $this->createMock(GhtkConfig::class);
        $config->method('getWeightUnit')->willReturn($unit);
        $config->method('getMinWeight')->willReturn($minWeight);

        return new ShipmentWeightCalculator($config);
    }

    private function item(float $weight, $qty, string $type, bool $virtual = false, ?DataObject $parent = null): DataObject
    {
        return new DataObject([
            'product' => new DataObject(['weight' => $weight]),
            'qty' => $qty,
            'is_virtual' => $virtual ? 1 : 0,
            'product_type' => $type,
            'parent_item' => $parent,
        ]);
    }

    private function request(array $items): RateRequest
    {
        $request = new RateRequest();
        $request->setAllItems($items);

        return $request;
    }

    public function testMixedCartKilograms(): void
    {
        // 0.5kg x2 (simple) + configurable parent skipped + child 0.3kg x1 + missing->min0.1 + virtual skip = 1.4kg = 1400g
        $parent = $this->item(0.0, 1, 'configurable');
        $calc = $this->calculator('kg', 0.1);
        $req = $this->request([
            $this->item(0.5, 2, 'simple'),
            $parent,
            $this->item(0.3, 1, 'simple', false, $parent),
            $this->item(0.0, 1, 'simple'), // missing weight -> min
            $this->item(9.0, 5, 'virtual', true),
        ]);

        $this->assertSame(1400, $calc->calculate($req));
    }

    public function testDecimalWeight(): void
    {
        $calc = $this->calculator('kg', 0.1);
        $this->assertSame(123, $calc->calculate($this->request([$this->item(0.123, 1, 'simple')])));
    }

    public function testQtyGreaterThanOne(): void
    {
        $calc = $this->calculator('kg', 0.1);
        $this->assertSame(750, $calc->calculate($this->request([$this->item(0.25, 3, 'simple')])));
    }

    public function testGramUnitIsNotMultiplied(): void
    {
        $calc = $this->calculator('g', 50.0);
        $this->assertSame(500, $calc->calculate($this->request([$this->item(250.0, 2, 'simple')])));
    }

    public function testFloatEpsilonDoesNotOverCeil(): void
    {
        // 1.4kg in float -> 1400.0000..2 must ceil to 1400, not 1401.
        $calc = $this->calculator('kg', 0.1);
        $this->assertSame(1400, $calc->calculate($this->request([
            $this->item(1.0, 1, 'simple'),
            $this->item(0.3, 1, 'simple'),
            $this->item(0.1, 1, 'simple'),
        ])));
    }

    public function testEmptyCartFallsBackToMin(): void
    {
        $calc = $this->calculator('kg', 0.2);
        $this->assertSame(200, $calc->calculate($this->request([])));
    }
}
