<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Address\GhtkOperationAddressCapability;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;
use Secomm\Ghtk\Model\GhtkApiProfile;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-7AJ3K8 — the GHTK API profile is a complete carrier-owned bundle (DEC-004 D3):
 * one identity + one address scheme + the endpoint paths; the capability delegates.
 */
class GhtkApiProfileTest extends TestCase
{
    public function testProfileIdentityAndScheme(): void
    {
        $profile = new GhtkApiProfile();

        $this->assertSame('GHTK_2025', $profile->getCode());
        $this->assertSame(VnSchemes::VN_ADMIN_2025, $profile->getAddressScheme());
    }

    public function testEndpointPaths(): void
    {
        $profile = new GhtkApiProfile();

        $this->assertSame('/services/shipment/fee', $profile->getFeePath());
        $this->assertSame('/services/shipment/order', $profile->getOrderPath());
        $this->assertSame('/services/shipment/v2/GHTK123', $profile->getOrderStatusPath('GHTK123'));
    }

    public function testAddressModeIsTextNative(): void
    {
        $profile = new GhtkApiProfile();

        $this->assertSame('TEXT_NATIVE', $profile->getAddressMode());
    }

    public function testOrderStatusPathEncodesTheLabelId(): void
    {
        $profile = new GhtkApiProfile();

        $this->assertSame('/services/shipment/v2/S%2F123', $profile->getOrderStatusPath('S/123'));
    }

    public function testCapabilityDelegatesToTheProfilePerOperation(): void
    {
        $capability = new GhtkOperationAddressCapability(new GhtkApiProfile());

        foreach (ShippingAddressOperation::all() as $operation) {
            // CANDIDATE scheme pending TASK-44F7V7 — single freeze seam in the capability.
            $this->assertSame(VnSchemes::VN_ADMIN_2025, $capability->getRequiredScheme($operation));
            $this->assertSame(['TEXT_NAME'], $capability->getSupportedRepresentations($operation));
            // r1 (DEC-TASK7AJ3K8-002): no guessed address — canonical unresolved = fail closed.
            $this->assertFalse($capability->supportsTextualFallback($operation));
        }
    }
}
