<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Rate;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CanonicalZoneInterface;
use Secomm\ShippingCore\Api\Address\CanonicalZoneMatcherInterface;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRegistryInterface;
use Secomm\ShippingCore\Api\Address\DestinationScope;
use Secomm\ShippingCore\Api\Rate\CarrierEligibilityResultInterface;
use Secomm\ShippingCore\Model\Address\CanonicalZone;
use Secomm\ShippingCore\Model\Rate\CarrierEligibilityEvaluator;

/**
 * TASK-8MQHJX (Phase B) — carrier eligibility evaluation: ALL short-circuit,
 * SELECTED_ZONES first-match, disabled/unknown zone skip, fail-closed ineligible.
 */
class CarrierEligibilityEvaluatorTest extends TestCase
{
    private CanonicalZoneMatcherInterface $zoneMatcher;
    private CanonicalZoneRegistryInterface $zoneRegistry;
    private CarrierEligibilityEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->zoneMatcher = $this->createMock(CanonicalZoneMatcherInterface::class);
        $this->zoneRegistry = $this->createMock(CanonicalZoneRegistryInterface::class);
        $this->evaluator = new CarrierEligibilityEvaluator($this->zoneMatcher, $this->zoneRegistry);
    }

    public function testAllScopeShortCircuitsWithoutZoneLookups(): void
    {
        $this->zoneRegistry->expects($this->never())->method('getByCode');
        $this->zoneMatcher->expects($this->never())->method('matches');

        $result = $this->evaluator->evaluate(DestinationScope::ALL, [], 'VN-SG', 'VNA25-26734');

        $this->assertTrue($result->isEligible());
        $this->assertSame(CarrierEligibilityResultInterface::REASON_ELIGIBLE, $result->getReasonCode());
        $this->assertNull($result->getMatchedZoneCode());
    }

    public function testSelectedZonesReturnsFirstMatchedZoneCode(): void
    {
        $zoneA = $this->makeZone('ZONE_A', false);
        $zoneB = $this->makeZone('ZONE_B', true);
        $this->zoneRegistry->method('getByCode')->willReturnCallback(
            static fn (string $code): ?CanonicalZoneInterface => match ($code) {
                'ZONE_A' => $zoneA,
                'ZONE_B' => $zoneB,
                default => null,
            }
        );
        $this->zoneMatcher->method('matches')->willReturnCallback(
            static fn (CanonicalZoneInterface $zone): bool => $zone->getCode() === 'ZONE_B'
        );

        $result = $this->evaluator->evaluate(
            DestinationScope::SELECTED_ZONES,
            ['ZONE_A', 'ZONE_B'],
            'VN-SG',
            'VNA25-26734'
        );

        $this->assertTrue($result->isEligible());
        $this->assertSame(CarrierEligibilityResultInterface::REASON_ELIGIBLE, $result->getReasonCode());
        $this->assertSame('ZONE_B', $result->getMatchedZoneCode());
    }

    public function testSelectedZonesSkipsUnknownAndDisabledZones(): void
    {
        $enabled = $this->makeZone('ENABLED', true);
        $this->zoneRegistry->method('getByCode')->willReturnCallback(
            static fn (string $code): ?CanonicalZoneInterface => $code === 'ENABLED' ? $enabled : null
        );
        $this->zoneMatcher->method('matches')->willReturn(true);

        $result = $this->evaluator->evaluate(
            DestinationScope::SELECTED_ZONES,
            ['UNKNOWN', 'ENABLED'],
            'VN-SG',
            null
        );

        $this->assertTrue($result->isEligible());
        $this->assertSame('ENABLED', $result->getMatchedZoneCode());
    }

    public function testSelectedZonesWithoutAnyMatchIsIneligible(): void
    {
        $zone = $this->makeZone('ZONE_A', true);
        $this->zoneRegistry->method('getByCode')->willReturn($zone);
        $this->zoneMatcher->method('matches')->willReturn(false);

        $result = $this->evaluator->evaluate(
            DestinationScope::SELECTED_ZONES,
            ['ZONE_A'],
            'VN-HN',
            null
        );

        $this->assertFalse($result->isEligible());
        $this->assertSame(
            CarrierEligibilityResultInterface::REASON_DESTINATION_NOT_IN_SCOPE,
            $result->getReasonCode()
        );
        $this->assertNull($result->getMatchedZoneCode());
    }

    public function testEmptyAllowedZonesIsIneligibleForSelectedScope(): void
    {
        $this->zoneRegistry->expects($this->never())->method('getByCode');

        $result = $this->evaluator->evaluate(DestinationScope::SELECTED_ZONES, [], 'VN-SG', null);

        $this->assertFalse($result->isEligible());
        $this->assertSame(
            CarrierEligibilityResultInterface::REASON_DESTINATION_NOT_IN_SCOPE,
            $result->getReasonCode()
        );
    }

    public function testUnknownScopeIsFailClosedIneligible(): void
    {
        $result = $this->evaluator->evaluate('MAGIC_SCOPE', [], 'VN-SG', null);

        $this->assertFalse($result->isEligible());
        $this->assertSame(
            CarrierEligibilityResultInterface::REASON_DESTINATION_NOT_IN_SCOPE,
            $result->getReasonCode()
        );
    }

    private function makeZone(string $code, bool $enabled): CanonicalZone
    {
        return new CanonicalZone($code, 'Zone ' . $code, $enabled);
    }
}
