<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Address;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\Address\CanonicalZone;

/**
 * TASK-8MQHJX (Phase A) — canonical zone VO: defaults, getters, fail-fast invariants.
 */
class CanonicalZoneTest extends TestCase
{
    public function testMinimalZoneHasDefaults(): void
    {
        $zone = new CanonicalZone('HCM_INNER', 'Nội thành TP.HCM');

        $this->assertSame('HCM_INNER', $zone->getCode());
        $this->assertSame('Nội thành TP.HCM', $zone->getLabel());
        $this->assertTrue($zone->isEnabled());
        $this->assertSame([], $zone->getIncludeProvinceCodes());
        $this->assertSame([], $zone->getIncludeWardCodes());
        $this->assertSame([], $zone->getExcludeWardCodes());
    }

    public function testFullZonePreservesAllFields(): void
    {
        $zone = new CanonicalZone(
            'HCM_INNER',
            'Nội thành TP.HCM',
            false,
            ['VN-SG'],
            ['VNA25-26734', 'VNA25-26740'],
            ['VNA25-90819']
        );

        $this->assertSame('HCM_INNER', $zone->getCode());
        $this->assertFalse($zone->isEnabled());
        $this->assertSame(['VN-SG'], $zone->getIncludeProvinceCodes());
        $this->assertSame(['VNA25-26734', 'VNA25-26740'], $zone->getIncludeWardCodes());
        $this->assertSame(['VNA25-90819'], $zone->getExcludeWardCodes());
    }

    public function testWhitespaceCodeFailsFast(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty code');

        new CanonicalZone('   ', 'Label');
    }

    public function testEmptyLabelFailsFast(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty label');

        new CanonicalZone('HCM_INNER', '');
    }
}
