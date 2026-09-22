<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Block\Adminhtml\Shipment\View;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Framework\App\RequestInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Block\Adminhtml\Shipment\View\Actions;
use Secomm\Ghn\Model\Shipment\GhnShipmentRepository;
use Secomm\ShippingCore\Model\ResourceModel\CarrierTrackingState\CollectionFactory as StateCollectionFactory;
use Secomm\ShippingCore\Model\ResourceModel\CarrierTrackingState\Collection;

/**
 * TASK-PWHG0V (GHN-E3-B) — the LOCAL-state visibility matrix (brief §12/§16): actions show only
 * for GHN shipments with a provider order; Cancel hides on terminal/late states, Return hides on
 * CANCELLED/RETURNED. The provider remains the final authority — this is UI convenience only.
 */
class ActionsTest extends TestCase
{
    private Registry&MockObject $registry;

    private GhnShipmentRepository&MockObject $repository;

    private StateCollectionFactory&MockObject $stateFactory;

    private ?array $providerRow = ['provider_status' => 'SUBMITTED', 'ghn_order_code' => 'L8TAKR'];

    private ?string $normalizedStatus = 'OUT_FOR_DELIVERY';

    protected function setUp(): void
    {
        $this->registry = $this->createMock(Registry::class);
        $this->repository = $this->createMock(GhnShipmentRepository::class);
        $stateFactory = $this->createMock(StateCollectionFactory::class);
        $stateCollection = $this->createMock(Collection::class);
        $stateCollection->method('addFieldToFilter')->willReturnSelf();
        $stateCollection->method('getFirstItem')->willReturnCallback(
            fn () => new \Magento\Framework\DataObject(['normalized_status' => $this->normalizedStatus])
        );
        $stateFactory->method('create')->willReturn($stateCollection);
        $this->stateFactory = $stateFactory;

        $rowRef = &$this->providerRow;
        $this->repository->method('findByShipmentId')->willReturnCallback(
            function () use (&$rowRef) {
                return $rowRef;
            }
        );
    }

    public function testNonGhnShipmentHidesAllActions(): void
    {
        $this->givenShipment('flatrate_flatrate', 30);
        $this->assertFalse($this->block()->canShowActions());
    }

    public function testMissingProviderRowHidesAllActions(): void
    {
        $this->givenShipment('secomm_ghn_secomm_ghn', 31);
        $this->providerRow = null;
        $this->assertFalse($this->block()->canShowActions());
    }

    /**
     * @dataProvider visibilityMatrixProvider
     */
    public function testVisibilityMatrix(string $state, bool $canCancel, bool $canReturn): void
    {
        $this->givenShipment('secomm_ghn_secomm_ghn', 31);
        $this->normalizedStatus = $state;

        $block = $this->block();
        $this->assertSame($canCancel, $block->canCancel(), "cancel @ {$state}");
        $this->assertSame($canReturn, $block->canReturn(), "return @ {$state}");
    }

    public static function visibilityMatrixProvider(): array
    {
        return [
            'live transporting' => ['OUT_FOR_DELIVERY', true, true],
            'in transit' => ['IN_TRANSIT', true, true],
            'delivery failed (retrying)' => ['DELIVERY_FAILED', true, true],
            'delivered' => ['DELIVERED', false, true],
            'returned' => ['RETURNED', false, false],
            'cancelled' => ['CANCELLED', false, false],
            'lost' => ['LOST', false, true],
            'damaged' => ['DAMAGED', false, true],
            'never tracked' => ['', true, true],
        ];
    }

    public function testCancelReasonsExposeMerchantLabelsNotCodesOnly(): void
    {
        $this->givenShipment('secomm_ghn_secomm_ghn', 31);
        $reasons = $this->block()->getCancelReasons();

        $this->assertSame(['GHN-CO001', 'GHN-CO002', 'GHN-CO003', 'GHN-CANCEL-OTHER'], array_keys($reasons));
        foreach ($reasons as $label) {
            $this->assertNotSame('', trim($label));
        }
    }

    public function testActionUrlsUseShipmentIdOnlyNeverProviderIdentity(): void
    {
        $this->givenShipment('secomm_ghn_secomm_ghn', 31);

        $block = $this->block();
        $this->assertStringContainsString('shipment_id/31', $block->getCancelUrl());
        $this->assertStringContainsString('secomm_ghn/shipment/cancel', $block->getCancelUrl());
        $this->assertStringContainsString('secomm_ghn/shipment/returnShipment', $block->getReturnUrl());
    }

    private function givenShipment(string $shippingMethod, int $shipmentId): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getShippingMethod')->willReturn($shippingMethod);
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getEntityId')->willReturn($shipmentId);
        $shipment->method('getOrder')->willReturn($order);
        $this->registry->method('registry')->with('current_shipment')->willReturn($shipment);
    }

    private function block(): Actions
    {
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            fn (string $path, array $params = []) => 'https://admin.example/' . $path . '/' .
                implode('/', array_map(fn ($k, $v) => $k . '/' . $v, array_keys($params), $params))
        );
        $request = $this->createMock(RequestInterface::class);
        $context = $this->createMock(Context::class);
        $context->method('getUrlBuilder')->willReturn($url);
        $context->method('getRequest')->willReturn($request);
        // Backend\Template base ctor requirements (kept OM-free for unit tests).
        $context->method('getAuthorization')->willReturn($this->createMock(\Magento\Framework\AuthorizationInterface::class));
        $context->method('getLocaleDate')->willReturn($this->createMock(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class));
        $context->method('getMathRandom')->willReturn($this->createMock(\Magento\Framework\Math\Random::class));
        $context->method('getBackendSession')->willReturn($this->createMock(\Magento\Backend\Model\Session::class));
        $context->method('getFormKey')->willReturn($this->createMock(\Magento\Framework\Data\Form\FormKey::class));
        $context->method('getNameBuilder')->willReturn($this->createMock(\Magento\Framework\Code\NameBuilder::class));
        $context->method('getEscaper')->willReturn($this->createMock(\Magento\Framework\Escaper::class));

        return new Actions(
            $context,
            $this->registry,
            $this->repository,
            $this->stateFactory,
            [],
            $this->createMock(\Magento\Framework\Json\Helper\Data::class),
            $this->createMock(\Magento\Directory\Helper\Data::class)
        );
    }
}
