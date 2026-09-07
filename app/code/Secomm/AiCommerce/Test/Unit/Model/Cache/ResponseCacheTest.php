<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Model\Cache;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Model\Cache\ResponseCache;

class ResponseCacheTest extends TestCase
{
    /**
     * @var CacheInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $cache;

    /**
     * @var ResponseCache
     */
    private $responseCache;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->responseCache = new ResponseCache($this->cache, new Json());
    }

    public function testStoreIsPartOfCacheIdentity(): void
    {
        $keys = [];

        $this->cache->method('load')->willReturnCallback(
            function (string $key) use (&$keys) {
                $keys[] = $key;

                return null;
            }
        );

        $this->responseCache->load(1, 'search', ['q' => 'linen']);
        $this->responseCache->load(2, 'search', ['q' => 'linen']);

        $this->assertCount(2, $keys);
        $this->assertNotSame($keys[0], $keys[1]);
    }

    public function testNormalizedParamsDifferInIdentity(): void
    {
        $keys = [];

        $this->cache->method('load')->willReturnCallback(
            function (string $key) use (&$keys) {
                $keys[] = $key;

                return null;
            }
        );

        $this->responseCache->load(1, 'search', ['q' => 'linen', 'store' => 'en']);
        $this->responseCache->load(1, 'search', ['q' => 'linen', 'store' => 'en', 'page' => '2']);

        $this->assertNotSame($keys[0], $keys[1]);
    }

    public function testSameParamsHitSameKeyRegardlessOfParamOrder(): void
    {
        $keys = [];

        $this->cache->method('load')->willReturnCallback(
            function (string $key) use (&$keys) {
                $keys[] = $key;

                return null;
            }
        );

        $this->responseCache->load(1, 'search', ['q' => 'a', 'page' => '1']);
        $this->responseCache->load(1, 'search', ['page' => '1', 'q' => 'a']);

        $this->assertSame($keys[0], $keys[1]);
    }

    public function testSaveUsesModuleAndStoreTags(): void
    {
        $this->cache->expects($this->once())->method('save')
            ->with(
                $this->anything(),
                $this->stringContains('_s1_search_'),
                ['secomm_aic', 'secomm_aic_store_1'],
                60
            );

        $this->responseCache->save(['items' => []], 1, 'search', ['q' => 'x'], 60);
    }

    public function testZeroLifetimeNeverSaves(): void
    {
        $this->cache->expects($this->never())->method('save');
        $this->responseCache->save(['items' => []], 1, 'search', [], 0);
    }

    public function testProductRouteKeySeparatesStoresAndSkus(): void
    {
        $keys = [];

        $this->cache->method('load')->willReturnCallback(
            function (string $key) use (&$keys) {
                $keys[] = $key;

                return null;
            }
        );

        // Same SKU, two stores: distinct keys (store is in the identity).
        $this->responseCache->load(1, 'product', ['sku' => 'ABC']);
        $this->responseCache->load(3, 'product', ['sku' => 'ABC']);
        // Same store, different SKU: distinct keys.
        $this->responseCache->load(1, 'product', ['sku' => 'XYZ']);
        // Same store + SKU again: identical key (stable warm hits).

        $this->assertNotSame($keys[0], $keys[1]);
        $this->assertNotSame($keys[0], $keys[2]);
        $this->assertSame($keys[0], $this->loadKey(1, 'product', ['sku' => 'ABC']));
    }

    /**
     * Compute the key one more load would use.
     *
     * @param int $storeId store view id
     * @param string $route route name
     * @param array $params normalized params
     * @return string cache key
     */
    private function loadKey(int $storeId, string $route, array $params): string
    {
        $key = '';
        $this->cache->method('load')->willReturnCallback(
            function (string $k) use (&$key) {
                $key = $k;

                return null;
            }
        );
        $this->responseCache->load($storeId, $route, $params);

        return $key;
    }
}
