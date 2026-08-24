<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Service\Url;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Store\Model\Store;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Service\Url\PublicUrlResolver;

class PublicUrlResolverTest extends TestCase
{
    /**
     * @var UrlFinderInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $urlFinder;

    /**
     * @var PublicUrlResolver
     */
    private $resolver;

    /**
     * @var Store|\PHPUnit\Framework\MockObject\MockObject
     */
    private $store;

    protected function setUp(): void
    {
        $this->urlFinder = $this->createMock(UrlFinderInterface::class);
        $this->resolver = new PublicUrlResolver($this->urlFinder);
        $this->store = $this->createMock(Store::class);
        $this->store->method('getId')->willReturn(1);
        $this->store->method('getBaseUrl')->willReturn('https://shop.example.com/');
    }

    public function testNoRewriteYieldsNulls(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn(9);

        $this->urlFinder->method('findAllByData')->willReturn([]);

        $this->assertSame(
            ['public_url' => null, 'canonical_url' => null],
            $this->resolver->getProductUrls($product, $this->store)
        );
    }

    public function testOldestNonRedirectRewriteWins(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn(9);

        $newer = $this->rewrite(20, 'alias/linen-shirt');
        $older = $this->rewrite(5, 'linen-shirt.html');

        $this->urlFinder->method('findAllByData')->willReturn([$newer, $older]);

        $urls = $this->resolver->getProductUrls($product, $this->store);

        $this->assertSame('https://shop.example.com/linen-shirt.html', $urls['public_url']);
        $this->assertSame('https://shop.example.com/linen-shirt.html', $urls['canonical_url']);
    }

    public function testQuerySuffixIsStripped(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn(9);

        $this->urlFinder->method('findAllByData')->willReturn([$this->rewrite(5, 'p.html?foo=1')]);

        $urls = $this->resolver->getProductUrls($product, $this->store);

        $this->assertSame('https://shop.example.com/p.html', $urls['public_url']);
    }

    public function testCategoryUrlsResolveFromCategoryEntity(): void
    {
        $this->urlFinder->method('findAllByData')->willReturn([$this->rewrite(7, 'bedding')]);

        $urls = $this->resolver->getCategoryUrls(4, $this->store);

        $this->assertSame('https://shop.example.com/bedding', $urls['canonical_url']);
    }

    /**
     * Create a rewrite mock.
     *
     * @param int $id url_rewrite_id
     * @param string $requestPath request path
     * @return UrlRewrite|\PHPUnit\Framework\MockObject\MockObject
     */
    private function rewrite(int $id, string $requestPath)
    {
        $rewrite = $this->createMock(UrlRewrite::class);
        $rewrite->method('getUrlRewriteId')->willReturn($id);
        $rewrite->method('getRequestPath')->willReturn($requestPath);

        return $rewrite;
    }
}
