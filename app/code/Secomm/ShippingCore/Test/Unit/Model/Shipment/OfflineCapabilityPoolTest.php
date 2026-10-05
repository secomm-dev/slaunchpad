<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Shipment;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Shipment\CarrierOfflineCapabilityInterface;
use Secomm\ShippingCore\Model\Shipment\OfflineCapabilityPool;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — capability registry: zero capabilities is valid; matching
 * is a RAW shipping-method prefix match (never Order::getShippingMethod(true)); disabled
 * capabilities are indistinguishable from absent ones.
 */
class OfflineCapabilityPoolTest extends TestCase
{
    public function testEmptyPoolMatchesNothing(): void
    {
        $pool = new OfflineCapabilityPool([]);

        self::assertNull($pool->findForShippingMethod('secomm_ghn_secomm_ghn'));
        self::assertSame([], $pool->getEnabled());
    }

    public function testRawMethodPrefixMatch(): void
    {
        $pool = new OfflineCapabilityPool([$this->capability('secomm_ghn')]);

        self::assertNotNull($pool->findForShippingMethod('secomm_ghn_secomm_ghn'));
    }

    public function testUnrelatedMethodDoesNotMatch(): void
    {
        $pool = new OfflineCapabilityPool([$this->capability('secomm_ghn')]);

        self::assertNull($pool->findForShippingMethod('flatrate_flatrate'));
        self::assertNull($pool->findForShippingMethod(''));
    }

    public function testDisabledCapabilityIsFiltered(): void
    {
        $pool = new OfflineCapabilityPool([$this->capability('secomm_ghn', enabled: false)]);

        self::assertNull($pool->findForShippingMethod('secomm_ghn_secomm_ghn'));
        self::assertSame([], $pool->getEnabled());
    }

    public function testGetEnabledReturnsOnlyEnabledCapabilities(): void
    {
        $enabled = $this->capability('secomm_ghn');
        $pool = new OfflineCapabilityPool([$this->capability('disabled_carrier', enabled: false), $enabled]);

        self::assertSame([$enabled], $pool->getEnabled());
    }

    private function capability(string $carrierCode, bool $enabled = true): CarrierOfflineCapabilityInterface
    {
        $capability = $this->createMock(CarrierOfflineCapabilityInterface::class);
        $capability->method('getCarrierCode')->willReturn($carrierCode);
        $capability->method('isOfflineCreationEnabled')->willReturn($enabled);

        return $capability;
    }
}
