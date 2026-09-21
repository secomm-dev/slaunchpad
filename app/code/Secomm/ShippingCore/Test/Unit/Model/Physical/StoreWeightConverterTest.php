<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Physical;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\Physical\StoreWeightConverter;

/**
 * DEC-TASK9Q5ZAK-001 — the shared store-weight-unit conversion: kgs/lbs only, fail-closed
 * otherwise (the same raw number means 10 kg or 10 lb — never guessed).
 */
class StoreWeightConverterTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;

    private StoreWeightConverter $converter;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->converter = new StoreWeightConverter($this->scopeConfig);
    }

    public function testKilogramsMultiplyByThousand(): void
    {
        $this->givenWeightUnit('kgs');

        $this->assertSame(2500.0, $this->converter->toGrams(2.5, 1));
    }

    public function testPoundsConvertExactly(): void
    {
        $this->givenWeightUnit('LBS ');

        $this->assertEqualsWithDelta(453.59237, $this->converter->toGrams(1.0, 1), 0.00001);
    }

    public function testMissingUnitRefusesToConvert(): void
    {
        $this->givenWeightUnit('');

        $this->expectException(LocalizedException::class);
        $this->converter->toGrams(2.0, 1);
    }

    public function testUnknownUnitRefusesToConvert(): void
    {
        $this->givenWeightUnit('stones');

        $this->expectException(LocalizedException::class);
        $this->converter->toGrams(2.0, null);
    }

    private function givenWeightUnit(string $unit): void
    {
        $this->scopeConfig->method('getValue')->with(
            'general/locale/weight_unit',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->anything()
        )->willReturn($unit);
    }
}
