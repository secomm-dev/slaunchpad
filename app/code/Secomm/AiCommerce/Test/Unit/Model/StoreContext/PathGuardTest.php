<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Model\StoreContext;

use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Model\Config;
use Secomm\AiCommerce\Model\StoreContext\PathGuard;

/**
 * BUG-D4QK1Q §9: the base path is part of a store view's public API
 * contract — boundary-exact matching against the TARGET store's configured
 * path, shared by router admission and controller defense-in-depth.
 */
class PathGuardTest extends TestCase
{
    /**
     * @var Config|\PHPUnit\Framework\MockObject\MockObject
     */
    private $config;

    /**
     * @var PathGuard
     */
    private $pathGuard;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getEndpointPath')->willReturnCallback(
            fn (?int $storeId = null): string => (int) $storeId === 3 ? 'agent' : 'ai'
        );
        $this->pathGuard = new PathGuard($this->config);
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: bool}>
     */
    public static function matchProvider(): array
    {
        return [
            'default base exact' => ['/ai', 1, true],
            'default base tail' => ['/ai/store', 1, true],
            'default base nested' => ['/ai/products/sku-1', 1, true],
            'prefix of a longer segment is NOT a match' => ['/ai-extra/store', 1, false],
            'wrong store path' => ['/agent/store', 1, false],
            'store override base exact' => ['/agent', 3, true],
            'store override base tail' => ['/agent/store', 3, true],
            'store override rejects default path' => ['/ai/store', 3, false],
            'root path never matches' => ['/', 1, false],
            'empty path never matches' => ['', 1, false],
        ];
    }

    /**
     * @dataProvider matchProvider
     * @param string $pathInfo request path
     * @param int $storeId target store view id
     * @param bool $expected expected verdict
     */
    public function testMatchesStrictStoreScopedBoundary(string $pathInfo, int $storeId, bool $expected): void
    {
        $this->assertSame($expected, $this->pathGuard->matches($pathInfo, $storeId));
    }
}
