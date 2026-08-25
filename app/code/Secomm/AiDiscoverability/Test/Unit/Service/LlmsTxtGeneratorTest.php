<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Test\Unit\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\AiDiscoverability\Model\Config;
use Secomm\AiDiscoverability\Service\LlmsTxtFormatter;
use Secomm\AiDiscoverability\Service\LlmsTxtGenerator;
use Secomm\AiDiscoverability\Service\Source\CategoriesSource;
use Secomm\AiDiscoverability\Service\Source\CmsPagesSource;
use Secomm\AiDiscoverability\Service\Source\CommerceEndpointsSource;
use Secomm\AiDiscoverability\Service\Source\PriorityUrlsSource;
use Secomm\AiDiscoverability\Service\Source\SitemapRefsSource;
use Secomm\AiDiscoverability\Service\UrlCollector;

/**
 * Covers the public-safe summary fallback policy (SPEC-TASK-0X552E §12.2, second pass):
 * brand_summary → effective site title → omitted; never the internal store-view name.
 */
class LlmsTxtGeneratorTest extends TestCase
{
    /**
     * @var Config&MockObject
     */
    private $config;

    /**
     * @var StoreManagerInterface&MockObject
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface&MockObject
     */
    private $scopeConfig;

    /**
     * @var StoreInterface&MockObject
     */
    private $store;

    /**
     * @var LlmsTxtGenerator
     */
    private $generator;

    /**
     * @var CommerceEndpointsSource&MockObject
     */
    private $commerceSource;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->store = $this->createMock(StoreInterface::class);
        $this->commerceSource = $this->createMock(CommerceEndpointsSource::class);

        $this->store->method('getId')->willReturn(1);
        $this->store->method('getName')->willReturn('Default Store View');
        $this->storeManager->method('getStore')->with(1)->willReturn($this->store);

        $this->config->method('getMaxUrls')->willReturn(100);
        $this->config->method('isIncludeSitemapRefs')->willReturn(false);
        $this->scopeConfig->method('getValue')->willReturn('vi_VN');
        $this->config->method('getCurrencyCode')->willReturn('VND');
        $this->commerceSource->method('getEntries')->willReturn([]);

        $this->generator = new LlmsTxtGenerator(
            $this->config,
            $this->storeManager,
            $this->scopeConfig,
            $this->createStub(PriorityUrlsSource::class),
            $this->createStub(CmsPagesSource::class),
            $this->createStub(CategoriesSource::class),
            $this->createStub(SitemapRefsSource::class),
            $this->commerceSource,
            new UrlCollector(),
            new LlmsTxtFormatter(),
            $this->createStub(LoggerInterface::class)
        );
    }

    public function testConfiguredBrandSummaryWins(): void
    {
        $this->config->method('getBrandSummary')->willReturn('Official OLV fashion store in Vietnam.');
        $this->config->method('getSiteTitle')->willReturn('OLV');

        $this->assertSame(
            "# OLV\n> Official OLV fashion store in Vietnam.\n\nLocale: vi_VN\nCurrency: VND\n",
            $this->generator->generate(1)
        );
    }

    public function testEmptySummaryFallsBackToPublicSiteTitle(): void
    {
        $this->config->method('getBrandSummary')->willReturn('');
        $this->config->method('getSiteTitle')->willReturn('OLV');

        $this->assertSame(
            "# OLV\n> OLV\n\nLocale: vi_VN\nCurrency: VND\n",
            $this->generator->generate(1)
        );
    }

    public function testInternalStoreNameIsNeverEmittedAsSummary(): void
    {
        $this->config->method('getBrandSummary')->willReturn('');
        $this->config->method('getSiteTitle')->willReturn('');

        $body = $this->generator->generate(1);

        // No public summary at all: blockquote omitted entirely, H1 falls back
        // to the internal store-view name (last resort, H1 only).
        $this->assertSame(
            "# Default Store View\n\nLocale: vi_VN\nCurrency: VND\n",
            $body
        );
        $this->assertStringNotContainsString('> Default Store View', $body);
    }

    public function testCommerceSectionAppendedWhenAiCommerceAdvertised(): void
    {
        $this->config->method('getBrandSummary')->willReturn('');
        $this->config->method('getSiteTitle')->willReturn('OLV');

        // Fresh source mock: the setUp stub would otherwise match first.
        $commerceSource = $this->createMock(CommerceEndpointsSource::class);
        $commerceSource->method('getEntries')->willReturn([
            ['label' => 'Store Information', 'url' => 'https://example.com/ai/store?store=default'],
            ['label' => 'Product Search', 'url' => 'https://example.com/ai/catalog/search?store=default'],
            ['label' => 'Categories', 'url' => 'https://example.com/ai/categories?store=default'],
            ['label' => 'Product Detail', 'url' => 'https://example.com/ai/products/{sku}?store=default', 'plain' => true],
        ]);

        $generator = new LlmsTxtGenerator(
            $this->config,
            $this->storeManager,
            $this->scopeConfig,
            $this->createStub(PriorityUrlsSource::class),
            $this->createStub(CmsPagesSource::class),
            $this->createStub(CategoriesSource::class),
            $this->createStub(SitemapRefsSource::class),
            $commerceSource,
            new UrlCollector(),
            new LlmsTxtFormatter(),
            $this->createStub(LoggerInterface::class)
        );

        $body = $generator->generate(1);

        $this->assertSame(
            "# OLV\n> OLV\n\nLocale: vi_VN\nCurrency: VND\n"
            . "\n## Machine-readable Commerce\n"
            . "- [Store Information](https://example.com/ai/store?store=default)\n"
            . "- [Product Search](https://example.com/ai/catalog/search?store=default)\n"
            . "- [Categories](https://example.com/ai/categories?store=default)\n"
            . "- Product Detail: https://example.com/ai/products/{sku}?store=default\n",
            $body
        );
    }

    public function testCommerceSectionAbsentWhenSourceEmpty(): void
    {
        $this->config->method('getBrandSummary')->willReturn('');
        $this->config->method('getSiteTitle')->willReturn('OLV');

        $body = $this->generator->generate(1);

        $this->assertStringNotContainsString('Machine-readable Commerce', $body);
    }
}
