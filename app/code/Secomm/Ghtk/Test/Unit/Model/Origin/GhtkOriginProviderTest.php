<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Origin;

use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\Origin\GhtkOriginProvider;
use Secomm\ShippingCore\Api\OriginInterface;
use Secomm\ShippingCore\Api\OriginProviderInterface;
use Secomm\ShippingCore\Model\ShippingContextFactory;

class GhtkOriginProviderTest extends TestCase
{
    private function config(array $pickup): GhtkConfig
    {
        $config = $this->createMock(GhtkConfig::class);
        $config->method('getPickAddressId')->willReturn($pickup['pick_address_id'] ?? '');
        $config->method('getPickProvince')->willReturn($pickup['pick_province'] ?? '');
        $config->method('getPickDistrict')->willReturn($pickup['pick_district'] ?? '');
        $config->method('getPickWard')->willReturn($pickup['pick_ward'] ?? '');

        return $config;
    }

    private function provider(array $pickup, ?OriginInterface $innerOrigin): GhtkOriginProvider
    {
        $inner = $this->createMock(OriginProviderInterface::class);
        if ($innerOrigin !== null) {
            $inner->expects($this->once())->method('resolve')->willReturn($innerOrigin);
        } else {
            $inner->expects($this->never())->method('resolve');
        }

        return new GhtkOriginProvider($this->config($pickup), $inner);
    }

    public function testLegacyPickAddressIdBecomesMetadataWithoutInnerCall(): void
    {
        $origin = $this->provider(['pick_address_id' => 'paid-9', 'pick_province' => 'ignored'], null)
            ->resolve((new ShippingContextFactory())->create(1, 'ghtk'));

        $this->assertSame('paid-9', $origin->getMetadata(GhtkOriginProvider::METADATA_PICK_ADDRESS_ID));
        $this->assertSame('VN', $origin->getCountryId());
    }

    public function testLegacyProvinceWardBecomesOrigin(): void
    {
        $origin = $this->provider(['pick_province' => 'Hà Nội', 'pick_district' => 'Hoàn Kiếm', 'pick_ward' => 'Phường Hàng Trống'], null)
            ->resolve((new ShippingContextFactory())->create(null, 'ghtk'));

        $this->assertSame('Hà Nội', $origin->getProvince());
        $this->assertSame('Hoàn Kiếm', $origin->getDistrict());
        $this->assertSame('Phường Hàng Trống', $origin->getWard());
        $this->assertFalse($origin->hasMetadata(GhtkOriginProvider::METADATA_PICK_ADDRESS_ID));
    }

    public function testHalfFilledLegacyConfigDoesNotDelegate(): void
    {
        // Strict DEC-021: a partially filled legacy override must not silently
        // fall back to the inner (shipping-origin) provider.
        $origin = $this->provider(['pick_province' => 'Hà Nội'], null)
            ->resolve((new ShippingContextFactory())->create(null, 'ghtk'));

        $this->assertSame('Hà Nội', $origin->getProvince());
        $this->assertNull($origin->getWard());
    }

    public function testEmptyLegacyConfigDelegatesToInnerProvider(): void
    {
        $innerOrigin = new \Secomm\ShippingCore\Model\Origin(
            sourceCode: 'default',
            countryId: 'VN',
            regionId: 467,
            province: 'Ha Noi',
            district: null,
            ward: 'Phường Hàng Trống',
            street: '25 Lê Lợi',
            postcode: null,
            telephone: null,
            contactName: null
        );

        $origin = $this->provider([], $innerOrigin)
            ->resolve((new ShippingContextFactory())->create(2, 'ghtk'));

        $this->assertSame($innerOrigin, $origin);
    }
}
