<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Shipment;

use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Shipment\FulfillmentMode;
use Secomm\ShippingCore\Model\Physical\ShipmentPhysicalPersister;
use Secomm\ShippingCore\Model\Shipment\FulfillmentMetadataPersister;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — the fulfillment marker lives ON the native packages
 * column under `secomm_fulfillment`: MERGE semantics (the secomm_physical snapshot and native
 * entries survive), OFFLINE-only records, and read-marker-then-write idempotency.
 */
class FulfillmentMetadataPersisterTest extends TestCase
{
    private ShipmentRepositoryInterface&MockObject $shipmentRepository;

    private ShipmentInterface&MockObject $shipment;

    private FulfillmentMetadataPersister $persister;

    protected function setUp(): void
    {
        $this->shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $this->shipment = $this->createMock(ShipmentInterface::class);
        $this->persister = new FulfillmentMetadataPersister($this->shipmentRepository);
    }

    public function testReadReturnsNullWithoutMarker(): void
    {
        $this->shipment->method('getPackages')->willReturn([1 => ['params' => []]]);

        self::assertNull($this->persister->read($this->shipment));
    }

    public function testReadReturnsNullForMalformedOrNonOfflineMarker(): void
    {
        $this->shipment->method('getPackages')->willReturn([FulfillmentMetadataPersister::PACKAGES_KEY => 'garbage']);
        self::assertNull($this->persister->read($this->shipment));

        $this->shipment->method('getPackages')->willReturn([
            FulfillmentMetadataPersister::PACKAGES_KEY => [FulfillmentMetadataPersister::MODE => 'ONLINE'],
        ]);
        self::assertNull($this->persister->read($this->shipment));
    }

    public function testReadReturnsTheOfflineMarker(): void
    {
        $marker = [FulfillmentMetadataPersister::MODE => FulfillmentMode::OFFLINE, 'anything' => 'x'];
        $this->shipment->method('getPackages')->willReturn([FulfillmentMetadataPersister::PACKAGES_KEY => $marker]);

        self::assertSame($marker, $this->persister->read($this->shipment));
    }

    public function testPersistWritesTheMarkerAndPreservesEveryOtherEntry(): void
    {
        $native = [1 => ['params' => ['weight' => 5], 'items' => []]];
        $physical = [ShipmentPhysicalPersister::PACKAGES_KEY => [[1500, 30, 20, 10]]];
        $this->shipment->method('getPackages')->willReturn($native + $physical);
        $this->shipment->expects($this->once())->method('setPackages')->with($this->callback(
            function (array $packages): bool {
                return isset($packages[1], $packages[ShipmentPhysicalPersister::PACKAGES_KEY])
                    && ($packages[FulfillmentMetadataPersister::PACKAGES_KEY]
                        [FulfillmentMetadataPersister::MODE] ?? '') === FulfillmentMode::OFFLINE;
            }
        ));
        $this->shipmentRepository->expects($this->once())->method('save')->with($this->shipment);

        $this->persister->persist($this->shipment, [
            FulfillmentMetadataPersister::MODE => FulfillmentMode::OFFLINE,
            FulfillmentMetadataPersister::INTENDED_CARRIER => 'secomm_ghn',
        ]);
    }

    public function testPersistIsIdempotentForAnAlreadyOfflineShipment(): void
    {
        $this->shipment->method('getPackages')->willReturn([
            FulfillmentMetadataPersister::PACKAGES_KEY => [FulfillmentMetadataPersister::MODE => FulfillmentMode::OFFLINE],
        ]);
        $this->shipmentRepository->expects($this->never())->method('save');
        $this->shipment->expects($this->never())->method('setPackages');

        $this->persister->persist($this->shipment, [FulfillmentMetadataPersister::MODE => FulfillmentMode::OFFLINE]);
    }
}
