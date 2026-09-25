<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\CarrierCoverage;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CanonicalZoneInterface;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRegistryInterface;
use Secomm\ShippingCore\Model\CarrierCoverage\Validator;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 — Carrier Coverage save validation: availability enum, zone
 * pairing + existence (deleted rejected, disabled allowed), contract-enum modes/policies,
 * zone-code normalization. Replaces the deleted GHN backend model semantics.
 */
class ValidatorTest extends TestCase
{
    private CanonicalZoneRegistryInterface $zoneRegistry;

    private Validator $validator;

    protected function setUp(): void
    {
        $this->zoneRegistry = $this->createMock(CanonicalZoneRegistryInterface::class);
        $this->zoneRegistry->method('getByCode')->willReturnCallback(
            function (string $code): ?CanonicalZoneInterface {
                if ($code === 'HCM_INNER') {
                    $zone = $this->createMock(CanonicalZoneInterface::class);
                    $zone->method('isEnabled')->willReturn(false);

                    return $zone;
                }
                if ($code === 'HN_INNER') {
                    return $this->createMock(CanonicalZoneInterface::class);
                }

                return null;
            }
        );
        $this->validator = new Validator($this->zoneRegistry);
    }

    public function testValidAllVietnamAllowsEmptyZones(): void
    {
        $codes = $this->validator->validate('ALL', ['HCM_INNER'], 'CARRIER_WITH_FALLBACK', 'FALLBACK');

        $this->assertSame(['HCM_INNER'], $codes);
    }

    public function testZoneRequiringModeNeedsAtLeastOneZone(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('requires at least one selected zone');
        $this->validator->validate('SELECTED_ZONES', [], 'CARRIER_WITH_FALLBACK', 'FALLBACK');
    }

    public function testAllExceptSelectedZonesAlsoRequiresZones(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('requires at least one selected zone');
        $this->validator->validate('ALL_EXCEPT_SELECTED_ZONES', ['  '], 'CARRIER_ONLY', 'STRICT');
    }

    public function testDeletedZoneReferenceRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Zone "GHOST" does not exist');
        $this->validator->validate('SELECTED_ZONES', ['GHOST'], 'CARRIER_WITH_FALLBACK', 'FALLBACK');
    }

    public function testDisabledZoneReferenceAllowed(): void
    {
        // HCM_INNER resolves to a disabled zone — allowed (fails safe at runtime with a diagnostic).
        $codes = $this->validator->validate('SELECTED_ZONES', ['HCM_INNER'], 'CARRIER_ONLY', 'STRICT');

        $this->assertSame(['HCM_INNER'], $codes);
    }

    public function testUnknownAvailabilityRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown availability');
        $this->validator->validate('EVERYWHERE', [], 'CARRIER_WITH_FALLBACK', 'FALLBACK');
    }

    public function testUnknownRateSourceModeRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown Rate Source Mode');
        $this->validator->validate('ALL', [], 'MAGIC', 'FALLBACK');
    }

    public function testUnknownAddressResolutionPolicyRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown Address Resolution Policy');
        $this->validator->validate('ALL', [], 'CARRIER_ONLY', 'BEST_EFFORT');
    }

    public function testZoneCodesNormalizedTrimDedupeDropEmpty(): void
    {
        $codes = $this->validator->validate(
            'SELECTED_ZONES',
            [' HCM_INNER ', '', 'HN_INNER', 'HCM_INNER'],
            'CARRIER_WITH_FALLBACK',
            'FALLBACK'
        );

        $this->assertSame(['HCM_INNER', 'HN_INNER'], $codes);
    }
}
