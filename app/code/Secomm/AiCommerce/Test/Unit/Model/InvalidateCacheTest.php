<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Model;

use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Model\Cache\ResponseCache;
use Secomm\AiCommerce\Model\InvalidateCache;

/**
 * SPEC-TASK-AIC-PDC1 §3.4: at runtime CacheInterface is App\Cache\Proxy,
 * whose clean($tags) contract swallows a Zend-style mode string as a tag
 * (runtime-proven: SINTER used "798_MATCHINGTAG"). Invalidations must pass
 * the plain tags array only.
 */
class InvalidateCacheTest extends TestCase
{
    /**
     * @var CacheInterface&MockObject
     */
    private $cache;

    /**
     * @var InvalidateCache
     */
    private $invalidateCache;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->invalidateCache = new InvalidateCache($this->cache, new ResponseCache(
            $this->createMock(CacheInterface::class),
            $this->createMock(\Magento\Framework\Serialize\Serializer\Json::class)
        ));
    }

    public function testCleanAllPassesModuleTagOnly(): void
    {
        $this->cache->expects($this->once())->method('clean')
            ->with([ResponseCache::CACHE_TAG]);
        $this->invalidateCache->cleanAll();
    }

    public function testCleanStorePassesStoreTagOnly(): void
    {
        $this->cache->expects($this->once())->method('clean')
            ->with(['secomm_aic_store_3']);
        $this->invalidateCache->cleanStore(3);
    }
}
