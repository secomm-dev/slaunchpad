<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\OrderSubmit;

use Magento\Shipping\Model\Shipment\Request;
use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Address\GhtkAddress;
use Secomm\Ghtk\Model\Address\PickupAddress;
use Secomm\Ghtk\Model\OrderSubmit\OrderRequestMapper;

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
        return [
            ['name' => 'Ao thun', 'weight' => 200, 'quantity' => 2, 'price' => 250000.0],
            ['name' => 'Quan jean', 'weight' => 500, 'quantity' => 1, 'price' => 600000.0],
        ];
    }

    public function testPickupAddressIdPreferredAndCodMapped(): void
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

        $this->assertSame('paid-7', $payload['pick_address_id']);
        $this->assertArrayNotHasKey('pick_province', $payload);
        $this->assertSame('Nguyen Van Admin', $payload['pick_name']);
        $this->assertSame('0901234567', $payload['pick_tel']);
        $this->assertSame(500000, $payload['pick_money']); // resolved COD — never grand_total
        $this->assertSame(1, $payload['is_freeship']); // Magento charged shipping — no double charge
        $this->assertSame('gram', $payload['weight_option']);
        $this->assertSame(900, $payload['weight']);
        $this->assertSame('ghtk-100000001-1', $payload['partner_order_id']);
        $this->assertSame('road', $payload['transport']);
        $this->assertSame(1100000, $payload['value']); // declared value = Σ price × qty
        $this->assertSame('Tran Thi Buyer', $payload['name']);
        $this->assertSame('Hà Nội', $payload['province']);
        $this->assertSame('Hoàn Kiếm', $payload['district']);
        $this->assertSame('Phường Hàng Trống', $payload['ward']);
        $this->assertSame($this->products(), $payload['order']);
    }

    public function testPickupNamesMappedWithoutAddressId(): void
    {
        $payload = (new OrderRequestMapper())->map(
            $this->request(),
            new PickupAddress(null, 'Hà Nội', 'Hoàn Kiếm', 'Phường Hàng Bạc'),
            new GhtkAddress('Hà Nội', null, 'Phường Hàng Trống', false),
            1500,
            0.0,
            'ghtk-100000002-1',
            $this->products(),
            'fly'
        );

        $this->assertArrayNotHasKey('pick_address_id', $payload);
        $this->assertSame('Hà Nội', $payload['pick_province']);
        $this->assertSame('Hoàn Kiếm', $payload['pick_district']);
        $this->assertSame('Phường Hàng Bạc', $payload['pick_ward']);
        $this->assertSame(0, $payload['pick_money']); // prepaid
        $this->assertArrayNotHasKey('district', $payload); // optional destination district omitted
        $this->assertSame('fly', $payload['transport']);
    }

    public function testShipperFallsBackToCompanyName(): void
    {
        $request = $this->request();
        $request->setShipperContactPersonName('');

        $payload = (new OrderRequestMapper())->map(
            $request,
            new PickupAddress('paid-1', null, null, null),
            new GhtkAddress('Hà Nội', null, 'Phường Hàng Trống', false),
            1000,
            0.0,
            'p-1',
            $this->products(),
            'road'
        );

        $this->assertSame('Secomm Store', $payload['pick_name']);
    }
}
