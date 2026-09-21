<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001 §11–§13) — city precedence narrowing tests.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Test\Unit\Model\City;

use Launchpad\MageplazaTableRate\Model\City\CityRateScopeResolver;
use Launchpad\MageplazaTableRate\Model\City\DestinationCityResolver;
use Launchpad\MageplazaTableRate\Model\MethodSettingsProvider;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Mageplaza\TableRateShipping\Model\ResourceModel\Rate\Collection;
use PHPUnit\Framework\TestCase;

class CityRateScopeResolverTest extends TestCase
{
    private MethodSettingsProvider $settingsProvider;

    private DestinationCityResolver $destinationResolver;

    private CityRateScopeResolver $resolver;

    protected function setUp(): void
    {
        $this->settingsProvider = $this->getMockBuilder(MethodSettingsProvider::class)
            ->disableOriginalConstructor()->getMock();
        $this->destinationResolver = $this->createMock(DestinationCityResolver::class);
        $this->resolver = new CityRateScopeResolver($this->settingsProvider, $this->destinationResolver);
    }

    /**
     * Fake matched rate row: id + region value (region column semantics of Mageplaza:
     * numeric region_id, or '*' wildcard).
     */
    private function rateRow(int $id, string $region): \Mageplaza\TableRateShipping\Model\Rate
    {
        $rate = $this->getMockBuilder(\Mageplaza\TableRateShipping\Model\Rate::class)
            ->onlyMethods(['getId'])
            ->addMethods(['getRegion'])
            ->disableOriginalConstructor()->getMock();
        $rate->method('getId')->willReturn($id);
        $rate->method('getRegion')->willReturn($region);

        return $rate;
    }

    private function collection(array $rows, array &$removed): Collection
    {
        $collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()->getMock();
        $collection->method('getItems')->willReturn($rows);
        $collection->method('removeItemByKey')->willReturnCallback(static function ($key) use (&$removed) {
            $removed[] = $key;

            return null;
        });

        return $collection;
    }

    private function request(int $regionId, string $city): RateRequest
    {
        $request = new RateRequest();
        $request->setDestRegionId($regionId);
        $request->setDestCity($city);

        return $request;
    }

    public function testExactCityBeatsEverything(): void
    {
        // VN | HCM | X → 20k ; VN | HCM | * → 30k ; VN | * | * → 40k ; dest X → keep ONLY 20k row.
        $rows = [$this->rateRow(1, '20'), $this->rateRow(2, '20'), $this->rateRow(3, '*')];
        $removed = [];
        $collection = $this->collection($rows, $removed);
        $this->settingsProvider->method('fetchCityCodes')->willReturn([1 => 'VNA25-X', 2 => 'VNA25-Y']);
        $this->destinationResolver->method('resolveCityCode')->willReturn('VNA25-X');

        $this->resolver->apply($collection, $this->request(20, 'Phường X'));
        $this->assertSame([2, 3], $removed);
    }

    public function testRegionWildcardCityWinsWhenNoExactCityRow(): void
    {
        // dest Y: exact city rows exist (X) but don't match; exact-region wildcard beats broader.
        $rows = [$this->rateRow(1, '20'), $this->rateRow(2, '20'), $this->rateRow(3, '*')];
        $removed = [];
        $collection = $this->collection($rows, $removed);
        $this->settingsProvider->method('fetchCityCodes')->willReturn([1 => 'VNA25-X', 2 => 'VNA25-Y']);
        $this->destinationResolver->method('resolveCityCode')->willReturn('VNA25-Y');

        $this->resolver->apply($collection, $this->request(20, 'Phường Y'));
        $this->assertSame([1, 3], $removed);
    }

    public function testBroaderWildcardUsedWhenNothingNarrower(): void
    {
        // No rows on region 20 without city constraint; only broader rows survive.
        $rows = [$this->rateRow(1, '99'), $this->rateRow(2, '99'), $this->rateRow(3, '*')];
        $removed = [];
        $collection = $this->collection($rows, $removed);
        $this->settingsProvider->method('fetchCityCodes')->willReturn([1 => 'VNA25-X']);
        $this->destinationResolver->method('resolveCityCode')->willReturn('VNA25-Z');

        $this->resolver->apply($collection, $this->request(20, 'Phường Z'));
        $this->assertSame([1], $removed);
    }

    public function testUnresolvedDestinationNeverMatchesCityRows(): void
    {
        $rows = [$this->rateRow(1, '20'), $this->rateRow(2, '*')];
        $removed = [];
        $collection = $this->collection($rows, $removed);
        $this->settingsProvider->method('fetchCityCodes')->willReturn([1 => 'VNA25-X']);
        $this->destinationResolver->method('resolveCityCode')->willReturn(null);

        $this->resolver->apply($collection, $this->request(20, '??'));
        $this->assertSame([1], $removed);
    }

    public function testUnresolvedDestinationKeepsAllWildcardTiersCombined(): void
    {
        // No narrowable identity → Mageplaza aggregation across wildcard tiers must be kept.
        $rows = [$this->rateRow(1, '20'), $this->rateRow(2, '*'), $this->rateRow(3, '20')];
        $removed = [];
        $collection = $this->collection($rows, $removed);
        $this->settingsProvider->method('fetchCityCodes')->willReturn([1 => 'VNA25-X']);
        $this->destinationResolver->method('resolveCityCode')->willReturn(null);

        $this->resolver->apply($collection, $this->request(20, ''));
        $this->assertSame([1], $removed);
    }

    public function testNoCityRowsIsUntouchedZeroRegression(): void
    {
        $rows = [$this->rateRow(1, '20'), $this->rateRow(2, '*')];
        $removed = [];
        $collection = $this->collection($rows, $removed);
        $this->settingsProvider->method('fetchCityCodes')->willReturn([]);

        $this->resolver->apply($collection, $this->request(20, 'Phường X'));
        $this->assertSame([], $removed);
    }

    public function testWinningTierKeepsAllRowsForSumMinMax(): void
    {
        // Multiple rows inside the winning tier (different weight/subtotal ranges) — ALL kept
        // so Mageplaza's SUM/MIN/MAX aggregation is unchanged within the tier.
        $rows = [
            $this->rateRow(1, '20'),
            $this->rateRow(2, '20'),
            $this->rateRow(3, '20'),
        ];
        $removed = [];
        $collection = $this->collection($rows, $removed);
        $this->settingsProvider->method('fetchCityCodes')->willReturn([
            1 => 'VNA25-X',
            2 => 'VNA25-X',
            3 => 'VNA25-X',
        ]);
        $this->destinationResolver->method('resolveCityCode')->willReturn('VNA25-X');

        $this->resolver->apply($collection, $this->request(20, 'Phường X'));
        $this->assertSame([], $removed);
    }

    public function testBlankDestinationRegionSkipsRegionTier(): void
    {
        $rows = [$this->rateRow(1, '*'), $this->rateRow(2, '20')];
        $removed = [];
        $collection = $this->collection($rows, $removed);
        $this->settingsProvider->method('fetchCityCodes')->willReturn([2 => 'VNA25-X']);
        $this->destinationResolver->method('resolveCityCode')->willReturn('VNA25-Q');

        // dest region 0 → tier 2 cannot apply; tier 3 (wildcards) wins.
        $this->resolver->apply($collection, $this->request(0, 'Phường Q'));
        $this->assertSame([2], $removed);
    }
}
