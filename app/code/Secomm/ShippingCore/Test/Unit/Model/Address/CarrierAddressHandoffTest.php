<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Address;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Model\Address\CarrierAddressHandoff;
use Secomm\ShippingCore\Model\Address\ResolvedShippingAddress;
use Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface;

/**
 * TASK-T78YH6 — carrier handoff VO invariants (contradictory outcomes are unconstructible).
 */
class CarrierAddressHandoffTest extends TestCase
{
    private ResolvedShippingAddress $resolved;

    protected function setUp(): void
    {
        $this->resolved = new ResolvedShippingAddress(
            VnAddressResolutionInterface::STATUS_MAPPED,
            'VN_ADMIN_PRE_2025',
            'VNAP25-9F8E7D6C5B'
        );
    }

    public function testResolvedHandoffShape(): void
    {
        $handoff = new CarrierAddressHandoff(true, $this->resolved, false, null);

        $this->assertTrue($handoff->isApplicable());
        $this->assertSame($this->resolved, $handoff->getResolvedAddress());
        $this->assertFalse($handoff->isTextualFallbackEligible());
        $this->assertNull($handoff->getFailureReason());
        // "Resolved" is derived — the contract deliberately has no isResolved() member.
        $this->assertNotFalse(method_exists($handoff, 'getResolvedAddress'));
        $this->assertFalse(method_exists($handoff, 'isResolved'));
    }

    public function testUnresolvedWithoutFallbackShape(): void
    {
        $handoff = new CarrierAddressHandoff(
            true,
            null,
            false,
            ShippingFailureReason::CANONICAL_UNRESOLVED
        );

        $this->assertTrue($handoff->isApplicable());
        $this->assertNull($handoff->getResolvedAddress());
        $this->assertFalse($handoff->isTextualFallbackEligible());
        $this->assertSame(ShippingFailureReason::CANONICAL_UNRESOLVED, $handoff->getFailureReason());
    }

    public function testUnresolvedWithFallbackEligibilityShape(): void
    {
        $handoff = new CarrierAddressHandoff(
            true,
            null,
            true,
            ShippingFailureReason::CANONICAL_UNRESOLVED
        );

        $this->assertTrue($handoff->isTextualFallbackEligible());
        $this->assertNull($handoff->getResolvedAddress());
    }

    public function testNotApplicableShape(): void
    {
        $handoff = new CarrierAddressHandoff(
            false,
            null,
            false,
            ShippingFailureReason::UNSUPPORTED_DESTINATION
        );

        $this->assertFalse($handoff->isApplicable());
        $this->assertNull($handoff->getResolvedAddress());
        $this->assertSame(ShippingFailureReason::UNSUPPORTED_DESTINATION, $handoff->getFailureReason());
    }

    public function testRejectsResolvedHandoffWithFallback(): void
    {
        $this->expectException(\LogicException::class);
        new CarrierAddressHandoff(true, $this->resolved, true, null);
    }

    public function testRejectsResolvedHandoffWithReason(): void
    {
        $this->expectException(\LogicException::class);
        new CarrierAddressHandoff(true, $this->resolved, false, ShippingFailureReason::CANONICAL_UNRESOLVED);
    }

    public function testRejectsUnresolvedHandoffWithoutCanonicalReason(): void
    {
        $this->expectException(\LogicException::class);
        new CarrierAddressHandoff(true, null, true, ShippingFailureReason::UNSUPPORTED_DESTINATION);
    }

    public function testRejectsNotApplicableHandoffWithResolvedAddress(): void
    {
        $this->expectException(\LogicException::class);
        new CarrierAddressHandoff(false, $this->resolved, false, ShippingFailureReason::UNSUPPORTED_DESTINATION);
    }

    public function testRejectsNotApplicableHandoffWithoutReason(): void
    {
        $this->expectException(\LogicException::class);
        new CarrierAddressHandoff(false, null, false, null);
    }
}
