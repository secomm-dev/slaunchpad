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
use Secomm\Ghn\Model\Address\Sync\Admin2025MasterDataFetcher;
use Secomm\Ghn\Model\Client\GhnEndpoints;

/**
 * TASK-MZ2TCB / AC-B2 — v3 new-model fetcher: paging, status mapping (1=active, 2=disabled,
 * 10=deleted skipped), extension_names JSON, verbatim `name`, missing field fail-loud.
 */
class Admin2025MasterDataFetcherTest extends TestCase
{
    private GhnApiClientInterface&MockObject $client;

    private Admin2025MasterDataFetcher $fetcher;

    protected function setUp(): void
    {
        $this->client = $this->createMock(GhnApiClientInterface::class);
        // pageSize=2 keeps the paging test small while still exercising the loop.
        $this->fetcher = new Admin2025MasterDataFetcher($this->client, 2);
    }

    public function testSupportsOnly2025Scheme(): void
    {
        $this->assertSame(GhnSchemes::GHN_ADMIN_2025, $this->fetcher->supports());
        $this->expectException(LocalizedException::class);
        $this->fetcher->fetch(GhnSchemes::GHN_ADMIN_PRE_2025);
    }

    public function testFetchBuildsParentFirstRowsWithPaging(): void
    {
        $provincePage1 = [
            ['_id' => 1, 'name' => 'Tp. Hồ Chí Minh', 'extension_names' => ['TP HCM'], 'type' => 'province', 'parent_id' => 1, 'status' => 1],
            ['_id' => 2, 'name' => 'Tỉnh Ghe', 'extension_names' => null, 'type' => 'province', 'parent_id' => 1, 'status' => 2],
            ['_id' => 3, 'name' => 'Tỉnh Đã Xóa', 'extension_names' => null, 'type' => 'province', 'parent_id' => 1, 'status' => 10],
        ];

        $wardPages = [
            // first page full → triggers a second page request
            [
                ['_id' => 11, 'name' => 'Phường Bến Nghé', 'extension_names' => [], 'type' => 'ward', 'parent_id' => 1, 'status' => 1],
                ['_id' => 12, 'name' => 'Phường Đa Kao', 'extension_names' => null, 'type' => 'ward', 'parent_id' => 1, 'status' => 1],
            ],
            // second page short → paging stops
            [
                ['_id' => 13, 'name' => 'Phường Bến Thành', 'extension_names' => null, 'type' => 'ward', 'parent_id' => 1, 'status' => 1],
            ],
        ];

        $this->client->expects($this->exactly(3))
            ->method('get')
            ->willReturnCallback(function (string $operation, string $path, array $params = []) use ($provincePage1, $wardPages): array {
                if ($path === GhnEndpoints::MASTER_DATA_PROVINCES_V3) {
                    $this->assertSame(['offset' => 0, 'limit' => 2], $params);

                    return $provincePage1;
                }

                $this->assertSame(GhnEndpoints::MASTER_DATA_WARDS_BY_PROVINCE_V3, $path);
                $this->assertSame('1', (string) $params['province_id']);
                $offset = (int) $params['offset'];

                return $offset === 0 ? $wardPages[0] : $wardPages[1];
            });

        $rows = $this->fetcher->fetch(GhnSchemes::GHN_ADMIN_2025);

        // Deleted province skipped entirely; disabled province kept without wards.
        $this->assertCount(5, $rows);

        $province = $rows[0];
        $this->assertSame('1', $province['provider_key']);
        $this->assertNull($province['parent_key']);
        $this->assertSame(1, $province['depth']);
        $this->assertSame(GhnSchemes::STATUS_ACTIVE, $province['status']);
        $this->assertSame((string) json_encode(['TP HCM'], JSON_UNESCAPED_UNICODE), $province['extension_names']);

        $disabled = $rows[1];
        $this->assertSame('2', $disabled['provider_key']);
        $this->assertSame(GhnSchemes::STATUS_DISABLED, $disabled['status']);

        $ward1 = $rows[2];
        $this->assertSame('11', $ward1['provider_key']);
        $this->assertSame('1', $ward1['parent_key']);
        $this->assertSame(2, $ward1['depth']);
        $this->assertNull($ward1['extension_names']);

        $this->assertSame('13', $rows[4]['provider_key']);
    }

    public function testMissingIdFailsLoud(): void
    {
        $this->client->method('get')->willReturn([['name' => 'No id here', 'status' => 1]]);

        $this->expectException(LocalizedException::class);
        $this->fetcher->fetch(GhnSchemes::GHN_ADMIN_2025);
    }
}
