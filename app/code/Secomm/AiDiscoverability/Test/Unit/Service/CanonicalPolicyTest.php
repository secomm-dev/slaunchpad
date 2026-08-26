<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Test\Unit\Service;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use PHPUnit\Framework\TestCase;
use Secomm\AiDiscoverability\Service\CanonicalPolicy;
use Secomm\AiDiscoverability\Service\SeoPolicy;

class CanonicalPolicyTest extends TestCase
{
    /**
     * @var UrlFinderInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $urlFinder;

    /**
     * @var SeoPolicy|\PHPUnit\Framework\MockObject\MockObject
     */
    private $seoPolicy;

    /**
     * @var CanonicalPolicy
     */
    private $policy;

    /**
     * @var StoreInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $store;

    protected function setUp(): void
    {
        $this->urlFinder = $this->createMock(UrlFinderInterface::class);
        $this->seoPolicy = $this->createMock(SeoPolicy::class);
        $this->policy = new CanonicalPolicy($this->urlFinder, $this->seoPolicy);

        $this->store = $this->createMock(Store::class);
        $this->store->method('getId')->willReturn(1);
        $this->store->method('getBaseUrl')->willReturn('https://shop.test/');
    }

    private function rewrite(int $id, string $path): UrlRewrite
    {
        $rewrite = $this->createMock(UrlRewrite::class);
        $rewrite->method('getUrlRewriteId')->willReturn($id);
        $rewrite->method('getRequestPath')->willReturn($path);

        return $rewrite;
    }

    public function testCategoryUsesOldestNonRedirectRewrite(): void
    {
        $this->seoPolicy->method('applyTrailingSlash')->willReturnArgument(0);
        $this->urlFinder->method('findAllByData')->willReturn([
            $this->rewrite(99, 'women/dresses-new'),
            $this->rewrite(40, 'women/dresses'),
        ]);

        $this->assertSame('https://shop.test/women/dresses', $this->policy->getCategoryUrl(5, $this->store));
    }

    public function testCategoryWithoutRewriteIsNull(): void
    {
        $this->urlFinder->method('findAllByData')->willReturn([]);

        $this->assertNull($this->policy->getCategoryUrl(5, $this->store));
    }

    public function testQueryStrippedAndDoubleSlashesCollapsed(): void
    {
        $this->seoPolicy->method('applyTrailingSlash')->willReturnArgument(0);

        $this->assertSame(
            'https://shop.test/about-us',
            $this->policy->getUrlForPath('/about-us?utm=x', $this->store)
        );
        $this->assertSame(
            'https://shop.test/a/b',
            $this->policy->getUrlForPath('//a//b', $this->store)
        );
    }

    public function testHomeUrlHasNoTrailingSlashAppendix(): void
    {
        $this->assertSame('https://shop.test', $this->policy->getHomeUrl($this->store));
    }
}
