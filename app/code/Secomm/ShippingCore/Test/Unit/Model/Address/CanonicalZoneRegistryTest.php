<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Address;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CanonicalZoneInterface;
use Secomm\ShippingCore\Model\Address\CanonicalZone;
use Secomm\ShippingCore\Model\Address\CanonicalZoneRegistry;

/**
 * TASK-8MQHJX (Phase A) — canonical zone registry: zero-zone valid, getByCode, getEnabled,
 * duplicate code fail-fast.
 */
class CanonicalZoneRegistryTest extends TestCase
{
    public function testZeroZonesIsValidState(): void
    {
        $registry = new CanonicalZoneRegistry([]);

        $this->assertSame([], $registry->getAll());
        $this->assertSame([], $registry->getEnabled());
        $this->assertNull($registry->getByCode('HCM_INNER'));
    }

    public function testGetByCodeReturnsRegisteredZone(): void
    {
        $zone = new CanonicalZone('HCM_INNER', 'Nội thành TP.HCM');
        $registry = new CanonicalZoneRegistry([$zone]);

        $this->assertSame($zone, $registry->getByCode('HCM_INNER'));
        $this->assertNull($registry->getByCode('UNKNOWN'));
    }

    public function testGetEnabledFiltersDisabledZones(): void
    {
        $enabled = new CanonicalZone('A', 'Zone A', true);
        $disabled = new CanonicalZone('B', 'Zone B', false);
        $registry = new CanonicalZoneRegistry([$enabled, $disabled]);

        $this->assertSame([$enabled], $registry->getEnabled());
    }

    public function testDuplicateZoneCodeFailsFast(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Duplicate canonical zone code');

        new CanonicalZoneRegistry([
            new CanonicalZone('HCM_INNER', 'First'),
            new CanonicalZone('HCM_INNER', 'Second'),
        ]);
    }

    public function testEmptyCodeFailsFast(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('non-empty');

        new CanonicalZoneRegistry([
            $this->createMock(CanonicalZoneInterface::class),
        ]);
    }
}
