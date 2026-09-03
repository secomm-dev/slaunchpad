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
        $this->config->method('getSectionTitle')->willReturnCallback(
            static fn (string $key, ?int $storeId = null): string => Config::SECTION_TITLES[$key]
        );
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

    public function testConfiguredBrandSummaryWinsAndStoreSummarySectionRenders(): void
    {
        $this->config->method('getBrandSummary')->willReturn('Official OLV fashion store in Vietnam.');
        $this->config->method('getSiteTitle')->willReturn('OLV');

        $this->assertSame(
            "# OLV\n> Official OLV fashion store in Vietnam.\n\nLocale: vi_VN\nCurrency: VND\n"
            . "\n## Store Summary\nOfficial OLV fashion store in Vietnam.\n",
            $this->generator->generate(1)
        );
    }

    public function testEmptySummaryFallsBackToPublicSiteTitleAndOmitsStoreSummary(): void
    {
        $this->config->method('getBrandSummary')->willReturn('');
        $this->config->method('getSiteTitle')->willReturn('OLV');

        $body = $this->generator->generate(1);

        $this->assertSame("# OLV\n> OLV\n\nLocale: vi_VN\nCurrency: VND\n", $body);
        $this->assertStringNotContainsString('## Store Summary', $body);
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

    public function testCommerceSectionsRenderWithGuidanceAndLimitationsWhenAdvertised(): void
    {
        $this->config->method('getBrandSummary')->willReturn('');
        $this->config->method('getSiteTitle')->willReturn('OLV');

        // Fresh source mock: the setUp stub would otherwise match first.
        $commerceSource = $this->createMock(CommerceEndpointsSource::class);
        $commerceSource->method('getEntries')->willReturn([
            [
                'label' => 'Store Information',
                'url' => 'https://example.com/ai/store?store=default',
                'purpose' => 'Store metadata, locale, currency and supported public catalog context.',
            ],
            [
                'label' => 'Product Detail',
                'url' => 'https://example.com/ai/products/{sku}?store=default',
                'purpose' => 'Retrieve public product information for a known SKU.',
                'plain' => true,
            ],
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

        $this->assertStringContainsString('## Agent Guidance', $body);
        $this->assertStringContainsString(
            'This site provides public machine-readable commerce endpoints for catalog discovery.',
            $body
        );
        $this->assertStringContainsString('## Machine-readable Commerce', $body);
        $this->assertStringContainsString('### Store Information', $body);
        $this->assertStringContainsString(
            'GET https://example.com/ai/store?store=default',
            $body
        );
        $this->assertStringContainsString(
            'Purpose: Retrieve public product information for a known SKU.',
            $body
        );
        $this->assertStringContainsString('## Commerce Limitations', $body);
        $this->assertStringContainsString('Transactional operations must use the storefront.', $body);
        // Deterministic byte output.
        $this->assertSame($body, $generator->generate(1));
    }

    public function testCommerceSectionsAbsentWhenSourceEmpty(): void
    {
        $this->config->method('getBrandSummary')->willReturn('');
        $this->config->method('getSiteTitle')->willReturn('OLV');

        $body = $this->generator->generate(1);

        $this->assertStringNotContainsString('Machine-readable Commerce', $body);
        $this->assertStringNotContainsString('Agent Guidance', $body);
        $this->assertStringNotContainsString('Commerce Limitations', $body);
        $this->assertStringNotContainsString('/ai/', $body);
    }

    /**
     * SPEC-TASK-QYZMF1 §5 AC-4: no unsupported capability may ever be claimed —
     * UCP/WebMCP/MCP/checkout mutations do not exist in this runtime.
     */
    public function testNoUnsupportedCapabilityClaims(): void
    {
        $this->config->method('getBrandSummary')->willReturn('B');
        $this->config->method('getSiteTitle')->willReturn('OLV');

        $commerceSource = $this->createMock(CommerceEndpointsSource::class);
        $commerceSource->method('getEntries')->willReturn([
            ['label' => 'Store Information', 'url' => 'https://example.com/ai/store?store=default', 'purpose' => 'p'],
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

        foreach ([
            'UCP', 'ucp', '.well-known', 'MCP', 'WebMCP', 'tools/list',
            'create_cart', 'create_checkout', 'update_checkout', 'complete_checkout',
            'Shop Pay', 'agent-driven payment', 'order tracking',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body, "forbidden claim: {$forbidden}");
        }
    }
}
