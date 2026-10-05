<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Shipment;

use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Carrier\Ghn;
use Secomm\Ghn\Model\Shipment\GhnOfflineCapability;
use Secomm\ShippingCore\Api\Shipment\CarrierOfflineCapabilityInterface;

/**
 * TASK-S52DGA — GHN's opt-in to the generic offline shipment flow: identity only, no limits
 * and no provider values cross this boundary (ShippingCore sees the carrier code, nothing else).
 */
class GhnOfflineCapabilityTest extends TestCase
{
    public function testImplementsTheGenericContract(): void
    {
        $capability = new GhnOfflineCapability();

        self::assertInstanceOf(CarrierOfflineCapabilityInterface::class, $capability);
    }

    public function testCarrierCodeIsTheRawMethodPrefix(): void
    {
        $capability = new GhnOfflineCapability();

        self::assertSame(Ghn::CARRIER_CODE, $capability->getCarrierCode());
        self::assertStringStartsWith($capability->getCarrierCode() . '_', 'secomm_ghn_secomm_ghn');
    }

    public function testOfflineCreationIsEnabled(): void
    {
        $capability = new GhnOfflineCapability();

        self::assertTrue($capability->isOfflineCreationEnabled());
    }
}
