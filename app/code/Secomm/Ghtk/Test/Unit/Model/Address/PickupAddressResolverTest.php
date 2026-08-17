<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Address;

use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Address\DestinationAddressResolver;
use Secomm\Ghtk\Model\Address\GhtkAddress;
use Secomm\Ghtk\Model\Address\PickupAddressResolver;
use Secomm\Ghtk\Model\Origin\GhtkOriginProvider;
use Secomm\ShippingCore\Model\Origin;

class PickupAddressResolverTest extends TestCase
{
    private function resolver(?GhtkAddress $normalized): PickupAddressResolver
    {
        $destResolver = $this->createMock(DestinationAddressResolver::class);
        $destResolver->method('resolve')->willReturn($normalized);

        return new PickupAddressResolver($destResolver);
    }

    private function origin(array $args, array $metadata = []): Origin
    {
        return new Origin(
            sourceCode: null,
            countryId: $args['countryId'] ?? 'VN',
            regionId: $args['regionId'] ?? null,
            province: $args['province'] ?? null,
            district: $args['district'] ?? null,
            ward: $args['ward'] ?? null,
            street: null,
            postcode: null,
            telephone: null,
            contactName: null,
            metadata: $metadata
        );
    }

    public function testPickAddressIdMetadataPreferred(): void
    {
        $pickup = $this->resolver(null)->resolve(
            $this->origin(['province' => 'ignored'], [GhtkOriginProvider::METADATA_PICK_ADDRESS_ID => 'abc123'])
        );

        $this->assertNotNull($pickup);
        $this->assertTrue($pickup->hasPickAddressId());
        $this->assertSame('abc123', $pickup->pickAddressId);
        $this->assertNull($pickup->province);
    }

    public function testRegionIdBasedOriginNormalizedToGhtkNames(): void
    {
        $normalized = new GhtkAddress('Hà Nội', 'Hoàn Kiếm', 'Phường Hàng Trống', true);
        $pickup = $this->resolver($normalized)->resolve(
            $this->origin(['regionId' => 467, 'ward' => 'Phường Hàng Trống'])
        );

        $this->assertNotNull($pickup);
        $this->assertFalse($pickup->hasPickAddressId());
        $this->assertSame('Hà Nội', $pickup->province);
        $this->assertSame('Hoàn Kiếm', $pickup->district);
        $this->assertSame('Phường Hàng Trống', $pickup->ward);
    }

    public function testRegionIdBasedOriginUnresolvableIsNull(): void
    {
        $this->assertNull(
            $this->resolver(null)->resolve($this->origin(['regionId' => 467, 'ward' => 'Không Tồn Tại']))
        );
    }

    public function testNamesOnlyOriginUsedAsIs(): void
    {
        $pickup = $this->resolver(null)->resolve(
            $this->origin(['province' => 'Hà Nội', 'ward' => 'Hoàn Kiếm', 'district' => ''])
        );

        $this->assertNotNull($pickup);
        $this->assertSame('Hà Nội', $pickup->province);
        $this->assertSame('Hoàn Kiếm', $pickup->ward);
        $this->assertNull($pickup->district);
    }

    public function testNamesOnlyOriginOptionalDistrictForwarded(): void
    {
        $pickup = $this->resolver(null)->resolve(
            $this->origin(['province' => 'Hà Nội', 'ward' => 'Hoàn Kiếm', 'district' => 'Ba Đình'])
        );

        $this->assertNotNull($pickup);
        $this->assertSame('Ba Đình', $pickup->district);
    }

    public function testMissingWardIsInvalid(): void
    {
        $this->assertNull($this->resolver(null)->resolve($this->origin(['province' => 'Hà Nội'])));
    }

    public function testMissingProvinceIsInvalid(): void
    {
        $this->assertNull($this->resolver(null)->resolve($this->origin(['ward' => 'Hoàn Kiếm'])));
    }

    public function testEmptyOriginIsInvalid(): void
    {
        $this->assertNull($this->resolver(null)->resolve($this->origin([])));
    }
}
