<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\OriginProvider;

use Magento\Directory\Model\Region;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\Origin;
use Secomm\ShippingCore\Model\OriginProvider\ShippingOriginProvider;
use Secomm\ShippingCore\Model\ShippingContextFactory;

class ShippingOriginProviderTest extends TestCase
{
    /**
     * @param array<string, mixed> $configValues shipping/origin/* values keyed by field.
     */
    private function provider(array $configValues, string $regionName = ''): ShippingOriginProvider
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            function (string $path, string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeCode = null) use ($configValues) {
                $field = (string) substr($path, strlen('shipping/origin/'));
                return $configValues[$field] ?? null;
            }
        );

        $region = $this->createMock(Region::class);
        $region->method('load')->willReturnSelf();
        $region->method('getName')->willReturn($regionName);

        $regionFactory = $this->createMock(RegionFactory::class);
        $regionFactory->method('create')->willReturn($region);

        return new ShippingOriginProvider($scopeConfig, $regionFactory);
    }

    private function context(?int $storeId): \Secomm\ShippingCore\Api\ShippingContextInterface
    {
        return (new ShippingContextFactory())->create($storeId, 'ghtk');
    }

    public function testResolvesFullShippingOrigin(): void
    {
        $origin = $this->provider(
            [
                'country_id' => 'VN',
                'region_id' => '467',
                'city' => 'Phường Hàng Trống',
                'street_line1' => '25',
                'street_line2' => 'Lý Thường Kiệt',
                'postcode' => '100000',
            ],
            'Ha Noi'
        )->resolve($this->context(3));

        $this->assertSame('VN', $origin->getCountryId());
        $this->assertSame(467, $origin->getRegionId());
        $this->assertSame('Ha Noi', $origin->getProvince());
        $this->assertSame('Phường Hàng Trống', $origin->getWard());
        $this->assertSame('25 Lý Thường Kiệt', $origin->getStreet());
        $this->assertSame('100000', $origin->getPostcode());
    }

    public function testDistrictTelephoneContactNameAreNullByDesign(): void
    {
        $origin = $this->provider(['country_id' => 'VN'], 'Ha Noi')->resolve($this->context(null));

        $this->assertNull($origin->getDistrict());
        $this->assertNull($origin->getTelephone());
        $this->assertNull($origin->getContactName());
        $this->assertNull($origin->getSourceCode());
        $this->assertFalse($origin->hasMetadata('ghtk.pick_address_id'));
    }

    public function testEmptyOriginYieldsNullFieldsNeverThrows(): void
    {
        $origin = $this->provider([])->resolve($this->context(null));

        $this->assertInstanceOf(Origin::class, $origin);
        $this->assertNull($origin->getCountryId());
        $this->assertNull($origin->getRegionId());
        $this->assertNull($origin->getProvince());
        $this->assertNull($origin->getWard());
        $this->assertNull($origin->getStreet());
        $this->assertNull($origin->getPostcode());
    }

    public function testBlankStringsAreNormalizedToNull(): void
    {
        $origin = $this->provider(
            ['country_id' => '  ', 'region_id' => '', 'city' => '  ', 'street_line1' => ' ', 'postcode' => '']
        )->resolve($this->context(null));

        $this->assertNull($origin->getCountryId());
        $this->assertNull($origin->getRegionId());
        $this->assertNull($origin->getWard());
        $this->assertNull($origin->getStreet());
        $this->assertNull($origin->getPostcode());
    }

    public function testStreetSingleLineOnly(): void
    {
        $origin = $this->provider(['street_line1' => '25 Lê Lợi', 'street_line2' => ''])->resolve($this->context(null));
        $this->assertSame('25 Lê Lợi', $origin->getStreet());
    }

    public function testReadsAreStoreScoped(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->exactly(6))
            ->method('getValue')
            ->with(
                $this->stringStartsWith('shipping/origin/'),
                ScopeInterface::SCOPE_STORE,
                7
            )
            ->willReturn(null);

        $regionFactory = $this->createMock(RegionFactory::class);
        (new ShippingOriginProvider($scopeConfig, $regionFactory))->resolve($this->context(7));
    }
}
