<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Test\Unit\Model;

use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\TestCase;
use Secomm\AiDiscoverability\Model\InvalidateCache;
use Secomm\AiDiscoverability\Service\LlmsTxtProvider;

/**
 * Pins the runtime CacheInterface contract: the concrete CacheInterface is
 * App\Cache\Proxy whose clean(array $tags) accepts exactly one array argument.
 * The legacy Zend-style clean($mode, $tags) call silently no-ops at runtime
 * (the mode string becomes a tag) — these tests prove it is gone.
 */
class InvalidateCacheTest extends TestCase
{
    private CacheInterface&\PHPUnit\Framework\MockObject\MockObject $cache;

    private LlmsTxtProvider&\PHPUnit\Framework\MockObject\MockObject $provider;

    private InvalidateCache $sut;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->provider = $this->createMock(LlmsTxtProvider::class);
        $this->sut = new InvalidateCache($this->cache, $this->provider);
    }

    public function testCleanAllPassesSingleArrayWithTagOnly(): void
    {
        $this->cache
            ->expects($this->once())
            ->method('clean')
            ->with([LlmsTxtProvider::CACHE_TAG])
            ->willReturn(true);

        $this->sut->cleanAll();
    }

    public function testCleanAllDoesNotUseZendStyleModeArgument(): void
    {
        $invocation = new \PHPUnit\Framework\MockObject\Rule\InvokedCount(1);
        $this->cache
            ->expects($invocation)
            ->method('clean')
            ->willReturnCallback(
                function (...$args) {
                    // Proxy contract: exactly one argument, an array of tags.
                    self::assertCount(1, $args, 'clean() must receive exactly one argument');
                    self::assertIsArray($args[0], 'clean() argument must be a plain tags array');
                    self::assertNotContains(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, $args[0]);
                    return true;
                }
            );

        $this->sut->cleanAll();
    }

    public function testCleanStoreUsesProviderStoreTag(): void
    {
        $this->provider
            ->method('storeTag')
            ->with(3)
            ->willReturn(LlmsTxtProvider::CACHE_TAG . '_store_3');

        $this->cache
            ->expects($this->once())
            ->method('clean')
            ->with([LlmsTxtProvider::CACHE_TAG . '_store_3'])
            ->willReturn(true);

        $this->sut->cleanStore(3);
    }

    public function testCleanStoresDeduplicatesAndDelegates(): void
    {
        $this->provider
            ->method('storeTag')
            ->willReturnCallback(
                static fn (int $storeId): string => LlmsTxtProvider::CACHE_TAG . '_store_' . $storeId
            );

        $expected = [
            [LlmsTxtProvider::CACHE_TAG . '_store_2'],
            [LlmsTxtProvider::CACHE_TAG . '_store_5'],
        ];
        $this->cache
            ->expects($this->exactly(2))
            ->method('clean')
            ->willReturnCallback(
                function (array $tags) use (&$expected): bool {
                    self::assertSame(array_shift($expected), $tags);
                    return true;
                }
            );

        $this->sut->cleanStores(['2', 5, 5, 2]);
    }
}
