<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Capability;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Capability\GhnAddressCapability;
use Secomm\ShippingCore\Api\Address\AddressRepresentation;
use Secomm\ShippingCore\Api\Address\CarrierOperationAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-FMBBSD (GHN-C slice 2) — per-operation capability matrix: RATE wants the legacy
 * operational address (PRE_2025, UNIT_ID), CREATE the new names (2025, TEXT_NAME — declared
 * only, no CREATE runtime); textual fallback is refused for both (fail closed, SPEC §13).
 */
class GhnAddressCapabilityTest extends TestCase
{
    public function testImplementsPerOperationContract(): void
    {
        $capability = new GhnAddressCapability();

        $this->assertInstanceOf(CarrierOperationAddressCapabilityInterface::class, $capability);
    }

    public function testRateRequiresPre2025Scheme(): void
    {
        $capability = new GhnAddressCapability();

        $this->assertSame(
            VnSchemes::VN_ADMIN_PRE_2025,
            $capability->getRequiredScheme(ShippingAddressOperation::RATE)
        );
    }

    public function testCreateDeclares2025Scheme(): void
    {
        $capability = new GhnAddressCapability();

        $this->assertSame(
            VnSchemes::VN_ADMIN_2025,
            $capability->getRequiredScheme(ShippingAddressOperation::CREATE)
        );
    }

    public function testRateSupportsUnitIdRepresentation(): void
    {
        $capability = new GhnAddressCapability();

        $this->assertSame(
            [AddressRepresentation::UNIT_ID],
            $capability->getSupportedRepresentations(ShippingAddressOperation::RATE)
        );
    }

    public function testCreateSupportsTextNameRepresentation(): void
    {
        $capability = new GhnAddressCapability();

        $this->assertSame(
            [AddressRepresentation::TEXT_NAME],
            $capability->getSupportedRepresentations(ShippingAddressOperation::CREATE)
        );
    }

    public function testTextualFallbackIsDisabledForBothOperations(): void
    {
        $capability = new GhnAddressCapability();

        $this->assertFalse($capability->supportsTextualFallback(ShippingAddressOperation::RATE));
        $this->assertFalse($capability->supportsTextualFallback(ShippingAddressOperation::CREATE));
    }

    public function testUnknownOperationIsRejected(): void
    {
        $capability = new GhnAddressCapability();

        $this->expectException(LocalizedException::class);
        $capability->getRequiredScheme('CANCEL');
    }
}
