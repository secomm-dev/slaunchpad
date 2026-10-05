<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\ViewModel\Shipment;

use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\Shipment\FulfillmentModeResolver;
use Secomm\ShippingCore\Model\Shipment\OfflineEligibilitySession;
use Secomm\ShippingCore\ViewModel\Shipment\OfflineControl;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — capability-driven gating for the "Create Offline Shipment"
 * control: renders only when the order's carrier has an ENABLED
 * CarrierOfflineCapabilityInterface implementation (carrier identities are never named in
 * ShippingCore — the mocked resolver supplies them). The stash pull CLEARS, so a later form
 * render never shows stale reasons.
 */
class OfflineControlTest extends TestCase
{
    private Registry&MockObject $registry;

    private FulfillmentModeResolver&MockObject $resolver;

    private OfflineEligibilitySession&MockObject $eligibilitySession;

    private OfflineControl $sut;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(Registry::class);
        $this->resolver = $this->createMock(FulfillmentModeResolver::class);
        $this->eligibilitySession = $this->createMock(OfflineEligibilitySession::class);
        $this->sut = new OfflineControl($this->registry, $this->resolver, $this->eligibilitySession);
    }

    public function testCanShowRequiresAShipment(): void
    {
        $this->registry->method('registry')->willReturn(null);

        self::assertFalse($this->sut->canShow());
        self::assertNull($this->sut->pullStashedEligibility());
    }

    public function testCanShowRequiresAnEnabledCapability(): void
    {
        $this->registry->method('registry')->willReturn($this->shipment('flatrate_flatrate', 21));
        $this->resolver->method('resolveCarrierCode')->willReturn(null);

        self::assertFalse($this->sut->canShow());
    }

    public function testCapableCarrierShowsTheControl(): void
    {
        $this->registry->method('registry')->willReturn($this->shipment('secomm_ghn_secomm_ghn', 21));
        $this->resolver->method('resolveCarrierCode')->willReturn('secomm_ghn');

        self::assertTrue($this->sut->canShow());
    }

    public function testPullStashedEligibilityReturnsAndClearsTheStash(): void
    {
        $this->registry->method('registry')->willReturn($this->shipment('secomm_ghn_secomm_ghn', 21));
        $this->eligibilitySession->expects($this->once())->method('pull')->with(21)->willReturn(
            ['reason_code' => 'INVALID_PARCEL', 'message' => 'package #1 is above the limit']
        );

        self::assertSame(
            ['reason_code' => 'INVALID_PARCEL', 'message' => 'package #1 is above the limit'],
            $this->sut->pullStashedEligibility()
        );
    }

    /**
     * @param string $shippingMethod
     * @param int $orderId
     */
    private function shipment(string $shippingMethod, int $orderId): Shipment&MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('getShippingMethod')->willReturn($shippingMethod);
        $order->method('getEntityId')->willReturn($orderId);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getOrderId')->willReturn($orderId);
        $shipment->method('getOrder')->willReturn($order);

        return $shipment;
    }
}
