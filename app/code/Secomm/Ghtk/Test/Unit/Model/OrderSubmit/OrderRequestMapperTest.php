<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\OrderSubmit;

use Magento\Shipping\Model\Shipment\Request;
use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Address\GhtkAddress;
use Secomm\Ghtk\Model\Address\PickupAddress;
use Secomm\Ghtk\Model\OrderSubmit\OrderRequestMapper;

/**
 * TASK-KCXKVR — CREATE payload must match the OFFICIAL contract:
 * `{"order": {...}, "products": [...]}`, partner key in `order.id`, weights in
 * KILOGRAMS (converted at the GHTK boundary from project-internal grams).
 */
class OrderRequestMapperTest extends TestCase
{
    private function request(): Request
    {
        $request = new Request();
        $request->setShipperContactPersonName('Nguyen Van Admin');
        $request->setShipperContactCompanyName('Secomm Store');
        $request->setShipperContactPhoneNumber('0901234567');
        $request->setShipperAddressStreet('25 Ly Thuong Kiet');
        $request->setRecipientContactPersonName('Tran Thi Buyer');
        $request->setRecipientContactPhoneNumber('0987654321');
        $request->setRecipientAddressStreet('12 Hang Bac');

        return $request;
    }

    private function products(): array
    {
        // Project-internal rows: weights in GRAMS (provider boundary converts).
        return [
            ['name' => 'Ao thun', 'weight' => 200, 'quantity' => 2, 'price' => 250000.0],
            ['name' => 'Quan jean', 'weight' => 500, 'quantity' => 1, 'price' => 600000.0],
        ];
    }

    public function testTopLevelShapeIsOrderPlusProducts(): void
    {
        $payload = (new OrderRequestMapper())->map(
            $this->request(),
            new PickupAddress('paid-7', null, null, null),
            new GhtkAddress('Hà Nội', 'Hoàn Kiếm', 'Phường Hàng Trống', true),
            900,
            500000.0,
            'ghtk-100000001-1',
            $this->products(),
            'road'
        );

        $this->assertSame(['order', 'products'], array_keys($payload));
        $this->assertIsArray($payload['order']);
        $this->assertIsArray($payload['products']);
    }

    public function testOrderFieldsNestedUnderOrderWithIdKey(): void
    {
        $order = $this->mapOrder(
            new PickupAddress('paid-7', null, null, null),
            new GhtkAddress('Hà Nội', 'Hoàn Kiếm', 'Phường Hàng Trống', true),
            900,
            500000.0,
            'ghtk-100000001-1',
            'road'
        );

        // Deterministic partner key — official duplicate-detection field.
        $this->assertSame('ghtk-100000001-1', $order['id']);
        $this->assertArrayNotHasKey('partner_order_id', $order);
        $this->assertSame('Nguyen Van Admin', $order['pick_name']);
        $this->assertSame('0901234567', $order['pick_tel']);
        $this->assertSame('25 Ly Thuong Kiet', $order['pick_address']);
        $this->assertSame(500000, $order['pick_money']); // resolved COD — never grand_total
        $this->assertSame(1, $order['is_freeship']); // Magento charged shipping — no double charge
        $this->assertSame('road', $order['transport']);
        $this->assertSame(1100000, $order['value']); // declared value = Σ price × qty
        $this->assertSame('Tran Thi Buyer', $order['name']);
        $this->assertSame('0987654321', $order['tel']);
        $this->assertSame('12 Hang Bac', $order['address']);
        $this->assertSame('Hà Nội', $order['province']);
        $this->assertSame('Hoàn Kiếm', $order['district']);
        $this->assertSame('Phường Hàng Trống', $order['ward']);
        $this->assertSame('Khác', $order['hamlet']); // official default when hamlet not applicable
    }

    public function testWeightsAreConvertedToKilogramsAtTheBoundary(): void
    {
        $payload = (new OrderRequestMapper())->map(
            $this->request(),
            new PickupAddress('paid-7', null, null, null),
            new GhtkAddress('Hà Nội', null, 'Phường Hàng Trống', false),
            900, // shipment total, grams
            0.0,
            'ghtk-1-1',
            $this->products(), // 200g + 500g per unit
            'road'
        );

        // 900 g → 0.9 kg (Double) — official unit is KG, never the store unit.
        $this->assertSame(0.9, $payload['order']['total_weight']);
        // Products converted per unit: 200g → 0.2kg, 500g → 0.5kg.
        $this->assertSame(0.2, $payload['products'][0]['weight']);
        $this->assertSame(0.5, $payload['products'][1]['weight']);
        // Mixed units are impossible: no weight_option key (documented default kilogram).
        $this->assertArrayNotHasKey('weight_option', $payload['order']);
    }

    public function testSubGramProductWeightFloorsAtOneGramInKg(): void
    {
        $payload = (new OrderRequestMapper())->map(
            $this->request(),
            new PickupAddress('paid-1', null, null, null),
            new GhtkAddress('Hà Nội', null, 'Phường Hàng Trống', false),
            1, // 1 gram total
            0.0,
            'ghtk-1-1',
            [['name' => 'Tiny', 'weight' => 1, 'quantity' => 1, 'price' => 1000.0]],
            'road'
        );

        $this->assertSame(0.001, $payload['products'][0]['weight']);
        $this->assertSame(0.001, $payload['order']['total_weight']);
    }

    public function testProductsPayloadCarriesRequiredAndDocumentedOptionalFields(): void
    {
        $payload = (new OrderRequestMapper())->map(
            $this->request(),
            new PickupAddress('paid-7', null, null, null),
            new GhtkAddress('Hà Nội', null, 'Phường Hàng Trống', false),
            700,
            25000.0,
            'ghtk-2-1',
            $this->products(),
            'road'
        );

        $this->assertSame('Ao thun', $payload['products'][0]['name']);
        $this->assertSame(2, $payload['products'][0]['quantity']);
        $this->assertSame(250000.0, $payload['products'][0]['price']);
        $this->assertSame('Quan jean', $payload['products'][1]['name']);
        $this->assertSame(1, $payload['products'][1]['quantity']);
    }

    public function testPickupAddressIdPreferredAndNestedInOrder(): void
    {
        $order = $this->mapOrder(
            new PickupAddress('paid-7', null, null, null),
            new GhtkAddress('Hà Nội', 'Hoàn Kiếm', 'Phường Hàng Trống', true),
            900,
            500000.0,
            'ghtk-100000001-1',
            'road'
        );

        $this->assertSame('paid-7', $order['pick_address_id']); // official priority field
        $this->assertArrayNotHasKey('pick_province', $order);
        $this->assertArrayNotHasKey('pick_ward', $order);
    }

    public function testPickupNamesMappedWithoutAddressId(): void
    {
        $order = $this->mapOrder(
            new PickupAddress(null, 'Hà Nội', 'Hoàn Kiếm', 'Phường Hàng Bạc'),
            new GhtkAddress('Hà Nội', null, 'Phường Hàng Trống', false),
            1500,
            0.0,
            'ghtk-100000002-1',
            'fly'
        );

        $this->assertArrayNotHasKey('pick_address_id', $order);
        $this->assertSame('Hà Nội', $order['pick_province']);
        $this->assertSame('Hoàn Kiếm', $order['pick_district']);
        $this->assertSame('Phường Hàng Bạc', $order['pick_ward']);
        $this->assertSame(0, $order['pick_money']); // prepaid
        $this->assertArrayNotHasKey('district', $order); // optional destination district omitted
        $this->assertSame('fly', $order['transport']);
    }

    public function testShipperFallsBackToCompanyName(): void
    {
        $request = $this->request();
        $request->setShipperContactPersonName('');

        $order = $this->mapOrder(
            new PickupAddress('paid-1', null, null, null),
            new GhtkAddress('Hà Nội', null, 'Phường Hàng Trống', false),
            1000,
            0.0,
            'p-1',
            'road',
            $request
        );

        $this->assertSame('Secomm Store', $order['pick_name']);
    }

    private function mapOrder(
        PickupAddress $pickup,
        GhtkAddress $dest,
        int $weightGram,
        float $codAmount,
        string $partnerOrderId,
        string $transport,
        ?Request $request = null
    ): array {
        $payload = (new OrderRequestMapper())->map(
            $request ?? $this->request(),
            $pickup,
            $dest,
            $weightGram,
            $codAmount,
            $partnerOrderId,
            $this->products(),
            $transport
        );

        return $payload['order'];
    }
}
