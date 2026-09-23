<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Address;

use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Address\GhtkOperationAddressCapability;
use Secomm\Ghtk\Model\GhtkApiProfile;
use Secomm\ShippingCore\Api\Address\AddressRepresentation;
use Secomm\Ghtk\Model\Address\GhtkLegacyCapabilityShim;
use Secomm\ShippingCore\Api\Address\CarrierAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\CarrierOperationAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-6YG3HP — GHTK expresses its address needs PER OPERATION (v5):
 * both RATE and CREATE are primary TEXT_NAME carriers over one candidate scheme,
 * textual fallback never allowed. The deprecated per-carrier surface is a
 * DOCUMENTED shim (ShippingCore scalar builder still type-hints it).
 */
class GhtkOperationAddressCapabilityTest extends TestCase
{
    private GhtkOperationAddressCapability $capability;

    protected function setUp(): void
    {
        $this->capability = new GhtkOperationAddressCapability(new GhtkApiProfile());
    }

    public function testImplementsThePerOperationContract(): void
    {
        $this->assertInstanceOf(CarrierOperationAddressCapabilityInterface::class, $this->capability);
    }

    public function testLegacyShimImplementsTheDeprecatedContractAndDelegatesToRate(): void
    {
        // The ONLY remaining reference to the deprecated per-carrier contract:
        // a dedicated shim for the ShippingCore scalar builder (removal documented
        // on GhtkLegacyCapabilityShim).
        $shim = new GhtkLegacyCapabilityShim($this->capability);
        $this->assertInstanceOf(CarrierAddressCapabilityInterface::class, $shim);
        $this->assertSame(
            $this->capability->getRequiredScheme(ShippingAddressOperation::RATE),
            $shim->getRequiredScheme()
        );
        $this->assertSame(
            $this->capability->supportsTextualFallback(ShippingAddressOperation::RATE),
            $shim->supportsTextualFallback()
        );
    }

    public function testRateAndCreateAdvertiseTextNameOnly(): void
    {
        foreach (ShippingAddressOperation::all() as $operation) {
            $this->assertSame(
                [AddressRepresentation::TEXT_NAME],
                $this->capability->getSupportedRepresentations($operation),
                "Operation {$operation} must advertise TEXT_NAME only — GHTK has no carrier IDs"
            );
        }
    }

    public function testPerOperationSchemeIsTheProfileCandidate(): void
    {
        foreach (ShippingAddressOperation::all() as $operation) {
            $this->assertSame(
                VnSchemes::VN_ADMIN_2025,
                $this->capability->getRequiredScheme($operation),
                "Operation {$operation}: CANDIDATE scheme pending TASK-44F7V7 — single freeze seam"
            );
        }
    }

    public function testTextualFallbackIsNeverAllowed(): void
    {
        foreach (ShippingAddressOperation::all() as $operation) {
            $this->assertFalse($this->capability->supportsTextualFallback($operation));
        }
    }

    public function testUnknownOperationIsRejected(): void
    {
        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->capability->getRequiredScheme('TRACK');
    }


}
