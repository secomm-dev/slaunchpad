<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Fee;

use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Config\Source\RateInclude;
use Secomm\Ghtk\Model\Fee\FeeResult;
use Secomm\Ghtk\Model\Fee\RateComposer;

class RateComposerTest extends TestCase
{
    private RateComposer $composer;

    private FeeResult $fee;

    protected function setUp(): void
    {
        $this->composer = new RateComposer();
        $this->fee = new FeeResult(20000.0, 1500.0, 800.0, true, 'area1');
    }

    public function testBaseOnly(): void
    {
        $this->assertSame(20000.0, $this->composer->compose($this->fee, []));
    }

    public function testBasePlusInsurance(): void
    {
        $this->assertSame(21500.0, $this->composer->compose($this->fee, [RateInclude::INSURANCE_FEE]));
    }

    public function testBasePlusExt(): void
    {
        $this->assertSame(20800.0, $this->composer->compose($this->fee, [RateInclude::EXT_FEES]));
    }

    public function testBasePlusInsuranceAndExt(): void
    {
        $this->assertSame(22300.0, $this->composer->compose($this->fee, [RateInclude::INSURANCE_FEE, RateInclude::EXT_FEES]));
    }

    public function testNeverNegative(): void
    {
        $negative = new FeeResult(-5000.0, 0.0, 0.0, true, null);
        $this->assertSame(0.0, $this->composer->compose($negative, []));
    }

    public function testIgnoresUnknownIncludeKeys(): void
    {
        $this->assertSame(20000.0, $this->composer->compose($this->fee, ['unknown']));
    }
}
