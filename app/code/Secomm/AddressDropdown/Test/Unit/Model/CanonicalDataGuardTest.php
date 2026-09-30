<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\Model\CanonicalDataGuard;
use Secomm\AddressDropdown\Model\Region;
use Secomm\AddressDropdown\Model\RegionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel as RegionResource;

/**
 * TASK-SEC-A4 — canonical VN data guard: VN rows are import-workflow-only (reject with an
 * explicit reason); every other country keeps the full generic engine.
 */
class CanonicalDataGuardTest extends TestCase
{
    private RegionFactory&MockObject $regionFactory;

    private RegionResource&MockObject $regionResource;

    private CanonicalDataGuard $guard;

    protected function setUp(): void
    {
        $this->regionFactory = $this->createMock(RegionFactory::class);
        $this->regionResource = $this->createMock(RegionResource::class);
        $this->regionFactory->method('create')->willReturnCallback(
            function (): Region {
                // AbstractModel's constructor requires framework context — stub the two
                // data methods the guard actually uses.
                $data = [];
                $region = $this->createPartialMock(Region::class, ['getData', 'setData']);
                $region->method('setData')->willReturnCallback(
                    function (string $key, mixed $value) use (&$data, $region): Region {
                        $data[$key] = $value;

                        return $region;
                    }
                );
                $region->method('getData')->willReturnCallback(
                    function (string $key) use (&$data): mixed {
                        return $data[$key] ?? null;
                    }
                );

                return $region;
            }
        );
        $this->guard = new CanonicalDataGuard($this->regionFactory, $this->regionResource);
    }

    public function testRejectsExplicitVnCountry(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Secomm_VietNamAddress import workflow');

        $this->guard->assertRegionMutatable(0, 'VN');
    }

    public function testRejectsExistingVnRegionByLookup(): void
    {
        $this->regionResource->method('load')->willReturnCallback(
            function (Region $region, int $id): void {
                $region->setData('region_id', $id);
                $region->setData('country_id', 'VN');
            }
        );

        try {
            $this->guard->assertRegionMutatable(12, null);
            $this->fail('Expected LocalizedException');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('region #12', $exception->getMessage());
        }
    }

    public function testAllowsNonVnRegionWithoutLookup(): void
    {
        // Country is explicitly not VN — the resource layer is never even consulted.
        $this->regionResource->expects($this->never())->method('load');

        $this->guard->assertRegionMutatable(33, 'US');
        $this->addToAssertionCount(1);
    }

    public function testAllowsVnCodeFromAnotherCountryRow(): void
    {
        $this->regionResource->method('load')->willReturnCallback(
            function (Region $region, int $id): void {
                $region->setData('region_id', $id);
                $region->setData('country_id', 'FR');
            }
        );

        $this->guard->assertRegionMutatable(77, null);
        $this->addToAssertionCount(1);
    }

    public function testRejectsCityOfVnRegion(): void
    {
        $this->regionResource->method('load')->willReturnCallback(
            function (Region $region, int $id): void {
                $region->setData('region_id', $id);
                $region->setData('country_id', 'VN');
            }
        );

        try {
            $this->guard->assertCityMutatable(9, 3);
            $this->fail('Expected LocalizedException');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('city #9 (parent region #3)', $exception->getMessage());
        }
    }

    public function testAllowsCityOfNonVnRegion(): void
    {
        $this->regionResource->method('load')->willReturnCallback(
            function (Region $region, int $id): void {
                $region->setData('region_id', $id);
                $region->setData('country_id', 'US');
            }
        );

        $this->guard->assertCityMutatable(9, 3);
        $this->addToAssertionCount(1);
    }
}
