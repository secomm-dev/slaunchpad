<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Fee;

use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Address\GhtkAddress;
use Secomm\Ghtk\Model\Address\PickupAddress;
use Secomm\Ghtk\Model\Fee\FeeRequestMapper;

class FeeRequestMapperTest extends TestCase
{
    public function testPickupAddressIdSentAlone(): void
    {
        $params = (new FeeRequestMapper())->map(
            new GhtkAddress('Hà Nội', 'Hoàn Kiếm', 'Phường Hàng Trống', true),
            new PickupAddress('paid-7', null, null, null),
            1500,
            500000.0,
            'road'
        );

        $this->assertSame('paid-7', $params['pick_address_id']);
        $this->assertArrayNotHasKey('pick_province', $params);
        $this->assertArrayNotHasKey('pick_ward', $params);
        $this->assertArrayNotHasKey('pick_district', $params);

        $this->assertSame('Hà Nội', $params['province']);
        $this->assertSame('Hoàn Kiếm', $params['district']);
        $this->assertSame('Phường Hàng Trống', $params['ward']);
        $this->assertSame(1500, $params['weight']);
        $this->assertSame(500000.0, $params['value']);
        $this->assertSame('road', $params['transport']);
    }

    public function testNormalizedPickupAddressFields(): void
    {
        $params = (new FeeRequestMapper())->map(
            new GhtkAddress('Hà Nội', null, 'Phường Hàng Trống', false),
            new PickupAddress(null, 'Hà Nội', 'Hoàn Kiếm', 'Phường Hàng Bạc'),
            1000,
            0.0,
            'fly'
        );

        $this->assertArrayNotHasKey('pick_address_id', $params);
        $this->assertSame('Hà Nội', $params['pick_province']);
        $this->assertSame('Phường Hàng Bạc', $params['pick_ward']);
        $this->assertSame('Hoàn Kiếm', $params['pick_district']);
        $this->assertArrayNotHasKey('district', $params); // optional destination district omitted
        $this->assertArrayNotHasKey('value', $params); // zero declared value omitted
        $this->assertSame('fly', $params['transport']);
    }

    public function testPickupWithoutDistrictOmitsIt(): void
    {
        $params = (new FeeRequestMapper())->map(
            new GhtkAddress('Hà Nội', null, 'Phường Hàng Trống', false),
            new PickupAddress(null, 'Hà Nội', null, 'Phường Hàng Bạc'),
            1000,
            0.0,
            'road'
        );

        $this->assertArrayNotHasKey('pick_district', $params);
    }

    public function testPickupIdentityForCacheKey(): void
    {
        $mapper = new FeeRequestMapper();

        $this->assertSame(
            'paid:paid-7',
            $mapper->pickupIdentity(new PickupAddress('paid-7', null, null, null))
        );
        $this->assertSame(
            'pp:Hà Nội|Phường Hàng Bạc',
            $mapper->pickupIdentity(new PickupAddress(null, 'Hà Nội', 'Hoàn Kiếm', 'Phường Hàng Bạc'))
        );
    }
}
