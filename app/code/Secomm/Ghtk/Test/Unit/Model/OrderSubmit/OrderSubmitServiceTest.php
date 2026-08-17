<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\OrderSubmit;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Collection as ShipmentCollection;
use Magento\Shipping\Model\Shipment\Request;
use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Address\DestinationAddressResolver;
use Secomm\Ghtk\Model\Address\GhtkAddress;
use Secomm\Ghtk\Model\Address\PickupAddress;
use Secomm\Ghtk\Model\Address\PickupAddressResolver;
use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\Config\Source\WeightUnit;
use Secomm\Ghtk\Model\GhtkApiClient;
use Secomm\Ghtk\Model\GhtkApiException;
use Secomm\Ghtk\Model\Log\MaskingLogger;
use Secomm\Ghtk\Model\OrderSubmit\CodAmountResolverInterface;
use Secomm\Ghtk\Model\OrderSubmit\OrderRequestMapper;
use Secomm\Ghtk\Model\OrderSubmit\OrderResponseMapper;
use Secomm\Ghtk\Model\OrderSubmit\OrderSubmitService;
use Secomm\Ghtk\Model\Origin\GhtkOriginProvider;
use Secomm\Ghtk\Model\Shipment\ShipmentWeightCalculator;
use Secomm\ShippingCore\Model\Origin;
use Secomm\ShippingCore\Model\ShippingContextFactory;

/**
 * SL-016 §10: origin through the provider chain (custom provider wins), COD
 * mapping (prepaid 0 / COD resolved), tracking persistence (comment
 * snapshot), API failure → LocalizedException (native flow aborts shipment).
 */
class OrderSubmitServiceTest extends TestCase
{
    private GhtkOriginProvider $originProvider;
    private PickupAddressResolver $pickupResolver;
    private DestinationAddressResolver $destResolver;
    private GhtkApiClient $apiClient;
    private MaskingLogger $logger;

    protected function setUp(): void
    {
        // Custom "fulfillment" provider stand-in — origin with carrier metadata.
        $origin = new Origin(
            sourceCode: 'hcm-wh-1',
            countryId: 'VN',
            regionId: null,
            province: 'Hồ Chí Minh',
            district: null,
            ward: 'Phường Bến Nghé',
            street: null,
            postcode: null,
            telephone: null,
            contactName: null,
            metadata: ['ghtk.pick_address_id' => 'src-paid-42']
        );
        $provider = $this->createMock(GhtkOriginProvider::class);
        $provider->method('resolve')->willReturn($origin);
        $this->originProvider = $provider;

        $this->pickupResolver = new PickupAddressResolver($this->createMock(DestinationAddressResolver::class));

        $destResolver = $this->createMock(DestinationAddressResolver::class);
        $destResolver->method('resolve')->willReturn(new GhtkAddress('Hà Nội', 'Hoàn Kiếm', 'Phường Hàng Trống', true));
        $this->destResolver = $destResolver;

        $this->apiClient = $this->createMock(GhtkApiClient::class);
        $this->logger = $this->createMock(MaskingLogger::class);
    }

    private function service(float $codAmount, Shipment $shipment): OrderSubmitService
    {
        $codResolver = $this->createMock(CodAmountResolverInterface::class);
        $codResolver->method('resolve')->willReturn($codAmount);

        $config = $this->createMock(GhtkConfig::class);
        $config->method('getTransport')->willReturn('road');
        $config->method('getWeightUnit')->willReturn(WeightUnit::KILOGRAM);
        $config->method('getMinWeight')->willReturn(0.1);

        return new OrderSubmitService(
            $this->originProvider,
            $this->pickupResolver,
            $this->destResolver,
            $codResolver,
            new OrderRequestMapper(),
            new OrderResponseMapper(),
            $this->apiClient,
            $this->createMock(ShipmentWeightCalculator::class),
            new ShippingContextFactory(),
            $config,
            $this->logger
        );
    }

    private function request(Shipment $shipment, array $packages = [['params' => ['weight' => 1.5]]]): Request
    {
        $request = new Request();
        $request->setOrderShipment($shipment);
        $request->setPackages($packages);
        $request->setShipperContactPersonName('Admin User');
        $request->setShipperContactPhoneNumber('0901234567');
        $request->setShipperAddressStreet('25 Ly Thuong Kiet');
        $request->setRecipientContactPersonName('Tran Thi Buyer');
        $request->setRecipientContactPhoneNumber('0987654321');
        $request->setRecipientAddressStreet('12 Hang Bac');

        return $request;
    }

    private function shipment(): Shipment
    {
        $address = $this->createMock(Address::class);
        $address->method('getCountryId')->willReturn('VN');
        $address->method('getRegionId')->willReturn(467);
        $address->method('getCity')->willReturn('Phường Hàng Trống');

        $shipments = $this->createMock(ShipmentCollection::class);
        $shipments->method('getSize')->willReturn(0); // first shipment → seq 1

        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getIncrementId')->willReturn('100000001');
        $order->method('getShippingAddress')->willReturn($address);
        $order->method('getShipmentsCollection')->willReturn($shipments);

        $item = $this->createMock(Shipment\Item::class);
        $item->method('getName')->willReturn('Ao thun');
        $item->method('getQty')->willReturn(2);
        $item->method('getPrice')->willReturn(250000.0);
        $item->method('getOrderItem')->willReturn(null);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getOrder')->willReturn($order);
        $shipment->method('getStoreId')->willReturn(1);
        $shipment->method('getAllItems')->willReturn([$item]);

        return $shipment;
    }

    public function testSuccessSubmitsWithProviderOriginAndPersistedSnapshot(): void
    {
        $shipment = $this->shipment();
        $shipment->expects($this->once())
            ->method('addComment')
            ->with($this->callback(function (string $comment) {
                return str_contains($comment, 'ghtk-100000001-1')
                    && str_contains($comment, 'label: S10001.P1')
                    && str_contains($comment, 'pick_money: 0') // prepaid
                    && str_contains($comment, 'weight (g): 1500'); // 1.5 kg package weight
            }), false, false);

        $this->apiClient->expects($this->once())
            ->method('submitOrder')
            ->with(
                $this->callback(function (array $payload) {
                    // Origin came through the (custom) provider — metadata wins.
                    return $payload['pick_address_id'] === 'src-paid-42'
                        && $payload['pick_money'] === 0
                        && $payload['is_freeship'] === 1
                        && $payload['partner_order_id'] === 'ghtk-100000001-1'
                        && $payload['weight'] === 1500;
                }),
                1
            )
            ->willReturn([
                'success' => true,
                'order' => ['label' => 'S10001.P1', 'tracking_code' => 'S10001.P1.XXXX'],
            ]);

        $result = $this->service(0.0, $shipment)->submit($this->request($shipment));

        $this->assertSame('S10001.P1', $result->labelId);
        $this->assertSame('S10001.P1.XXXX', $result->trackingNumber);
    }

    public function testGhtkRejectionAborts(): void
    {
        $shipment = $this->shipment();
        $this->apiClient->method('submitOrder')->willReturn(['success' => false, 'message' => 'Địa chỉ không hỗ trợ']);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('GHTK rejected the order');
        $this->service(0.0, $shipment)->submit($this->request($shipment));
    }

    public function testTransportFailureAborts(): void
    {
        $shipment = $this->shipment();
        $this->apiClient->method('submitOrder')
            ->willThrowException(new GhtkApiException('GHTK order request failed (status 500).', true));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('GHTK order submission failed');
        $this->service(0.0, $shipment)->submit($this->request($shipment));
    }

    public function testMissingLabelIdentifierAborts(): void
    {
        $shipment = $this->shipment();
        $this->apiClient->method('submitOrder')->willReturn(['success' => true, 'order' => []]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('no label/tracking');
        $this->service(0.0, $shipment)->submit($this->request($shipment));
    }

    public function testPartialCodFailFastPropagatesBeforeApiCall(): void
    {
        $shipment = $this->shipment();
        $codResolver = $this->createMock(CodAmountResolverInterface::class);
        $codResolver->method('resolve')
            ->willThrowException(new LocalizedException(__('Partial COD shipments are not supported yet.')));

        $config = $this->createMock(GhtkConfig::class);
        $config->method('getTransport')->willReturn('road');
        $config->method('getWeightUnit')->willReturn('kg');
        $config->method('getMinWeight')->willReturn(0.1);

        $service = new OrderSubmitService(
            $this->originProvider,
            $this->pickupResolver,
            $this->destResolver,
            $codResolver,
            new OrderRequestMapper(),
            new OrderResponseMapper(),
            $this->apiClient,
            $this->createMock(ShipmentWeightCalculator::class),
            new ShippingContextFactory(),
            $config,
            $this->logger
        );

        $this->apiClient->expects($this->never())->method('submitOrder');
        $this->expectException(LocalizedException::class);
        $service->submit($this->request($shipment));
    }

    public function testNoPackageWeightFallsBackToShipmentItems(): void
    {
        $shipment = $this->shipment();

        $this->apiClient->expects($this->once())
            ->method('submitOrder')
            ->with($this->callback(fn (array $p) => $p['weight'] === 700), 1) // calculator mock below
            ->willReturn(['success' => true, 'order' => ['label' => 'S2', 'tracking_code' => 'S2.T']]);

        $codResolver = $this->createMock(CodAmountResolverInterface::class);
        $codResolver->method('resolve')->willReturn(0.0);

        $config = $this->createMock(GhtkConfig::class);
        $config->method('getTransport')->willReturn('road');
        $config->method('getWeightUnit')->willReturn('kg');
        $config->method('getMinWeight')->willReturn(0.1);

        $weightCalculator = $this->createMock(ShipmentWeightCalculator::class);
        $weightCalculator->method('calculateForShipment')->willReturn(700);

        $service = new OrderSubmitService(
            $this->originProvider,
            $this->pickupResolver,
            $this->destResolver,
            $codResolver,
            new OrderRequestMapper(),
            new OrderResponseMapper(),
            $this->apiClient,
            $weightCalculator,
            new ShippingContextFactory(),
            $config,
            $this->logger
        );

        $service->submit($this->request($shipment, [['params' => ['weight' => 0]]]));
    }
}
