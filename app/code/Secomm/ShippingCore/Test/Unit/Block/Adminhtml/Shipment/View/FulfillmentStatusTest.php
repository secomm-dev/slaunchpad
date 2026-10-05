<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Block\Adminhtml\Shipment\View;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Block\Adminhtml\Shipment\View\FulfillmentStatus;
use Secomm\ShippingCore\Model\Shipment\FulfillmentMetadataPersister;

class FulfillmentStatusTest extends TestCase
{
    private Registry&MockObject $registry;

    private FulfillmentMetadataPersister&MockObject $metadataPersister;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(Registry::class);
        $this->metadataPersister = $this->createMock(FulfillmentMetadataPersister::class);
    }

    private function block(): FulfillmentStatus
    {
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            fn (string $path, array $params = []) => 'https://admin.example/' . $path
        );
        $context = $this->createMock(Context::class);
        $context->method('getUrlBuilder')->willReturn($url);
        $context->method('getRequest')->willReturn($this->createMock(RequestInterface::class));
        $context->method('getAuthorization')->willReturn($this->createMock(\Magento\Framework\AuthorizationInterface::class));
        $context->method('getLocaleDate')->willReturn($this->createMock(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class));
        $context->method('getMathRandom')->willReturn($this->createMock(\Magento\Framework\Math\Random::class));
        $context->method('getBackendSession')->willReturn($this->createMock(\Magento\Backend\Model\Session::class));
        $context->method('getFormKey')->willReturn($this->createMock(\Magento\Framework\Data\Form\FormKey::class));
        $context->method('getNameBuilder')->willReturn($this->createMock(\Magento\Framework\Code\NameBuilder::class));
        $context->method('getEscaper')->willReturn($this->createMock(\Magento\Framework\Escaper::class));

        return new FulfillmentStatus(
            $context,
            $this->registry,
            $this->metadataPersister,
            [],
            $this->createMock(\Magento\Framework\Json\Helper\Data::class),
            $this->createMock(\Magento\Directory\Helper\Data::class)
        );
    }

    public function testHiddenForLegacyAndOnlineShipments(): void
    {
        $this->registry->method('registry')->willReturn(null);
        self::assertFalse($this->block()->canShow());

        $this->registry->method('registry')->willReturn($this->shipment('secomm_ghn_secomm_ghn', 7));
        $this->metadataPersister->method('read')->willReturn(null);
        self::assertFalse($this->block()->canShow());
    }

    public function testShowsOfflineFieldsForAMarkedShipment(): void
    {
        $this->registry->method('registry')->willReturn($this->shipment('secomm_ghn_secomm_ghn', 7));
        $this->metadataPersister->method('read')->willReturn([
            FulfillmentMetadataPersister::MODE => 'OFFLINE',
            FulfillmentMetadataPersister::INTENDED_CARRIER => 'secomm_ghn',
            FulfillmentMetadataPersister::REASON_CODE => 'INVALID_PARCEL',
            FulfillmentMetadataPersister::REASON_MESSAGE => 'package #1 length is 300 cm',
            FulfillmentMetadataPersister::NOTE => 'Booked manually',
        ]);
        $block = $this->block();

        self::assertTrue($block->canShow());
        self::assertTrue($block->isOffline());
        self::assertSame('secomm_ghn', $block->getIntendedCarrier());
        self::assertSame('INVALID_PARCEL', $block->getReasonCode());
        self::assertSame('package #1 length is 300 cm', $block->getReasonMessage());
        self::assertSame('Booked manually', $block->getNote());
    }

    private function shipment(string $shippingMethod, int $entityId): Shipment&MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('getShippingMethod')->willReturn($shippingMethod);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getEntityId')->willReturn($entityId);
        $shipment->method('getOrder')->willReturn($order);

        return $shipment;
    }
}
