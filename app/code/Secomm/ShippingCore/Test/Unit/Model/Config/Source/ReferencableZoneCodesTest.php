<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Config\Source;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CanonicalZoneInterface;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRepositoryInterface;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierZoneIndex;
use Secomm\ShippingCore\Model\Config\Source\ReferencableZoneCodes;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 (final TL verification §6) — coverage picker options:
 * enabled zones offered; disabled-but-referenced zones stay VISIBLE marked "— Disabled"
 * (never silently lost on edit); disabled-unreferenced zones are NOT newly selectable.
 */
class ReferencableZoneCodesTest extends TestCase
{
    private function zone(string $code, string $label, bool $enabled): CanonicalZoneInterface
    {
        $zone = $this->createMock(CanonicalZoneInterface::class);
        $zone->method('getCode')->willReturn($code);
        $zone->method('getLabel')->willReturn($label);
        $zone->method('isEnabled')->willReturn($enabled);

        return $zone;
    }

    private function build(array $zones, array $referencedByCarrier): ReferencableZoneCodes
    {
        $repository = $this->createMock(CanonicalZoneRepositoryInterface::class);
        $repository->method('getAll')->willReturn($zones);
        $index = $this->createMock(CarrierZoneIndex::class);
        $index->method('carriersForZone')->willReturnCallback(
            static fn (string $code): array => $referencedByCarrier[$code] ?? []
        );

        return new ReferencableZoneCodes($repository, $index);
    }

    public function testEnabledZonesOfferedAndDisabledReferencedStayVisible(): void
    {
        $source = $this->build([
            $this->zone('HCM_INNER', 'Nội thành', true),
            $this->zone('DN_INNER', 'Đà Nẵng', false),
            $this->zone('HN_INNER', 'Hà Nội', false),
        ], ['DN_INNER' => ['secomm_ghn']]);

        $this->assertSame([
            ['value' => 'HCM_INNER', 'label' => 'Nội thành (HCM_INNER)'],
            ['value' => 'DN_INNER', 'label' => 'Đà Nẵng (DN_INNER) — Disabled'],
        ], $source->toOptionArray());
    }

    public function testDisabledUnreferencedZoneIsNotOffered(): void
    {
        $source = $this->build([
            $this->zone('HN_INNER', 'Hà Nội', false),
        ], []);

        $this->assertSame([], $source->toOptionArray());
    }

    public function testEmptyRegistryYieldsNothing(): void
    {
        $this->assertSame([], $this->build([], [])->toOptionArray());
    }
}
