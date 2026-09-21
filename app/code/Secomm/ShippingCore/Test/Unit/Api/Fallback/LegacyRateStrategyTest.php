<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Api\Fallback;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Fallback\LegacyRateStrategy;

/**
 * TASK-5JQYMP — legacy RATE strategy identities. Implementation naming: DIRECT_FALLBACK
 * (≡ architecture §15.1 wording "FALLBACK_ONLY") | MAP_THEN_FALLBACK.
 */
class LegacyRateStrategyTest extends TestCase
{
    public function testStrategyIdentitiesAreStable(): void
    {
        $this->assertSame('DIRECT_FALLBACK', LegacyRateStrategy::DIRECT_FALLBACK);
        $this->assertSame('MAP_THEN_FALLBACK', LegacyRateStrategy::MAP_THEN_FALLBACK);
        $this->assertSame(['DIRECT_FALLBACK', 'MAP_THEN_FALLBACK'], LegacyRateStrategy::all());
    }

    public function testExistsMatrix(): void
    {
        $this->assertTrue(LegacyRateStrategy::exists(LegacyRateStrategy::DIRECT_FALLBACK));
        $this->assertTrue(LegacyRateStrategy::exists(LegacyRateStrategy::MAP_THEN_FALLBACK));
        $this->assertFalse(LegacyRateStrategy::exists('FALLBACK_ONLY'));
        $this->assertFalse(LegacyRateStrategy::exists(''));
    }

    public function testAssertKnownAcceptsKnownStrategies(): void
    {
        foreach (LegacyRateStrategy::all() as $strategy) {
            LegacyRateStrategy::assertKnown($strategy);
            $this->addToAssertionCount(1);
        }
    }

    public function testAssertKnownRejectsUnknownStrategy(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown legacy RATE strategy');
        LegacyRateStrategy::assertKnown('FALLBACK_ONLY');
    }
}
