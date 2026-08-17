<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Address;

use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\Ghtk\Api\Data\GhtkAddressMapInterface;
use Secomm\Ghtk\Model\Address\BestEffortViVnResolver;
use Secomm\Ghtk\Model\Address\DestinationAddressResolver;
use Secomm\Ghtk\Model\Address\WardIdBridge;
use Secomm\Ghtk\Model\GhtkAddressMapRepository;

class DestinationAddressResolverTest extends TestCase
{
    private $repo;
    private $bridge;
    private $bestEffort;
    private $cache;
    private $logger;
    private DestinationAddressResolver $resolver;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(GhtkAddressMapRepository::class);
        $this->bridge = $this->createMock(WardIdBridge::class);
        $this->bestEffort = $this->createMock(BestEffortViVnResolver::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->cache->method('load')->willReturn(false); // cache miss by default

        $this->resolver = new DestinationAddressResolver(
            $this->repo,
            $this->bridge,
            $this->bestEffort,
            $this->cache,
            $this->logger
        );
    }

    public function testMappingHitReturnsExact(): void
    {
        $row = $this->createMock(GhtkAddressMapInterface::class);
        $row->method('getGhtkProvince')->willReturn('Hà Nội');
        $row->method('getGhtkDistrict')->willReturn('Hoàn Kiếm');
        $row->method('getGhtkWard')->willReturn('Phường A');
        $this->repo->method('findActive')->willReturn($row);
        $this->cache->expects($this->once())->method('save');

        $address = $this->resolver->resolve('VN', 1157, 1);

        $this->assertNotNull($address);
        $this->assertTrue($address->isExact);
        $this->assertSame('Hà Nội', $address->province);
        $this->assertSame('Hoàn Kiếm', $address->district);
        $this->assertSame('Phường A', $address->ward);
    }

    public function testCacheHitSkipsRepository(): void
    {
        $cached = '{"province":"P","district":"D","ward":"W","isExact":true}';
        $this->cache = $this->createMock(CacheInterface::class);
        $this->cache->method('load')->willReturn($cached);
        $this->resolver = new DestinationAddressResolver(
            $this->repo,
            $this->bridge,
            $this->bestEffort,
            $this->cache,
            $this->logger
        );

        $this->repo->expects($this->never())->method('findActive');

        $address = $this->resolver->resolve('VN', 1157, 1);
        $this->assertNotNull($address);
        $this->assertSame('W', $address->ward);
        $this->assertTrue($address->isExact);
    }

    public function testMissFallsBackToBestEffort(): void
    {
        $this->repo->method('findActive')->willReturn(null);
        $this->bestEffort->method('getProvinceName')->willReturn('Thành phố Hà Nội');
        $this->bestEffort->method('getWardName')->willReturn('Phường B');

        $address = $this->resolver->resolve('VN', 1157, 3);

        $this->assertNotNull($address);
        $this->assertFalse($address->isExact);
        $this->assertNull($address->district);
    }

    public function testMissWithIncompleteBestEffortReturnsNull(): void
    {
        $this->repo->method('findActive')->willReturn(null);
        $this->bestEffort->method('getProvinceName')->willReturn(null);

        $this->assertNull($this->resolver->resolve('VN', 1157, 3));
    }

    public function testNoWardIdentifierReturnsNull(): void
    {
        $this->assertNull($this->resolver->resolve('VN', 1157, null, null));
    }

    public function testLegacyWardNameUsesBridge(): void
    {
        $this->bridge->expects($this->once())->method('resolveWardId')->with(1157, 'Hoan Kiem Ward')->willReturn(5);
        $row = $this->createMock(GhtkAddressMapInterface::class);
        $row->method('getGhtkProvince')->willReturn('P');
        $row->method('getGhtkWard')->willReturn('W');
        $row->method('getGhtkDistrict')->willReturn(null);
        $this->repo->method('findActive')->willReturn($row);

        $address = $this->resolver->resolve('VN', 1157, null, 'Hoan Kiem Ward');

        $this->assertNotNull($address);
        $this->assertSame('W', $address->ward);
    }

    public function testNeverThrowsOnException(): void
    {
        $this->repo->method('findActive')->willThrowException(new \RuntimeException('db down'));

        $this->assertNull($this->resolver->resolve('VN', 1157, 1));
    }
}
