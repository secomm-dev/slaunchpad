<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\ServiceLevel;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\ServiceLevelRateDecisionInterface;
use Secomm\ShippingCore\Model\Fallback\FallbackRate;
use Secomm\ShippingCore\Model\Rate\CarrierRate;
use Secomm\ShippingCore\Model\ServiceLevel\ServiceLevelRateDecision;

/**
 * TASK-M3ME32 — decision VO invariants (REALTIME/FALLBACK/UNAVAILABLE are mutually exclusive).
 */
class ServiceLevelRateDecisionTest extends TestCase
{
    public function testRealtimeDecisionShape(): void
    {
        $rates = ['carrierX' => new CarrierRate(30.0), 'carrierY' => new CarrierRate(35.0)];
        $decision = ServiceLevelRateDecision::realtime('LEVEL_A', $rates);

        $this->assertSame('LEVEL_A', $decision->getServiceLevelCode());
        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_REALTIME, $decision->getSource());
        $this->assertSame($rates, $decision->getRealtimeRates());
        $this->assertNull($decision->getFallbackRate());
        $this->assertTrue($decision->isAvailable());
    }

    public function testFallbackDecisionShape(): void
    {
        $fallbackRate = new FallbackRate(0.0, 'Free Fallback');
        $decision = ServiceLevelRateDecision::fallback('LEVEL_A', $fallbackRate);

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_FALLBACK, $decision->getSource());
        $this->assertSame([], $decision->getRealtimeRates());
        $this->assertSame($fallbackRate, $decision->getFallbackRate());
        $this->assertTrue($decision->isAvailable());
    }

    public function testUnavailableDecisionShape(): void
    {
        $decision = ServiceLevelRateDecision::unavailable('LEVEL_A');

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_UNAVAILABLE, $decision->getSource());
        $this->assertSame([], $decision->getRealtimeRates());
        $this->assertNull($decision->getFallbackRate());
        $this->assertFalse($decision->isAvailable());
    }

    public function testConstructorKeepsParityWithFactories(): void
    {
        $decision = new ServiceLevelRateDecision(
            'LEVEL_A',
            ServiceLevelRateDecisionInterface::SOURCE_REALTIME,
            ['carrierX' => new CarrierRate(30.0)]
        );

        $this->assertSame(ServiceLevelRateDecisionInterface::SOURCE_REALTIME, $decision->getSource());
        $this->addToAssertionCount(1);
    }

    public function testRejectsRealtimeWithoutRates(): void
    {
        $this->expectException(\LogicException::class);
        ServiceLevelRateDecision::realtime('LEVEL_A', []);
    }

    public function testRejectsRealtimeWithFallbackRate(): void
    {
        $this->expectException(\LogicException::class);
        new ServiceLevelRateDecision(
            'LEVEL_A',
            ServiceLevelRateDecisionInterface::SOURCE_REALTIME,
            ['carrierX' => new CarrierRate(30.0)],
            new FallbackRate(20.0, 'Fallback')
        );
    }

    public function testRejectsFallbackWithRealtimeRates(): void
    {
        $this->expectException(\LogicException::class);
        new ServiceLevelRateDecision(
            'LEVEL_A',
            ServiceLevelRateDecisionInterface::SOURCE_FALLBACK,
            ['carrierX' => new CarrierRate(30.0)],
            new FallbackRate(20.0, 'Fallback')
        );
    }

    public function testRejectsFallbackWithoutFallbackRate(): void
    {
        $this->expectException(\LogicException::class);
        new ServiceLevelRateDecision('LEVEL_A', ServiceLevelRateDecisionInterface::SOURCE_FALLBACK, [], null);
    }

    public function testRejectsUnavailableWithRates(): void
    {
        $this->expectException(\LogicException::class);
        new ServiceLevelRateDecision(
            'LEVEL_A',
            ServiceLevelRateDecisionInterface::SOURCE_UNAVAILABLE,
            ['carrierX' => new CarrierRate(30.0)]
        );
    }

    public function testRejectsUnknownSource(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unknown service-level rate decision source');
        new ServiceLevelRateDecision('LEVEL_A', 'CARRIER_RATE');
    }
}
