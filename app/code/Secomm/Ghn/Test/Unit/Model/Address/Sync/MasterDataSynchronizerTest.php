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
use Secomm\Ghn\Model\Address\GhnSchemes;
use Secomm\Ghn\Model\Address\Sync\MasterDataFetcher;
use Secomm\Ghn\Model\Address\Sync\MasterDataSynchronizer;
use Secomm\Ghn\Model\Address\Sync\UnitPersister;
use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\ResourceModel\AddressUnit;

/**
 * TASK-MZ2TCB / AC-B2 — synchronizer: dry-run writes nothing, parent-first upsert with resolved
 * parent_id, disable-on-missing, duplicate provider_key + unknown fetcher fail loud.
 */
class MasterDataSynchronizerTest extends TestCase
{
    private MasterDataFetcher&MockObject $fetcher;

    private AddressUnit&MockObject $unitResource;

    private Config&MockObject $config;

    private MasterDataSynchronizer $synchronizer;

    protected function setUp(): void
    {
        $this->fetcher = $this->createMock(MasterDataFetcher::class);
        $this->fetcher->method('supports')->willReturn(GhnSchemes::GHN_ADMIN_2025);
        $this->unitResource = $this->createMock(AddressUnit::class);
        $this->config = $this->createMock(Config::class);
        $this->config->method('getEnvironment')->willReturn('sandbox');

        $this->synchronizer = new MasterDataSynchronizer(
            [GhnSchemes::GHN_ADMIN_2025 => $this->fetcher],
            new UnitPersister($this->unitResource),
            $this->config
        );
    }

    public function testDryRunWritesNothing(): void
    {
        $this->fetcher->method('fetch')->willReturn([
            ['provider_key' => '1', 'provider_id' => '1', 'provider_code' => null, 'parent_key' => null, 'depth' => 1, 'name' => 'A', 'extension_names' => null, 'status' => 'ACTIVE'],
        ]);
        $this->unitResource->method('fetchKeys')->willReturn(['9' => 55]);
        $this->unitResource->expects($this->never())->method('upsert');
        $this->unitResource->expects($this->never())->method('disableMissing');

        $report = $this->synchronizer->sync(GhnSchemes::GHN_ADMIN_2025, true);

        $this->assertTrue($report['dry_run']);
        $this->assertSame(1, $report['fetched']);
        $this->assertSame(1, $report['disabled']); // key '9' would disappear
    }

    public function testRealRunResolvesParentIdsAndDisablesMissing(): void
    {
        $rows = [
            ['provider_key' => '1', 'provider_id' => '1', 'provider_code' => null, 'parent_key' => null, 'depth' => 1, 'name' => 'A', 'extension_names' => null, 'status' => 'ACTIVE'],
            ['provider_key' => '11', 'provider_id' => '11', 'provider_code' => null, 'parent_key' => '1', 'depth' => 2, 'name' => 'A1', 'extension_names' => null, 'status' => 'ACTIVE'],
        ];

        $this->fetcher->method('fetch')->willReturn($rows);
        // fetchKeys: knownKeys report (1) + persister initial state (2) + refresh per depth (3..4).
        $matcher = $this->exactly(4);
        $this->unitResource->expects($matcher)
            ->method('fetchKeys')
            ->willReturnCallback(function () use ($matcher): array {
                return $matcher->numberOfInvocations() === 1 ? ['1' => 10, '99' => 77] : ['1' => 10, '11' => 20];
            });

        $captured = [];
        // One upsert per depth group (province first, then ward) — parent-first write order.
        $this->unitResource->expects($this->exactly(2))
            ->method('upsert')
            ->willReturnCallback(function (array $batch) use (&$captured): int {
                $captured[] = $batch;

                return count($batch);
            });
        $this->unitResource->expects($this->once())
            ->method('disableMissing')
            ->with(GhnSchemes::GHN_ADMIN_2025, $this->callback(static fn (array $keep): bool => isset($keep['1'], $keep['11'])), $this->anything())
            ->willReturn(1);

        $report = $this->synchronizer->sync(GhnSchemes::GHN_ADMIN_2025, false);

        $this->assertFalse($report['dry_run']);
        $this->assertSame(1, $report['disabled']);
        $this->assertCount(2, $captured);
        $this->assertSame('1', $captured[0][0]['provider_key']);
        $this->assertNull($captured[0][0]['parent_id']);
        $this->assertSame('11', $captured[1][0]['provider_key']);
        $this->assertSame(10, $captured[1][0]['parent_id']); // parent entity resolved from key map
        $this->assertStringStartsWith('sandbox:', $report['source_version']);
    }

    public function testDuplicateProviderKeyFailsLoud(): void
    {
        $this->fetcher->method('fetch')->willReturn([
            ['provider_key' => '1', 'provider_id' => '1', 'provider_code' => null, 'parent_key' => null, 'depth' => 1, 'name' => 'A', 'extension_names' => null, 'status' => 'ACTIVE'],
            ['provider_key' => '1', 'provider_id' => '1', 'provider_code' => null, 'parent_key' => null, 'depth' => 1, 'name' => 'A dup', 'extension_names' => null, 'status' => 'ACTIVE'],
        ]);

        $this->expectException(LocalizedException::class);
        $this->synchronizer->sync(GhnSchemes::GHN_ADMIN_2025, false);
    }

    public function testUnknownSchemeFails(): void
    {
        $this->expectException(LocalizedException::class);
        $this->synchronizer->sync('VN_ADMIN_2025', false);
    }
}

