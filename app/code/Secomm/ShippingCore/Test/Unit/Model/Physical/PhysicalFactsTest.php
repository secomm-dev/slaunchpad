<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Physical;

use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\Physical\PhysicalPackage;
use Secomm\ShippingCore\Model\Physical\ShipmentPhysicalData;
use Secomm\ShippingCore\Model\Physical\ShipmentPhysicalPersister;

/**
 * DEC-TASK9Q5ZAK-001 — the carrier-neutral physical-facts VOs and the sales_shipment.packages
 * persistence (marker `secomm_physical`): facts only — no carrier semantics ever enter here.
 */
class PhysicalFactsTest extends TestCase
{
    public function testPhysicalPackageRejectsNonPositiveFacts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PhysicalPackage(0, 10, 10, 10);
    }

    public function testShipmentPhysicalDataRejectsContradictingTotal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ShipmentPhysicalData([new PhysicalPackage(1000, 10, 10, 10)], 2000);
    }

    public function testShipmentPhysicalDataRejectsEmptyPackages(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ShipmentPhysicalData::fromPackages([]);
    }

    public function testFromPackagesComputesTheFactualTotal(): void
    {
        $data = ShipmentPhysicalData::fromPackages([
            new PhysicalPackage(45000, 60, 50, 40),
            new PhysicalPackage(1000, 10, 10, 10),
        ]);

        $this->assertSame(46000, $data->getTotalWeightG());
        $this->assertCount(2, $data->getPackages());
    }

    public function testPersisterWritesMarkerShapeAndReadsItBack(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $captured = null;
        $shipment->method('setPackages')->willReturnCallback(
            function (array $packages) use ($shipment, &$captured): ShipmentInterface {
                $captured = ['packages' => $packages];

                return $shipment;
            }
        );
        $shipment->method('getPackages')->willReturnCallback(
            function () use (&$captured): ?array {
                return $captured['packages'] ?? null;
            }
        );
        /** @var ShipmentRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->expects($this->once())->method('save')->with($shipment);
        $persister = new ShipmentPhysicalPersister($repository);

        $physical = ShipmentPhysicalData::fromPackages([new PhysicalPackage(1500, 30, 20, 10)]);
        $persister->persist($shipment, $physical);

        $packages = $captured['packages'];
        $this->assertSame([[1500, 30, 20, 10]], $packages[ShipmentPhysicalPersister::PACKAGES_KEY]);

        $readBack = $persister->read($shipment);
        $this->assertNotNull($readBack);
        $this->assertSame(1500, $readBack->getTotalWeightG());
        $this->assertSame(30, $readBack->getPackages()[0]->getLengthCm());
    }

    public function testPersistMergesPreservingNonMarkerEntries(): void
    {
        // Forward-compat (r3 audit): a future native label-popup shape (numeric entries) and the
        // Secomm marker coexist in one column — persisting updates ONLY the marker.
        $existing = [1 => ['params' => ['weight' => 5]]];
        $shipment = $this->createMock(ShipmentInterface::class);
        $captured = null;
        $shipment->method('getPackages')->willReturn($existing);
        $shipment->method('setPackages')->willReturnCallback(
            function (array $packages) use (&$captured): void {
                $captured = $packages;
            }
        );
        /** @var ShipmentRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(ShipmentRepositoryInterface::class);
        $repository->expects($this->once())->method('save')->with($shipment);
        $persister = new ShipmentPhysicalPersister($repository);

        $physical = ShipmentPhysicalData::fromPackages([new PhysicalPackage(1500, 30, 20, 10)]);
        $persister->persist($shipment, $physical);

        $this->assertSame([[1500, 30, 20, 10]], $captured[ShipmentPhysicalPersister::PACKAGES_KEY]);
        $this->assertSame(['params' => ['weight' => 5]], $captured[1], 'non-marker entries are preserved');
    }

    public function testReaderIgnoresForeignPackageShapes(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        // the label-popup shape (numeric keys + params) must NOT be read as Secomm facts
        $shipment->method('getPackages')->willReturn([1 => ['params' => ['weight' => 5, 'length' => 30]]]);

        $persister = new ShipmentPhysicalPersister($this->createMock(ShipmentRepositoryInterface::class));

        $this->assertNull($persister->read($shipment));
    }

    public function testReaderTreatsMalformedMarkerRowsAsAbsent(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getPackages')->willReturn([ShipmentPhysicalPersister::PACKAGES_KEY => [[1500, 30]]]);

        $persister = new ShipmentPhysicalPersister($this->createMock(ShipmentRepositoryInterface::class));

        $this->assertNull($persister->read($shipment));
    }
}
