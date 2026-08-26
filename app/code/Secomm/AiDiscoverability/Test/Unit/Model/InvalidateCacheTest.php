<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Secomm\AiDiscoverability\Model\Cache\Type;
use Secomm\AiDiscoverability\Model\InvalidateCache;
use Secomm\AiDiscoverability\Service\LlmsTxtProvider;

/**
 * Pins the cache-type clean() contract. The module now cleans through its
 * dedicated cache TYPE (Zend Cache FrontendInterface via TagScope), whose
 * native signature is clean($mode, array $tags) / clean() — the OPPOSITE of
 * the App\Cache\Proxy clean(array $tags) one-argument contract fixed in
 * SPEC-CHANGE-AIDL-CINV1. These tests prove both the correct mode/tag usage
 * and the type-tag scoping that makes
 * `cache:clean secomm_ai_discoverability` equivalent.
 */
class InvalidateCacheTest extends TestCase
{
    private Type&\PHPUnit\Framework\MockObject\MockObject $cacheType;

    private LlmsTxtProvider&\PHPUnit\Framework\MockObject\MockObject $provider;

    private InvalidateCache $sut;

    protected function setUp(): void
    {
        $this->cacheType = $this->createMock(Type::class);
        $this->provider = $this->createMock(LlmsTxtProvider::class);
        $this->sut = new InvalidateCache($this->cacheType, $this->provider);
    }

    public function testCleanStoreMatchesStoreTagWithinTypeScope(): void
    {
        $this->provider
            ->method('storeTag')
            ->with(3)
            ->willReturn(LlmsTxtProvider::CACHE_TAG . '_store_3');

        // TagScope contract: clean($mode, [$tags]) — never the Proxy form.
        $this->cacheType
            ->expects($this->once())
            ->method('clean')
            ->with(
                \Zend_Cache::CLEANING_MODE_MATCHING_TAG,
                [LlmsTxtProvider::CACHE_TAG . '_store_3']
            )
            ->willReturn(true);

        $this->sut->cleanStore(3);
    }

    public function testCleanAllUsesScopeAllNeverFlushesSharedFrontend(): void
    {
        // clean() with no mode ⇒ CLEANING_MODE_ALL ⇒ TagScope rewrites it to
        // MATCHING_TAG [type tag] — a flush of the whole shared default
        // frontend must never be requested.
        $this->cacheType
            ->expects($this->once())
            ->method('clean')
            ->with(\Zend_Cache::CLEANING_MODE_ALL, [])
            ->willReturn(true);

        $this->sut->cleanAll();
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
        $this->cacheType
            ->expects($this->exactly(2))
            ->method('clean')
            ->willReturnCallback(
                function (string $mode, array $tags) use (&$expected): bool {
                    self::assertSame(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, $mode);
                    self::assertSame(array_shift($expected), $tags);

                    return true;
                }
            );

        $this->sut->cleanStores(['2', 5, 5, 2]);
    }
}
