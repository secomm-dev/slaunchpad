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
use Secomm\Ghtk\Model\Address\GhtkAddressAdapter;
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
    private GhtkAddressAdapter $destResolver;
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

        $this->pickupResolver = new PickupAddressResolver($this->createMock(GhtkAddressAdapter::class));

        $destResolver = $this->createMock(GhtkAddressAdapter::class);
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
                    // Official CREATE shape: {order: {...}, products: [...]} — TASK-KCXKVR.
                    return isset($payload['order'], $payload['products'])
                        // Origin came through the (custom) provider — metadata wins.
                        && $payload['order']['pick_address_id'] === 'src-paid-42'
                        && $payload['order']['pick_money'] === 0
                        && $payload['order']['is_freeship'] === 1
                        && $payload['order']['id'] === 'ghtk-100000001-1'
                        // 1500 g → 1.5 kg at the GHTK boundary.
                        && $payload['order']['total_weight'] === 1.5;
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

    public function testTechnicalTransportFailureAbortsNoRetry(): void
    {
        $shipment = $this->shipment();
        // 5xx → TECHNICAL: single attempt, uncertain outcome, safe-retry message.
        $this->apiClient->expects($this->once())->method('submitOrder')
            ->willThrowException(new GhtkApiException(
                'GHTK order request failed (status 503).',
                false,
                0,
                null,
                \Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory::SERVER_ERROR
            ));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('temporarily unavailable');
        $this->service(0.0, $shipment)->submit($this->request($shipment));
    }

    public function testBusinessTransportCategoryAbortsAsConfigFailure(): void
    {
        $shipment = $this->shipment();
        // 403 auth/config → BUSINESS non-retry (§15/§16).
        $this->apiClient->expects($this->once())->method('submitOrder')
            ->willThrowException(new GhtkApiException(
                'GHTK order request rejected (status 403).',
                false,
                0,
                null,
                \Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory::CLIENT_ERROR
            ));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('rejected the shipment submission');
        $this->service(0.0, $shipment)->submit($this->request($shipment));
    }

    public function testMalformedSuccessPayloadAbortsAsTechnical(): void
    {
        $shipment = $this->shipment();
        // success=true but NO usable shipment identity — no shipment may be built (§18).
        $this->apiClient->method('submitOrder')->willReturn(['success' => true, 'order' => []]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('unusable response');
        $this->service(0.0, $shipment)->submit($this->request($shipment));
    }

    public function testDuplicateWithMatchingPartnerIdRecoversExistingOrder(): void
    {
        // §32 — ORDER_ID_EXIST + matching partner_id + ghtk_label → RECOVERED_EXISTING:
        // reuse the provider identity, mark recovered, no second submission.
        $shipment = $this->shipment();
        $shipment->expects($this->once())
            ->method('addComment')
            ->with($this->callback(fn (string $comment): bool => str_contains($comment, 'existing order recovered')), false, false);

        $this->apiClient->expects($this->once())->method('submitOrder')
            ->willReturn([
                'success' => false,
                'error_code' => 'ORDER_ID_EXIST',
                'partner_id' => 'ghtk-100000001-1',
                'ghtk_label' => 'S1.A1.17373471',
                'status' => 1,
            ]);

        $result = $this->service(0.0, $shipment)->submit($this->request($shipment));

        $this->assertTrue($result->recovered);
        $this->assertSame('ghtk-100000001-1', $result->partnerOrderId);
        $this->assertSame('S1.A1.17373471', $result->labelId);
        $this->assertSame('1', $result->providerStatus);
    }

    public function testDuplicateWithMismatchedPartnerIdIsAHardConflict(): void
    {
        $shipment = $this->shipment();
        $this->apiClient->method('submitOrder')->willReturn([
            'success' => false,
            'error_code' => 'ORDER_ID_EXIST',
            'partner_id' => 'ghtk-OTHER-ORDER',
            'ghtk_label' => 'S1.A1.OTHER',
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('different reference');
        $this->service(0.0, $shipment)->submit($this->request($shipment));
    }

    public function testDuplicateWithoutPartnerIdIsNeverSilentlyRecovered(): void
    {
        $shipment = $this->shipment();
        $this->apiClient->method('submitOrder')->willReturn([
            'success' => false,
            'error_code' => 'ORDER_ID_EXIST',
            'ghtk_label' => 'S1.A1.17373471',
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('did not identify it');
        $this->service(0.0, $shipment)->submit($this->request($shipment));
    }

    public function testDuplicateWithoutLabelIdentityIsAHardFailure(): void
    {
        $shipment = $this->shipment();
        $this->apiClient->method('submitOrder')->willReturn([
            'success' => false,
            'error_code' => 'ORDER_ID_EXIST',
            'partner_id' => 'ghtk-100000001-1',
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('without a label identity');
        $this->service(0.0, $shipment)->submit($this->request($shipment));
    }

    public function testManualRetryAfterUncertainFailureRecoversWithSameDeterministicId(): void
    {
        // §33 — attempt 1: uncertain technical failure; manual retry with the SAME
        // deterministic order.id → ORDER_ID_EXIST → recovery. No duplicate shipment.
        $shipment = $this->shipment();
        $capturedIds = [];
        $attempt = 0;

        $this->apiClient->expects($this->exactly(2))->method('submitOrder')
            ->with($this->callback(function (array $payload) use (&$capturedIds): bool {
                $capturedIds[] = $payload['order']['id'];

                return true;
            }))
            ->willReturnCallback(function () use (&$attempt) {
                $attempt++;
                if ($attempt === 1) {
                    throw new GhtkApiException(
                        'GHTK order request failed (status 503).',
                        false, 0, null,
                        \Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory::SERVER_ERROR
                    );
                }

                return [ // the provider HAD created the order on attempt 1
                    'success' => false,
                    'error_code' => 'ORDER_ID_EXIST',
                    'partner_id' => 'ghtk-100000001-1',
                    'ghtk_label' => 'S1.A1.17373471',
                    'status' => 1,
                ];
            });

        $service = $this->service(0.0, $shipment);

        try {
            $service->submit($this->request($shipment));
            $this->fail('Attempt 1 must abort the shipment creation (no false success).');
        } catch (LocalizedException) {
            // expected — admin/system retries the label creation
        }

        $result = $service->submit($this->request($shipment));

        $this->assertSame(['ghtk-100000001-1', 'ghtk-100000001-1'], $capturedIds, 'deterministic id must be identical across attempts');
        $this->assertTrue($result->recovered);
        $this->assertSame('S1.A1.17373471', $result->labelId);
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
            ->with($this->callback(fn (array $p) => $p['order']['total_weight'] === 0.7), 1) // calculator mock below
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
