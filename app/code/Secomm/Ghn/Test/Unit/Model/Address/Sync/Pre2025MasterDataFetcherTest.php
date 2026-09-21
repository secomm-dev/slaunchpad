<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Address\Sync;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Api\Client\GhnApiClientInterface;
use Secomm\Ghn\Model\Address\GhnSchemes;
use Secomm\Ghn\Model\Address\Sync\Pre2025MasterDataFetcher;
use Secomm\Ghn\Model\Client\GhnEndpoints;

/**
 * TASK-MZ2TCB / AC-B2 — legacy fetcher: province → district → ward nesting with verified field
 * names (ProvinceID/ProvinceName, DistrictID/DistrictName, WardCode/WardName).
 */
class Pre2025MasterDataFetcherTest extends TestCase
{
    private GhnApiClientInterface&MockObject $client;

    private Pre2025MasterDataFetcher $fetcher;

    protected function setUp(): void
    {
        $this->client = $this->createMock(GhnApiClientInterface::class);
        $this->fetcher = new Pre2025MasterDataFetcher($this->client);
    }

    public function testSupportsOnlyPre2025Scheme(): void
    {
        $this->assertSame(GhnSchemes::GHN_ADMIN_PRE_2025, $this->fetcher->supports());
        $this->expectException(LocalizedException::class);
        $this->fetcher->fetch(GhnSchemes::GHN_ADMIN_2025);
    }

    public function testFetchBuildsThreeLevelTree(): void
    {
        $this->client->expects($this->exactly(4))
            ->method('get')
            ->willReturnCallback(function (string $operation, string $path, array $params = []): array {
                return match ($path) {
                    GhnEndpoints::MASTER_DATA_PROVINCES => [
                        ['ProvinceID' => 201, 'ProvinceName' => 'TP. Hồ Chí Minh', 'CanUpdateCOD' => true],
                    ],
                    GhnEndpoints::MASTER_DATA_DISTRICTS => (function () use ($params): array {
                        self::assertSame('201', (string) ($params['province_id'] ?? ''));

                        return [
                            ['DistrictID' => 1442, 'DistrictName' => 'Quận 1', 'ProvinceID' => 201],
                            ['DistrictID' => 1443, 'DistrictName' => 'Quận 3', 'ProvinceID' => 201],
                        ];
                    })(),
                    GhnEndpoints::MASTER_DATA_WARDS => (function () use ($params): array {
                        self::assertContains((string) ($params['district_id'] ?? ''), ['1442', '1443']);

                        return [
                            ['WardCode' => 90733, 'WardName' => 'Phường Bến Nghé', 'DistrictID' => (int) $params['district_id']],
                        ];
                    })(),
                    default => self::fail('Unexpected path ' . $path),
                };
            });

        $rows = $this->fetcher->fetch(GhnSchemes::GHN_ADMIN_PRE_2025);

        $this->assertCount(5, $rows);

        $province = $rows[0];
        $this->assertSame('p:201', $province['provider_key']);
        $this->assertNull($province['parent_key']);
        $this->assertSame(1, $province['depth']);

        $district = $rows[1];
        $this->assertSame('d:201:1442', $district['provider_key']);
        $this->assertSame('p:201', $district['parent_key']);
        $this->assertSame('1442', $district['provider_id']);
        $this->assertSame(2, $district['depth']);

        // Depth-ASC order: province, district(1442), its ward, district(1443), its ward.
        $ward = $rows[2];
        $this->assertSame('w:1442:90733', $ward['provider_key']);
        $this->assertSame('90733', $ward['provider_code']);
        $this->assertNull($ward['provider_id']);
        $this->assertSame('d:201:1442', $ward['parent_key']);
        $this->assertSame(3, $ward['depth']);
        $this->assertSame(GhnSchemes::STATUS_ACTIVE, $ward['status']);
    }

    public function testMissingNameFailsLoud(): void
    {
        $this->client->method('get')->willReturn([['ProvinceID' => 1]]);

        $this->expectException(LocalizedException::class);
        $this->fetcher->fetch(GhnSchemes::GHN_ADMIN_PRE_2025);
    }
}
