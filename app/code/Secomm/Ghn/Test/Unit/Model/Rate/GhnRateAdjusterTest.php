<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Rate;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Rate\GhnRateAdjuster;

/**
 * TASK-WAWNDS — the GHN rate buffer contract: applied AFTER a successful provider rate, never
 * to eligibility; providerRate and finalRate stay separately observable (logged).
 */
class GhnRateAdjusterTest extends TestCase
{
    private Config&MockObject $config;

    private GhnRateAdjuster $adjuster;

    /** Config surface (property-driven — PHPUnit first-registration-wins trap). */
    private bool $enabled = false;

    private string $type = 'fixed';

    private float $value = 0.0;

    private string $rounding = 'none';

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $config = $this->config;
        $this->config->method('isRateAdjustmentEnabled')->willReturnCallback(fn (): bool => $this->enabled);
        $this->config->method('getRateAdjustmentType')->willReturnCallback(fn (): string => $this->type);
        $this->config->method('getRateAdjustmentValue')->willReturnCallback(fn (): float => $this->value);
        $this->config->method('getRateAdjustmentRounding')->willReturnCallback(fn (): string => $this->rounding);
        $this->adjuster = new GhnRateAdjuster(
            $config,
            new GhnLogger($this->createMock(\Psr\Log\LoggerInterface::class))
        );
    }

    public function testDisabledAdjustmentPassesProviderRateThrough(): void
    {
        $this->assertSame(53900.0, $this->adjuster->adjust(53900.0, 1));
    }

    public function testFixedAdjustmentAddsConfiguredValue(): void
    {
        $this->enabled = true;
        $this->value = 10000.0;

        $this->assertSame(63900.0, $this->adjuster->adjust(53900.0, 1));
    }

    public function testPercentAdjustmentScalesProviderRate(): void
    {
        $this->enabled = true;
        $this->type = 'percent';
        $this->value = 5.0;

        $this->assertSame(56595.0, $this->adjuster->adjust(53900.0, 1));
    }

    public function testRoundingRoundUpToStep(): void
    {
        $this->enabled = true;
        $this->value = 100.0;
        $this->rounding = '5000';

        // 54,000 → ceil to the nearest 5,000
        $this->assertSame(55000.0, $this->adjuster->adjust(53900.0, 1));
    }

    public function testZeroProviderRateStaysZero(): void
    {
        // Zero is a valid promotional rate; a fixed buffer would raise it — allowed, because
        // the buffer never decides ELIGIBILITY, only the displayed price of a valid quote.
        $this->enabled = true;
        $this->value = 5000.0;
        $this->rounding = 'none';

        $this->assertSame(5000.0, $this->adjuster->adjust(0.0, 1));
    }
}
