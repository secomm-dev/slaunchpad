<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Shipment;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Sales\Api\Data\ShipmentInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Shipment\CarrierOfflineCapabilityInterface;
use Secomm\ShippingCore\Api\Shipment\FulfillmentMode;
use Secomm\ShippingCore\Model\Shipment\FulfillmentMetadataPersister;
use Secomm\ShippingCore\Model\Shipment\FulfillmentModeResolver;
use Secomm\ShippingCore\Model\Shipment\OfflineCapabilityPool;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — the single fulfillment-mode read seam: request intent is
 * STRICT (only the exact "OFFLINE" value counts), persisted metadata defaults to ONLINE, and
 * carrier resolution is a raw-prefix capability match.
 */
class FulfillmentModeResolverTest extends TestCase
{
    private HttpRequest&MockObject $request;

    private FulfillmentMetadataPersister&MockObject $metadataPersister;

    private OfflineCapabilityPool&MockObject $capabilityPool;

    private FulfillmentModeResolver $resolver;

    protected function setUp(): void
    {
        $this->request = $this->createMock(HttpRequest::class);
        $this->metadataPersister = $this->createMock(FulfillmentMetadataPersister::class);
        $this->capabilityPool = $this->createMock(OfflineCapabilityPool::class);
        $this->resolver = new FulfillmentModeResolver($this->request, $this->metadataPersister, $this->capabilityPool);
    }

    // ---------- request intent ----------

    public function testExactOfflineValueIsIntent(): void
    {
        $this->givenPostedShipmentParam(['fulfillment_mode' => FulfillmentMode::OFFLINE]);

        self::assertTrue($this->resolver->isOfflineIntent());
    }

    public function testAnythingElseIsOnline(): void
    {
        $this->givenPostedShipmentParam(['fulfillment_mode' => 'ONLINE']);
        self::assertFalse($this->resolver->isOfflineIntent());

        $this->givenPostedShipmentParam(['fulfillment_mode' => 'offline']); // strict — no case folding
        self::assertFalse($this->resolver->isOfflineIntent());

        $this->givenPostedShipmentParam([]);
        self::assertFalse($this->resolver->isOfflineIntent());

        $this->request->method('getParam')->willReturn(null); // no shipment[] at all
        self::assertFalse($this->resolver->isOfflineIntent());

        $this->request->method('getParam')->willReturn('not-an-array');
        self::assertFalse($this->resolver->isOfflineIntent());
    }

    // ---------- persisted mode ----------

    public function testShipmentWithoutMetadataRowIsOnline(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $this->metadataPersister->method('read')->with($shipment)->willReturn(null);

        self::assertSame(FulfillmentMode::ONLINE, $this->resolver->forShipment($shipment));
    }

    public function testShipmentWithMetadataRowIsOffline(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $this->metadataPersister->method('read')->with($shipment)->willReturn(
            [FulfillmentMetadataPersister::MODE => FulfillmentMode::OFFLINE]
        );

        self::assertSame(FulfillmentMode::OFFLINE, $this->resolver->forShipment($shipment));
    }

    // ---------- carrier resolution ----------

    public function testCarrierResolutionDelegatesToThePoolPrefixMatch(): void
    {
        $capability = $this->createMock(CarrierOfflineCapabilityInterface::class);
        $capability->method('getCarrierCode')->willReturn('secomm_ghn');
        $this->capabilityPool->method('findForShippingMethod')
            ->with('secomm_ghn_secomm_ghn')
            ->willReturn($capability);

        self::assertSame('secomm_ghn', $this->resolver->resolveCarrierCode('secomm_ghn_secomm_ghn'));
    }

    public function testEmptyMethodResolvesToNothing(): void
    {
        self::assertNull($this->resolver->resolveCarrierCode(null));
        self::assertNull($this->resolver->resolveCarrierCode(''));
    }

    private function givenPostedShipmentParam(array $value): void
    {
        $this->request = $this->createMock(HttpRequest::class);
        $this->request->method('getParam')->with('shipment')->willReturn($value);
        $this->resolver = new FulfillmentModeResolver($this->request, $this->metadataPersister, $this->capabilityPool);
    }
}
