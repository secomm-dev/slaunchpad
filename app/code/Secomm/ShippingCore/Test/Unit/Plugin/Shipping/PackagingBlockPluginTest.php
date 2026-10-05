<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Plugin\Shipping;

use Magento\Shipping\Block\Adminhtml\Order\Packaging;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\Physical\ShipmentPhysicalPersister;
use Secomm\ShippingCore\Model\Shipment\FulfillmentMetadataPersister;
use Secomm\ShippingCore\Plugin\Shipping\PackagingBlockPlugin;

/**
 * BUG-74VGQX — the physical-facts marker must never reach the admin packaging display data
 * (packed.phtml / printPackage PDF iterate every entry unguarded), while every other entry
 * shape passes through untouched.
 */
class PackagingBlockPluginTest extends TestCase
{
    public function testMarkerOnlyPackagesAreStrippedToEmpty(): void
    {
        $plugin = new PackagingBlockPlugin();

        $result = $plugin->afterGetPackages(
            $this->createMock(Packaging::class),
            [ShipmentPhysicalPersister::PACKAGES_KEY => [[1500, 30, 20, 10]]]
        );

        $this->assertSame([], $result);
    }

    public function testMixedPackagesKeepNativeEntriesAndDropTheMarker(): void
    {
        $native = [1 => ['params' => ['weight' => 5, 'length' => 30], 'items' => []]];
        $packages = $native + [ShipmentPhysicalPersister::PACKAGES_KEY => [[1500, 30, 20, 10]]];
        $plugin = new PackagingBlockPlugin();

        $result = $plugin->afterGetPackages($this->createMock(Packaging::class), $packages);

        $this->assertSame($native, $result, 'native label-popup entries are untouched');
        $this->assertArrayNotHasKey(ShipmentPhysicalPersister::PACKAGES_KEY, $result);
    }

    public function testPackagesWithoutMarkerPassThroughUnchanged(): void
    {
        $native = [1 => ['params' => ['weight' => 5], 'items' => []]];
        $plugin = new PackagingBlockPlugin();

        $result = $plugin->afterGetPackages($this->createMock(Packaging::class), $native);

        $this->assertSame($native, $result);
    }

    public function testFulfillmentMarkerIsStrippedToo(): void
    {
        // TASK-S52DGA: the fulfillment marker is a second unguarded shape on the packages
        // column — it must never reach the native packaging display data either.
        $native = [1 => ['params' => ['weight' => 5], 'items' => []]];
        $plugin = new PackagingBlockPlugin();

        $result = $plugin->afterGetPackages($this->createMock(Packaging::class), $native + [
            ShipmentPhysicalPersister::PACKAGES_KEY => [[1500, 30, 20, 10]],
            FulfillmentMetadataPersister::PACKAGES_KEY => [FulfillmentMetadataPersister::MODE => 'OFFLINE'],
        ]);

        $this->assertSame($native, $result);
        $this->assertArrayNotHasKey(FulfillmentMetadataPersister::PACKAGES_KEY, $result);
    }

    public function testStripMarkersHelperMatchesThePluginBehavior(): void
    {
        // BUG-DT0C4W — the shared strip helper (used by FormShowPackagesPlugin too).
        $native = [1 => ['params' => ['weight' => 5], 'items' => []]];

        $result = PackagingBlockPlugin::stripMarkers($native + [
            ShipmentPhysicalPersister::PACKAGES_KEY => [[1500, 30, 20, 10]],
            FulfillmentMetadataPersister::PACKAGES_KEY => [FulfillmentMetadataPersister::MODE => 'OFFLINE'],
        ]);

        $this->assertSame($native, $result);
        $this->assertSame([], PackagingBlockPlugin::stripMarkers([
            ShipmentPhysicalPersister::PACKAGES_KEY => [[1500, 30, 20, 10]],
        ]));
    }

    public function testNonArrayResultPassesThroughUntouched(): void
    {
        $plugin = new PackagingBlockPlugin();

        $this->assertNull($plugin->afterGetPackages($this->createMock(Packaging::class), null));
        $this->assertSame('[]', $plugin->afterGetPackages($this->createMock(Packaging::class), '[]'));
    }
}
