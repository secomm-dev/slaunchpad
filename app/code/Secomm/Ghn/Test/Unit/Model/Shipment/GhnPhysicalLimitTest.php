<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Shipment;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\GhnShipmentConstraints;
use Secomm\Ghn\Model\Shipment\GhnPhysicalLimit;

/**
 * TASK-ZS2B41 — the CREATE-side physical limits are merchant-tunable via system config;
 * empty/non-positive config values fall back to the authoritative GhnShipmentConstraints
 * defaults (weight stays contract-frozen).
 */
class GhnPhysicalLimitTest extends TestCase
{
    private Config&MockObject $config;

    /** TASK-ZS2B41 (rev. 3-path) — mutable fixture: per-dimension configured shared limits. */
    private array $createDimensionLimitsCm = ['length' => 200, 'width' => 200, 'height' => 200];

    private GhnPhysicalLimit $limit;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getMaxLengthCm')->willReturnCallback(
            fn (): int => $this->createDimensionLimitsCm['length']
        );
        $this->config->method('getMaxWidthCm')->willReturnCallback(
            fn (): int => $this->createDimensionLimitsCm['width']
        );
        $this->config->method('getMaxHeightCm')->willReturnCallback(
            fn (): int => $this->createDimensionLimitsCm['height']
        );
        $this->limit = new GhnPhysicalLimit($this->config);
    }

    public function testDefaultsMirrorTheCreateContract(): void
    {
        $this->assertSame(200, $this->limit->getMaxLengthCm());
        $this->assertSame(200, $this->limit->getMaxWidthCm());
        $this->assertSame(200, $this->limit->getMaxHeightCm());
        $this->assertSame(GhnShipmentConstraints::TYPE_5_MAX_WEIGHT_G, $this->limit->getMaxPackageWeightG());
    }

    public function testConfiguredPerDimensionLimitsOverrideTheContractDefaults(): void
    {
        $this->createDimensionLimitsCm = ['length' => 180, 'width' => 190, 'height' => 210];

        $this->assertSame(180, $this->limit->getMaxLengthCm());
        $this->assertSame(190, $this->limit->getMaxWidthCm());
        $this->assertSame(210, $this->limit->getMaxHeightCm());
        // Weight limit stays contract-frozen (TASK-ZS2B41 scope: dimension only).
        $this->assertSame(GhnShipmentConstraints::TYPE_5_MAX_WEIGHT_G, $this->limit->getMaxPackageWeightG());
    }

    /** Constants remain the authoritative defaults for BC references. */
    public function testConstantsStillPointAtTheConstraints(): void
    {
        $this->assertSame(GhnShipmentConstraints::MAX_SIDE_CM, GhnPhysicalLimit::MAX_SIDE_CM);
        $this->assertSame(GhnShipmentConstraints::TYPE_5_MAX_WEIGHT_G, GhnPhysicalLimit::MAX_WEIGHT_G);
    }
}
