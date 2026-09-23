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
use Secomm\ShippingCore\Model\Address\CanonicalZoneMatcher;

/**
 * TASK-8MQHJX (Phase B) — deterministic zone matching precedence (fail-closed):
 * disabled → false; province gate; include-ward gate; exclude-ward wins; unrestricted → true.
 */
class CanonicalZoneMatcherTest extends TestCase
{
    private CanonicalZoneMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new CanonicalZoneMatcher();
    }

    public function testDisabledZoneNeverMatches(): void
    {
        $zone = new CanonicalZone('Z', 'Z', false, [], [], []);

        $this->assertFalse($this->matcher->matches($zone, 'VN-SG', 'VNA25-26734'));
    }

    public function testUnrestrictedZoneMatchesAnyDestination(): void
    {
        $zone = new CanonicalZone('Z', 'Z');

        $this->assertTrue($this->matcher->matches($zone, 'VN-SG', 'VNA25-26734'));
        $this->assertTrue($this->matcher->matches($zone, 'VN-HN', null));
    }

    public function testProvinceMismatchIsFalse(): void
    {
        $zone = new CanonicalZone('Z', 'Z', true, ['VN-SG'], [], []);

        $this->assertTrue($this->matcher->matches($zone, 'VN-SG', null));
        $this->assertFalse($this->matcher->matches($zone, 'VN-HN', null));
    }

    public function testIncludeWardsRequireNonNullMatchingWard(): void
    {
        $zone = new CanonicalZone('Z', 'Z', true, ['VN-SG'], ['VNA25-26734'], []);

        // ward in include list → match
        $this->assertTrue($this->matcher->matches($zone, 'VN-SG', 'VNA25-26734'));
        // ward not in include list → no match
        $this->assertFalse($this->matcher->matches($zone, 'VN-SG', 'VNA25-26740'));
        // ward missing (null) → fail-closed, không treat là "tất cả ward"
        $this->assertFalse($this->matcher->matches($zone, 'VN-SG', null));
    }

    public function testExcludeWardWinsOverMatchingProvince(): void
    {
        $zone = new CanonicalZone('Z', 'Z', true, ['VN-SG'], [], ['VNA25-90819']);

        $this->assertFalse($this->matcher->matches($zone, 'VN-SG', 'VNA25-90819'));
        $this->assertTrue($this->matcher->matches($zone, 'VN-SG', 'VNA25-26734'));
        // ward missing → exclude không áp dụng được → vẫn match theo province
        $this->assertTrue($this->matcher->matches($zone, 'VN-SG', null));
    }

    public function testIncludeAndExcludeCombined(): void
    {
        $zone = new CanonicalZone('Z', 'Z', true, ['VN-SG'], ['VNA25-26734', 'VNA25-26740'], ['VNA25-26740']);

        $this->assertTrue($this->matcher->matches($zone, 'VN-SG', 'VNA25-26734'));
        // vừa include vừa exclude → exclude thắng
        $this->assertFalse($this->matcher->matches($zone, 'VN-SG', 'VNA25-26740'));
    }

    public function testMatchingIsCaseSensitiveStrict(): void
    {
        $zone = new CanonicalZone('Z', 'Z', true, ['VN-SG'], [], []);

        $this->assertFalse($this->matcher->matches($zone, 'vn-sg', null));
    }
}
